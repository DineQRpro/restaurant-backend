<?php

namespace App\Services\Billing;

use App\Models\DocumentSequence;
use App\Services\Document\DocumentNumberService;

class BillNumberGenerator
{
    public function __construct(
        private readonly DocumentNumberService $sequences,
    ) {}

    public function generate(): string
    {
        return $this->sequences->allocate(DocumentSequence::BILL);
    }
}
