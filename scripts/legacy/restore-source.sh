#!/usr/bin/env bash
# Restore a Varsity SQL dump into a staging source database.
# The approved source is replaced only after the incoming copy verifies.
# This script never writes to the Laravel destination database.
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: ./scripts/legacy/restore-source.sh /absolute/path/varsity-latest.sql.gz" >&2
  exit 1
fi

dump="$1"
if [[ ! -f "$dump" ]]; then
  echo "Dump file was not found." >&2
  exit 1
fi

case "$dump" in
  *.sql.gz)
    gzip -t "$dump"
    ;;
  *.sql)
    if ! grep -q "CREATE TABLE" "$dump"; then
      echo "The SQL dump does not contain a CREATE TABLE statement." >&2
      exit 1
    fi
    ;;
  *)
    echo "The dump must be a .sql or .sql.gz file." >&2
    exit 1
    ;;
esac

if [[ ! -s "$dump" ]]; then
  echo "The dump file is empty." >&2
  exit 1
fi

target_db="${LEGACY_STAGING_DB_DATABASE:-varsity_source}"
target_host="${LEGACY_STAGING_DB_HOST:-127.0.0.1}"
target_port="${LEGACY_STAGING_DB_PORT:-3306}"
dest_db="${DB_DATABASE:-}"
dest_host="${DB_HOST:-}"
dest_port="${DB_PORT:-3306}"
laravel_project="${LEGACY_LARAVEL_DDEV_PROJECT:-restaurant-backend}"
incoming="${target_db}_incoming"
previous="${target_db}_previous"

if [[ ! "$target_db" =~ ^[A-Za-z0-9_]+$ || ! "$incoming" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "The staging database name contains unsupported characters." >&2
  exit 1
fi

normalize_host() {
  local host
  host="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
  case "$host" in
    localhost|127.0.0.1|::1) printf 'localhost' ;;
    *) printf '%s' "$host" ;;
  esac
}

ddev_approot() {
  local project="$1"
  local approot
  approot="$(ddev describe "$project" -j | sed -n 's/.*"approot":"\([^"]*\)".*/\1/p' | head -n 1)"
  if [[ -z "$approot" || ! -d "$approot" ]]; then
    echo "DDEV project ${project} was not found." >&2
    exit 1
  fi
  printf '%s' "$approot"
}

ddev_mysql() {
  local approot="$1"
  shift
  ( cd "$approot" && ddev mysql "$@" )
}

if [[ -n "${LEGACY_STAGING_DDEV_PROJECT:-}" && "$LEGACY_STAGING_DDEV_PROJECT" == "$laravel_project" ]]; then
  echo "Refusing to restore into the Laravel DDEV project." >&2
  exit 1
fi

staging_approot=""
laravel_approot=""
if [[ -n "${LEGACY_STAGING_DDEV_PROJECT:-}" ]]; then
  staging_approot="$(ddev_approot "$LEGACY_STAGING_DDEV_PROJECT")"
  if [[ -n "$dest_db" ]]; then
    laravel_approot="$(ddev_approot "$laravel_project" 2>/dev/null || true)"
  fi
fi

# Same host and port are allowed when the database names differ.
# The same database name is refused when the server identity also matches,
# including when the two connections use different host aliases.
if [[ -n "$dest_db" && "$target_db" == "$dest_db" && "$(normalize_host "$target_host")" == "$(normalize_host "$dest_host")" && "$target_port" == "$dest_port" ]]; then
  echo "Refusing to restore because the staging target is the Laravel database." >&2
  exit 1
fi

if [[ -n "$dest_db" && "$target_db" == "$dest_db" && -n "$staging_approot" && -n "$laravel_approot" ]]; then
  source_server="$(ddev_mysql "$staging_approot" -N -e "SELECT @@hostname" 2>/dev/null || true)"
  dest_server="$(ddev_mysql "$laravel_approot" -N -e "SELECT @@hostname" 2>/dev/null || true)"
  if [[ -n "$source_server" && "$source_server" == "$dest_server" ]]; then
    echo "Refusing to restore because the staging database is the Laravel database on the same server." >&2
    exit 1
  fi
fi

option_quote() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '"%s"' "$value"
}

option_file=""
owned_option_file=""
cleanup() {
  if [[ -n "$owned_option_file" && -f "$owned_option_file" ]]; then
    rm -f "$owned_option_file"
  fi
}
trap cleanup EXIT

mysql_exec() {
  if [[ -n "$staging_approot" ]]; then
    ddev_mysql "$staging_approot" "$@"
    return
  fi

  if [[ -z "$option_file" ]]; then
    if [[ -n "${LEGACY_STAGING_OPTION_FILE:-}" ]]; then
      option_file="$LEGACY_STAGING_OPTION_FILE"
    else
      if [[ -z "${LEGACY_STAGING_DB_USERNAME:-}" || -z "${LEGACY_STAGING_DB_PASSWORD:-}" ]]; then
        echo "Set LEGACY_STAGING_DB_USERNAME and LEGACY_STAGING_DB_PASSWORD, or LEGACY_STAGING_OPTION_FILE." >&2
        exit 1
      fi
      option_file="$(mktemp)"
      owned_option_file="$option_file"
      chmod 600 "$option_file"
      {
        printf '[client]\n'
        printf 'user=%s\n' "$(option_quote "$LEGACY_STAGING_DB_USERNAME")"
        printf 'password=%s\n' "$(option_quote "$LEGACY_STAGING_DB_PASSWORD")"
        printf 'host=%s\n' "$(option_quote "$target_host")"
        printf 'port=%s\n' "$target_port"
      } > "$option_file"
    fi
  fi

  mysql --defaults-extra-file="$option_file" "$@"
}

database_exists() {
  local name="$1"
  local found
  found="$(mysql_exec -N -e "SHOW DATABASES LIKE '${name}'")"
  [[ "$found" == "$name" ]]
}

if database_exists "$target_db"; then
  if [[ "${LEGACY_RESTORE_CONFIRM:-}" != "replace-source" ]]; then
    if [[ ! -t 0 ]]; then
      echo "The staging database already exists. Re-run with LEGACY_RESTORE_CONFIRM=replace-source after reviewing it." >&2
      exit 1
    fi
    read -r -p "Replace staging database ${target_db}? Type replace-source: " answer
    if [[ "$answer" != "replace-source" ]]; then
      echo "Restore cancelled. The existing staging database was not changed." >&2
      exit 1
    fi
  fi
fi

mysql_exec -e "DROP DATABASE IF EXISTS \`${incoming}\`; CREATE DATABASE \`${incoming}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if [[ "$dump" == *.sql.gz ]]; then
  gzip -dc "$dump" | mysql_exec "$incoming"
else
  mysql_exec "$incoming" < "$dump"
fi

required=(tbl_users tbl_category tbl_products tbl_tables tbl_discounts)
for table in "${required[@]}"; do
  count="$(mysql_exec -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${incoming}' AND table_name='${table}'")"
  if [[ "$count" != "1" ]]; then
    mysql_exec -e "DROP DATABASE IF EXISTS \`${incoming}\`;"
    echo "Restore verification failed. The approved staging database was not replaced." >&2
    exit 1
  fi
