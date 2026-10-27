# Repeatable full refresh

One full refresh replaces the Laravel business dataset from the restored Varsity snapshot. Phase 1 masters and Phase 2 customers, orders, items, bills, payments, and banquet receipts run in that same operation. It does not synchronize changes incrementally, and it never writes to the Varsity production database. A second refresh clears the previous import before loading the new snapshot. It does not append duplicates.

The server that runs the refresh needs PHP, Composer, and MySQL or MariaDB. It does not need Cursor or DDEV. DDEV commands below are only for this local workspace.

Apply outstanding Laravel migrations, including `orders.historical_customer_name`, before the first refresh. The refresh does not add columns. It only replaces business rows.

The commands are:

```bash
php artisan legacy:migration:analyze
php artisan legacy:migration:full-refresh --dry-run
php artisan legacy:migration:full-refresh --approve-reset
php artisan legacy:migration:status
php artisan legacy:migration:admin owner --name="Owner" --email="owner@example.com"
```

In production, the reset also requires `--approve-production`.

`legacy:migration:dry-run` and `legacy:migration:run` remain available. The repeatable operation is `legacy:migration:full-refresh`.

## Databases

`DB_*` is the Laravel destination.

`LEGACY_DB_*` is the read-only connection to the restored staging database. Its default name is `varsity_source`. The two databases may be on the same MySQL server and may use the same host and port. They must not be the same database. The check compares the database name, and for MySQL it also compares `@@hostname` with `DATABASE()`, so two connection names or host aliases that reach the same database are still refused.

`LEGACY_STAGING_DB_*` or `LEGACY_STAGING_OPTION_FILE` is the privileged connection used only by the shell scripts. For DDEV, set `LEGACY_STAGING_DDEV_PROJECT` to the Varsity project, not the Laravel project.

## Source restore

```bash
./scripts/legacy/restore-source.sh /absolute/path/varsity-latest.sql.gz
```

The script accepts `.sql` and `.sql.gz`. It checks that the file exists, is non-empty, and is a readable dump. Gzip archives are tested with `gzip -t`. Plain SQL must contain a `CREATE TABLE` statement.

Restoration goes into `varsity_source_incoming` first. Required legacy tables are checked there. If that check fails, the incoming database is dropped and the approved `varsity_source` database is left as it was.

After the incoming copy verifies:

1. The previous approved copy is moved into `varsity_source_previous`.
2. The incoming tables are moved into `varsity_source` in one rename.
3. The empty incoming database is dropped.
4. The required tables are checked again on `varsity_source`.

A failed restore therefore cannot become the database the migrator reads. The previous staging copy remains available in `varsity_source_previous` for manual recovery. Replacing an existing staging database requires typing `replace-source`, or setting `LEGACY_RESTORE_CONFIRM=replace-source` for a non-interactive shell.

The script refuses to run when the target project is the Laravel DDEV project, or when the target is the same database as `DB_DATABASE` on the same server. A different database name on that server is accepted. Export `DB_HOST`, `DB_PORT`, and `DB_DATABASE` before running the script on a Linux server; the script does not read `.env` by itself.

## SELECT-only account

Create the reader once on the staging server:

```bash
export LEGACY_STAGING_DB_USERNAME=...
export LEGACY_STAGING_DB_PASSWORD=...
export LEGACY_DB_USERNAME=legacy_reader
export LEGACY_DB_PASSWORD=...
./scripts/legacy/create-reader.sh
```

The script grants `SELECT` on the staging schema and revokes other privileges. It does not print the password. Store `LEGACY_DB_PASSWORD` in the Laravel environment or a protected option file. Do not put it in Artisan arguments.

## What a full refresh does

`--dry-run` validates the source and reports valid, skipped, and rejected rows. It does not clear or import business data. Collision checks assume the destination business tables will be empty.

`--approve-reset` then:

