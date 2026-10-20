<?php

namespace App\Services\LegacyMigration;

use RuntimeException;
use Symfony\Component\Process\Process;

class MysqldumpDestinationBackup implements DestinationBackup
{
    public function backup(): string
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! is_array($connection) || ($connection['driver'] ?? '') !== 'mysql') {
            throw new RuntimeException('A destination backup requires the MySQL connection.');
        }

        $directory = storage_path('app/db-backups');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The backup directory could not be created.');
        }

        $optionFile = tempnam(sys_get_temp_dir(), 'legacy-dump-');
        if ($optionFile === false) {
            throw new RuntimeException('A secure MySQL option file could not be created.');
        }

        chmod($optionFile, 0600);
        $handle = fopen($optionFile, 'wb');
        if ($handle === false) {
            throw new RuntimeException('A secure MySQL option file could not be opened.');
        }

        fwrite($handle, "[client]\n");
        fwrite($handle, 'user='.$this->quote((string) ($connection['username'] ?? ''))."\n");
        fwrite($handle, 'password='.$this->quote((string) ($connection['password'] ?? ''))."\n");
        fwrite($handle, 'host='.$this->quote((string) ($connection['host'] ?? ''))."\n");
        fwrite($handle, 'port='.(int) ($connection['port'] ?? 3306)."\n");
        fclose($handle);

        $path = $directory.'/pre-full-refresh-'.now()->format('Ymd-His').'.sql.gz';

        try {
            $dump = new Process([
                'mysqldump',
                '--defaults-extra-file='.$optionFile,
                '--single-transaction',
                '--skip-lock-tables',
                (string) $connection['database'],
            ]);
            $dump->setTimeout(300);
            $dump->run();

            if (! $dump->isSuccessful() || $dump->getOutput() === '') {
                throw new RuntimeException('The destination backup failed.');
            }

            $compressed = gzencode($dump->getOutput(), 9);
            if ($compressed === false || file_put_contents($path, $compressed) === false) {
                throw new RuntimeException('The destination backup could not be stored.');
            }

            chmod($path, 0600);

            return $path;
        } finally {
            @unlink($optionFile);
        }
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
