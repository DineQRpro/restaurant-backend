<?php

namespace App\Services\LegacyMigration;

interface DestinationBackup
{
    public function backup(): string;
}