1. Runs the same validation.
2. Writes a gzip backup of the Laravel database to `storage/app/db-backups`, mode `0600`.
3. Turns on maintenance mode.
4. Clears business tables, including orders, bills, customers, users, menu data, discounts, floors, sessions, and access tokens.
5. Resets their auto-increment counters and document sequence numbers.
6. Keeps roles, permissions, payment methods, dine types, menu types, and migration history.
7. Recreates Ground Floor as id 1, code `ground-floor`.
8. Imports Phase 1 master data.
9. Imports Phase 2 customers and transactions in dependency order and rebuilds source-to-destination ID maps.
10. Moves the order and bill document sequences past the largest historical numbers.
11. Verifies counts, menu types, floor assignment, foreign keys, inactive discounts, inactive credit, and that no historical order stays `placed`.
12. Turns maintenance mode off only after that validation. The summary includes `transactions.preserved_bill_total`, `transactions.legacy_net_sales`, `transactions.credit_rows`, `transactions.credit_total`, and `transactions.payment_gaps_over_one_rupee`.

If the import or verification fails after the destination has been cleared, the command restores that backup and turns maintenance mode off. If the restore itself fails, maintenance mode stays on and the backup filename is reported. The password is not included in the error.

Hot Beverages stays empty. Legacy categories and products belong to Food Menu. Legacy Admin imports as Admin. Legacy Kitchen, Counter, and Staff import as Waiter. Store users are skipped. Unknown roles are rejected. Invalid emails and mobile numbers are replaced or cleared and imported with a warning. Payment methods are reused.

Local and testing imports hash the configured development password and require a password change. Production imports give every user a random password, never the shared development password. Users with an email address receive a one-time reset token in `storage/app/legacy-migration/run-<id>-activations.txt`, mode `0600`. The database stores only the hashed token. Laravel's password broker expires it after 60 minutes. Distribute those tokens privately and delete the file. Users without an email address must be recovered by an administrator.

`--dry-run` does not change business tables or the source database. It does write a migration run, mapping rows, and rejection rows so the report can be reviewed.

## Commands

### Local DDEV

Run these from the Laravel project. The staging database lives in the Varsity DDEV project, not in the Laravel database. DDEV 1.25 selects that project by its directory; the scripts do that from `LEGACY_STAGING_DDEV_PROJECT`.

```bash
export LEGACY_STAGING_DDEV_PROJECT=VarsityKitchen
export LEGACY_STAGING_DB_DATABASE=varsity_source
export LEGACY_RESTORE_CONFIRM=replace-source
./scripts/legacy/restore-source.sh /absolute/path/varsity-latest.sql.gz

export LEGACY_DB_USERNAME=legacy_reader
export LEGACY_DB_PASSWORD='set-a-reader-password'
./scripts/legacy/create-reader.sh
```

Put the reader in the Laravel `.env`. Use the Varsity database container, not the Laravel `db` hostname:

```bash
LEGACY_DB_HOST=ddev-VarsityKitchen-db
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=varsity_source
LEGACY_DB_USERNAME=legacy_reader
LEGACY_DB_PASSWORD=
```

Then:

```bash
ddev exec php artisan legacy:migration:analyze
ddev exec php artisan legacy:migration:full-refresh --dry-run
ddev exec php artisan legacy:migration:full-refresh --approve-reset
ddev exec php artisan legacy:migration:status
```

Recover the local admin only after a refresh, with the password in the environment:

```bash
ddev exec env LEGACY_ADMIN_PASSWORD="$LEGACY_ADMIN_PASSWORD" php artisan legacy:migration:admin admin --name="Admin" --email="admin@restaurant.test"
```

Set `LEGACY_ADMIN_PASSWORD` in the shell before that command. Do not pass it as an Artisan argument. Local imported staff use the configured development password and must change it at first login.

### Live Linux server

Run these from the Laravel application directory over SSH. Export the destination identity first so the restore script can reject an identical database. Do not point `LEGACY_STAGING_DB_*` at the original Varsity production server.

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE=the_laravel_database_name
export LEGACY_STAGING_DB_HOST=127.0.0.1
export LEGACY_STAGING_DB_PORT=3306
export LEGACY_STAGING_DB_DATABASE=varsity_source
export LEGACY_STAGING_DB_USERNAME='staging-admin'
export LEGACY_STAGING_DB_PASSWORD='set-a-staging-password'
export LEGACY_RESTORE_CONFIRM=replace-source
./scripts/legacy/restore-source.sh /absolute/path/varsity-latest.sql.gz

