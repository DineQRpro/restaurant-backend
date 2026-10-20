<?php

namespace App\Services\LegacyMigration;

interface DestinationRestore
{
    public function restore(string $path): void;
}
