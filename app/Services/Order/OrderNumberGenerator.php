<?php

namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Support\Str;

class OrderNumberGenerator
{
    public function generate(): string
    {
        $date = now()->format('Ymd');

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = sprintf('ORD-%s-%04d', $date, random_int(1, 9999));

            if (! Order::query()->where('order_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('ORD-%s-%s', $date, Str::upper(Str::random(8)));
    }
}
