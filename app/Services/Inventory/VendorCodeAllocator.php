<?php

namespace App\Services\Inventory;

use App\Models\DocumentSequence;

class VendorCodeAllocator
{
    public function __construct(private readonly MasterCodeAllocator $codes) {}

    public function next(): string
    {
        return $this->codes->next(DocumentSequence::VENDOR, 'VEN');
    }
}
