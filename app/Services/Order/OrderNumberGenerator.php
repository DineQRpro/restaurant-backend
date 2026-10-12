<?php

namespace App\Services\Order;

use App\Models\DocumentSequence;
use App\Services\Document\DocumentNumberService;

class OrderNumberGenerator
{
    public function __construct(
        private readonly DocumentNumberService $sequences,
    ) {}

    public function generate(): string
    {
        return $this->sequences->allocate(DocumentSequence::ORDER);
    }
}