done

mysql_exec -e "CREATE DATABASE IF NOT EXISTS \`${target_db}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS \`${previous}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; SET FOREIGN_KEY_CHECKS=0;"

move_tables() {
  local from="$1"
  local to="$2"
  local tables
  tables="$(mysql_exec -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='${from}' AND table_type='BASE TABLE'")"
  if [[ -z "$tables" ]]; then
    return
  fi
  local sql="SET FOREIGN_KEY_CHECKS=0; RENAME TABLE "
  local first=1
  local table
  while IFS= read -r table; do
    [[ -z "$table" ]] && continue
    if [[ "$first" -eq 0 ]]; then
      sql+=", "
    fi
    sql+="\`${from}\`.\`${table}\` TO \`${to}\`.\`${table}\`"
    first=0
  done <<< "$tables"
  sql+=";"
  mysql_exec -e "$sql"
}

previous_tables="$(mysql_exec -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='${previous}' AND table_type='BASE TABLE'")"
if [[ -n "$previous_tables" ]]; then
  drop_sql="SET FOREIGN_KEY_CHECKS=0;"
  while IFS= read -r table; do
    [[ -z "$table" ]] && continue
    drop_sql+=" DROP TABLE \`${previous}\`.\`${table}\`;"
  done <<< "$previous_tables"
  mysql_exec -e "$drop_sql"
fi

move_tables "$target_db" "$previous"
move_tables "$incoming" "$target_db"
mysql_exec -e "DROP DATABASE IF EXISTS \`${incoming}\`;"

for table in "${required[@]}"; do
  count="$(mysql_exec -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${target_db}' AND table_name='${table}'")"
  if [[ "$count" != "1" ]]; then
    echo "The approved staging database failed its final table check." >&2
    exit 1
  fi
done

echo "Staging source ${target_db} is ready. The previous copy is in ${previous}."
echo "Point LEGACY_DB_DATABASE at ${target_db} and use the SELECT-only account. The Laravel database was not changed."
