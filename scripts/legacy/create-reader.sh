#!/usr/bin/env bash
# One-time administrative setup for the SELECT-only legacy account.
# Run this against the staging server, never against the Varsity production server
# and never against the Laravel destination database.
set -euo pipefail

target_db="${LEGACY_STAGING_DB_DATABASE:-varsity_source}"
reader="${LEGACY_DB_USERNAME:-}"
reader_password="${LEGACY_DB_PASSWORD:-}"
dest_db="${DB_DATABASE:-}"
dest_host="${DB_HOST:-}"
target_host="${LEGACY_STAGING_DB_HOST:-127.0.0.1}"
target_port="${LEGACY_STAGING_DB_PORT:-3306}"
dest_port="${DB_PORT:-3306}"

if [[ -z "$reader" || -z "$reader_password" ]]; then
  echo "Set LEGACY_DB_USERNAME and LEGACY_DB_PASSWORD before creating the reader." >&2
  exit 1
fi

if [[ ! "$target_db" =~ ^[A-Za-z0-9_]+$ || ! "$reader" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "The database or reader name contains unsupported characters." >&2
  exit 1
fi

if [[ -n "${LEGACY_STAGING_DDEV_PROJECT:-}" && "$LEGACY_STAGING_DDEV_PROJECT" == "${LEGACY_LARAVEL_DDEV_PROJECT:-restaurant-backend}" ]]; then
  echo "Refusing to create the reader inside the Laravel DDEV project." >&2
  exit 1
fi

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

# Same host and port are allowed when the database names differ.
if [[ -n "$dest_db" && "$target_db" == "$dest_db" && "$target_host" == "$dest_host" && "$target_port" == "$dest_port" ]]; then
  echo "Refusing to grant source access on the Laravel database." >&2
  exit 1
fi

if [[ -n "$dest_db" && "$target_db" == "$dest_db" && -n "${LEGACY_STAGING_DDEV_PROJECT:-}" ]]; then
  source_root="$(ddev_approot "$LEGACY_STAGING_DDEV_PROJECT")"
  dest_root="$(ddev_approot "${LEGACY_LARAVEL_DDEV_PROJECT:-restaurant-backend}")"
  source_server="$(cd "$source_root" && ddev mysql -N -e "SELECT @@hostname" 2>/dev/null || true)"
  dest_server="$(cd "$dest_root" && ddev mysql -N -e "SELECT @@hostname" 2>/dev/null || true)"
  if [[ -n "$source_server" && "$source_server" == "$dest_server" ]]; then
    echo "Refusing to grant source access on the Laravel database." >&2
    exit 1
  fi
fi

option_file=""
cleanup() {
  if [[ -n "$option_file" && -f "$option_file" && "$option_file" != "${LEGACY_STAGING_OPTION_FILE:-}" ]]; then
    rm -f "$option_file"
  fi
}
trap cleanup EXIT

sql_password="${reader_password//\'/\'\'}"
sql="CREATE USER IF NOT EXISTS '${reader}'@'%' IDENTIFIED BY '${sql_password}'; ALTER USER '${reader}'@'%' IDENTIFIED BY '${sql_password}'; REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${reader}'@'%'; GRANT SELECT ON \`${target_db}\`.* TO '${reader}'@'%'; FLUSH PRIVILEGES;"

if [[ -n "${LEGACY_STAGING_DDEV_PROJECT:-}" ]]; then
  printf '%s\n' "$sql" | ( cd "$(ddev_approot "$LEGACY_STAGING_DDEV_PROJECT")" && ddev mysql )
else
  if [[ -n "${LEGACY_STAGING_OPTION_FILE:-}" ]]; then
    option_file="$LEGACY_STAGING_OPTION_FILE"
  else
    option_file="$(mktemp)"
    chmod 600 "$option_file"
    {
      printf '[client]\n'
      printf 'user="%s"\n' "${LEGACY_STAGING_DB_USERNAME:?Set LEGACY_STAGING_DB_USERNAME}"
      printf 'password="%s"\n' "${LEGACY_STAGING_DB_PASSWORD:?Set LEGACY_STAGING_DB_PASSWORD}"
      printf 'host="%s"\n' "$target_host"
      printf 'port=%s\n' "$target_port"
    } > "$option_file"
  fi
  printf '%s\n' "$sql" | mysql --defaults-extra-file="$option_file"
fi

echo "SELECT-only access was granted on ${target_db}. The password was not printed."
