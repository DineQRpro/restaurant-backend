<?php

namespace App\Services\LegacyMigration;

use RuntimeException;

class RowRejected extends RuntimeException
{
    public function __construct(
        public readonly string $outcome,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function failed(string $message): self
    {
        return new self('failed', $message);
    }

    public static function skipped(string $message): self
    {
        return new self('skipped', $message);
    }
}
