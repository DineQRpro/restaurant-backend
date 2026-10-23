<?php

namespace App\Services\LegacyMigration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MigrationPreflight
{
    /**
     * @var list<string>
     */
    public const SOURCE_TABLES = [
        'tbl_users',
        'tbl_category',
        'tbl_products',
        'tbl_tables',
        'tbl_discounts',
        'tbl_order',
        'tbl_order_items',
        'tbl_bill',
        'tbl_bill_payments',
        'tbl_receipts',
        'tbl_receipt_items',
    ];

    public function __construct(private readonly LegacySource $source) {}

    public function assertSeparated(): void
    {
        $destination = config('database.connections.'.config('database.default'));
        $legacy = config('database.connections.legacy');

        if (! is_array($destination) || ! is_array($legacy)) {
            throw new RuntimeException('Database connections are not configured.');
        }

        if ($this->endpoint($destination) === $this->endpoint($legacy)) {
            throw new RuntimeException('The legacy source and the Laravel database are the same database. Import was refused.');
        }

        if (($destination['driver'] ?? '') !== 'mysql' || ($legacy['driver'] ?? '') !== 'mysql') {
            return;
        }

        $sourceIdentity = $this->serverIdentity('legacy');
        $destinationIdentity = $this->serverIdentity(config('database.default'));

        if ($sourceIdentity === $destinationIdentity) {
            throw new RuntimeException('The legacy source and the Laravel destination resolve to the same database on the same server. Import was refused.');
        }
    }

    /**
     * @return list<string>
     */
    public function missingTables(): array
    {
        $missing = [];

        foreach (self::SOURCE_TABLES as $table) {
            if (! Schema::connection('legacy')->hasTable($table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    /**
     * @return array{host: string, database: string}
     */
    public function identity(): array
    {
        $legacy = config('database.connections.legacy');

        if (($legacy['driver'] ?? '') !== 'mysql') {
            return [
                'host' => (string) ($legacy['host'] ?? 'fixture'),
                'database' => (string) ($legacy['database'] ?? 'fixture'),
            ];
        }

        $row = $this->source->connection()->selectOne('SELECT @@hostname AS host, DATABASE() AS database_name');

        return [
            'host' => (string) ($row->host ?? ''),
            'database' => (string) ($row->database_name ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function endpoint(array $connection): string
    {
        $host = strtolower((string) ($connection['host'] ?? ''));

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $host = 'localhost';
        }

        return $host.'|'.(string) ($connection['port'] ?? '3306').'|'.strtolower((string) ($connection['database'] ?? ''));
    }

    private function serverIdentity(string $connection): string
    {
        $row = DB::connection($connection)->selectOne('SELECT @@hostname AS host, DATABASE() AS database_name');

        return strtolower((string) ($row->host ?? '')).'|'.strtolower((string) ($row->database_name ?? ''));
    }
}
