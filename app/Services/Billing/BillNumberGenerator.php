<?php

namespace App\Services\Billing;

use App\Models\Bill;
use Illuminate\Support\Str;

class BillNumberGenerator
{
    public function generate(): string
    {
        $date = now()->format('Ymd');

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = sprintf('BILL-%s-%04d', $date, random_int(1, 9999));

            if (! Bill::query()->where('bill_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('BILL-%s-%s', $date, Str::upper(Str::random(8)));
    }
}
