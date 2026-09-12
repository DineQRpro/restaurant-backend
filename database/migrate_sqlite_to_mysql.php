<?php

declare(strict_types=1);

echo "============================================\n";
echo " SQLite → MariaDB Data Migration\n";
echo "============================================\n\n";

$sqlitePath = __DIR__ . '/database.sqlite';

if (!file_exists($sqlitePath)) {
    die("ERROR: SQLite database not found: {$sqlitePath}\n");
}

/*
|--------------------------------------------------------------------------
| SQLite connection
|--------------------------------------------------------------------------
*/
$sqlite = new PDO(
    'sqlite:' . $sqlitePath,
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

/*
|--------------------------------------------------------------------------
| MariaDB connection
|--------------------------------------------------------------------------
*/
$mysql = new PDO(
    'mysql:host=db;port=3306;dbname=db;charset=utf8mb4',
    'db',
    'db',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

/*
|--------------------------------------------------------------------------
| Tables to migrate
|--------------------------------------------------------------------------
|
| migrations is intentionally excluded.
|
| Laravel's MySQL migrations table already contains the correct
| migration history and must remain untouched.
|
*/
$tables = [
    'cache',
    'cache_locks',
    'failed_jobs',
    'job_batches',
    'jobs',
    'password_reset_tokens',
    'roles',
    'permissions',
    'users',
    'role_permissions',
    'personal_access_tokens',
    'sessions',
    'menu_categories',
    'menu_items',
    'tables',
];

/*
|--------------------------------------------------------------------------
| Disable FK checks
|--------------------------------------------------------------------------
*/
$mysql->exec('SET FOREIGN_KEY_CHECKS = 0');

try {

    foreach ($tables as $table) {

        echo "Processing: {$table}\n";

        /*
        |--------------------------------------------------------------------------
        | Read SQLite data
        |--------------------------------------------------------------------------
        */
        $stmt = $sqlite->query(
            'SELECT * FROM "' . str_replace('"', '""', $table) . '"'
        );

        $rows = $stmt->fetchAll();

        echo "  SQLite rows: " . count($rows) . "\n";

        /*
        |--------------------------------------------------------------------------
        | Clear target table
        |--------------------------------------------------------------------------
        */
        $mysql->exec(
            'DELETE FROM `' . str_replace('`', '``', $table) . '`'
        );

        if (count($rows) === 0) {
            echo "  MySQL rows: 0\n";
            echo "  ✓ Done\n\n";
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Build INSERT query dynamically
        |--------------------------------------------------------------------------
        */
        $columns = array_keys($rows[0]);

        $quotedColumns = array_map(
            fn($column) => '`' . str_replace('`', '``', $column) . '`',
            $columns
        );

        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            str_replace('`', '``', $table),
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        );

        $insert = $mysql->prepare($sql);

        /*
        |--------------------------------------------------------------------------
        | Insert rows
        |--------------------------------------------------------------------------
        */
        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = $row[$column];
            }

            $insert->execute($values);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify target count
        |--------------------------------------------------------------------------
        */
        $mysqlCount = (int) $mysql
            ->query(
                'SELECT COUNT(*) FROM `' .
                str_replace('`', '``', $table) .
                '`'
            )
            ->fetchColumn();

        echo "  MySQL rows: {$mysqlCount}\n";

        if ($mysqlCount !== count($rows)) {
            throw new RuntimeException(
                "Row count mismatch for {$table}. " .
                "SQLite: " . count($rows) .
                ", MySQL: {$mysqlCount}"
            );
        }

        echo "  ✓ Done\n\n";
    }

    /*
    |--------------------------------------------------------------------------
    | Restore FK checks
    |--------------------------------------------------------------------------
    */
    $mysql->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "============================================\n";
    echo " Migration completed successfully.\n";
    echo "============================================\n";

} catch (Throwable $e) {

    $mysql->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "\n============================================\n";
    echo " MIGRATION FAILED\n";
    echo "============================================\n";
    echo $e->getMessage() . "\n";

    exit(1);
}