export LEGACY_DB_USERNAME=legacy_reader
export LEGACY_DB_PASSWORD='set-a-reader-password'
./scripts/legacy/create-reader.sh
```

Set the same reader values in `.env` as `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE=varsity_source`, `LEGACY_DB_USERNAME`, and `LEGACY_DB_PASSWORD`. Then:

```bash
php artisan legacy:migration:analyze
php artisan legacy:migration:full-refresh --dry-run
php artisan legacy:migration:full-refresh --approve-reset --approve-production
php artisan legacy:migration:status
LEGACY_ADMIN_PASSWORD='a-unique-password' php artisan legacy:migration:admin owner --name="Owner" --email="owner@example.com"
```

Production requires both approval flags. The admin password must be at least 12 characters and is not printed.

## Production admin recovery

```bash
LEGACY_ADMIN_PASSWORD='a-unique-password' php artisan legacy:migration:admin owner --name="Owner" --email="owner@example.com"
```

The password is read from `LEGACY_ADMIN_PASSWORD` or from an interactive prompt. It must be at least 12 characters. The command does not print it. Running it again for the same username recovers that admin account.

## Phase 2 on the same refresh

Phase 2 does not have its own reset. After Phase 1 tables, categories, products, discounts, and users are mapped, the transaction migrator:

1. Skips occupation rows that no order references. It does not restore occupancy flags.
2. Creates one customer per valid 10-digit phone, in phone order. Names do not create extra customers. Six orders with no valid phone keep a null customer. The typed name is stored on `orders.historical_customer_name`.
3. Imports historical orders as `checkout`. Unfinished orders and method-7 cancel orders also get `cancelled_at`. Dine-in orders get `seating_cleaned_at` from the historical update time, so they do not occupy a table or open a kitchen ticket.
4. Uses the inactive `legacy-unassigned` waiter when the source waiter cannot be imported. That account cannot sign in.
5. Imports cancelled order items and leaves them off derived bill lines.
6. Preserves `bill_gd_total`. It does not invent payments. Credit stays Credit and stays inactive for new counter settlements. Cancel rows are not collected payments.
7. Imports the two banquet receipts and their items. It does not create receipts from bills.
8. Records `TEST_SEED` bills, their orders, items, and payments, plus orphan bills and orphan items, as skipped rows.

For the snapshot reviewed on 2026-10-24, a read-only check of `varsity_source` matched 1,317 orders, 6,459 items, 1,308 bills, preserved total 1,619,387.53, legacy-style net sales 1,583,750.98, 22 credit rows totaling 15,639.00, 169 payment gaps over one rupee, and 2 receipts. Five of the 169 gaps are orphan bills that are excluded. The remaining gaps are imported with the stored bill total, including the 73.83 shortfall on bill 1394. A newer dump will produce different totals. Compare the refresh summary with a fresh read-only count instead of expecting these figures.

Historical reports keep cancelled bills out of net sales and keep Credit out of Cash. Open historical bills can still appear on Track Orders. They do not occupy a table. Customer reports omit the six orders that have no customer.

## Repeat with a newer Varsity backup

1. Copy the new dump to the Laravel server. Do not restore it into the Laravel database or into the original Varsity production database.
2. Restore it into `varsity_source` with `restore-source.sh`, as shown above. The script now also requires `tbl_order`, `tbl_order_items`, `tbl_bill`, `tbl_bill_payments`, `tbl_receipts`, and `tbl_receipt_items`.
3. Run `php artisan legacy:migration:analyze`.
4. Run `php artisan legacy:migration:full-refresh --dry-run` and review skipped rows, failed rows, preserved totals, net sales, credit totals, and payment gaps.
5. Run the approved refresh. Local DDEV uses `--approve-reset`. Production uses `--approve-reset --approve-production`.
6. Run `php artisan legacy:migration:status`.
7. Recover the admin account with `legacy:migration:admin`.

If validation fails after the clear, the command restores the gzip backup and turns maintenance mode off. If that restore fails, maintenance mode stays on and the backup path is reported. Do not continue serving the application from a half-imported database.

## Recovery

The backup file is `storage/app/db-backups/`, mode `0600`. Keep it until the imported counts and financial summary have been checked. To recover manually, turn maintenance mode on, restore that gzip with the same database user that owns the Laravel database, then turn maintenance mode off only after the restored row counts match the backup. The refresh command already does this when its own verification fails.
