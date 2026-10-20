<?php

namespace App\Services\LegacyMigration;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacySource
{
    public static bool $allowWrites = false;

    /** @var array<int, true> */
    private static array $guarded = [];

    public function connection(): Connection
    {
        $connection = DB::connection('legacy');
        $id = spl_object_id($connection);

        if (! isset(self::$guarded[$id])) {
            self::$guarded[$id] = true;
            $connection->beforeExecuting(function (string $query): void {
                if (self::$allowWrites || self::isReadQuery($query)) {
                    return;
                }

                throw new RuntimeException('The legacy connection accepts only read queries.');
            });
        }

        return $connection;
    }

    public static function isReadQuery(string $query): bool
    {
        return (bool) preg_match('/\A\s*(select|show|explain|describe|desc|with)\b/i', $query);
    }

    /**
     * @return list<object>
     */
    public function grants(): array
    {
        return $this->connection()->select('SHOW GRANTS FOR CURRENT_USER()');
    }

    /**
     * @return array{select_only: bool, summary: string}
     */
    public function privilegeSummary(): array
    {
        $select = false;
        $extra = [];

        foreach ($this->grants() as $grant) {
            $text = implode(' ', array_map('strval', (array) $grant));
            if (preg_match('/\bGRANT\s+USAGE\b/i', $text)) {
                continue;
            }

            if (preg_match('/\bGRANT\s+SELECT\s+ON\s+`?([^`\s]+)`?\.\*/i', $text, $matches) === 1) {
                $select = true;

                continue;
            }

            $extra[] = 'additional privilege';
        }

        return [
            'select_only' => $select && $extra === [],
            'summary' => $select && $extra === []
                ? 'SELECT on the source schema'
                : 'The source account is not limited to SELECT.',
        ];
    }
}
