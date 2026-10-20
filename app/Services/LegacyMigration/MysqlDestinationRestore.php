<?php

namespace App\Services\LegacyMigration;

use RuntimeException;
use Symfony\Component\Process\Process;

class MysqlDestinationRestore implements DestinationRestore
{
    public function restore(string $path): void
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! is_array($connection) || ($connection['driver'] ?? '') !== 'mysql') {
            throw new RuntimeException('Restoring a destination backup requires the MySQL connection.');
        }

        if (! is_file($path)) {
            throw new RuntimeException('The destination backup file is missing.');
        }

        $sql = gzdecode((string) file_get_contents($path));

        if ($sql === false || $sql === '') {
            throw new RuntimeException('The destination backup could not be read.');
        }

        $optionFile = tempnam(sys_get_temp_dir(), 'legacy-restore-');
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

        try {
            $process = new Process([
                'mysql',
                '--defaults-extra-file='.$optionFile,
                (string) $connection['database'],
            ]);
            $process->setInput($sql);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('The destination backup could not be restored.');
            }
        } finally {
            @unlink($optionFile);
        }
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
