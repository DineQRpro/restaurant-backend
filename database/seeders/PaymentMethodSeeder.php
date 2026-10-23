<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentMethod::updateOrCreate(
            ['slug' => PaymentMethod::SLUG_CASH],
            [
                'name' => 'Cash',
                'description' => 'Cash payment',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        PaymentMethod::updateOrCreate(
            ['slug' => PaymentMethod::SLUG_UPI],
            [
                'name' => 'UPI',
                'description' => 'UPI payment',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        PaymentMethod::updateOrCreate(
            ['slug' => PaymentMethod::SLUG_CARD],
            [
                'name' => 'Card',
                'description' => 'Card payment',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        PaymentMethod::updateOrCreate(
            ['slug' => PaymentMethod::SLUG_SPLIT],
            [
                'name' => 'Split',
                'description' => 'Split across two payment methods',
                'sort_order' => 4,
                'is_active' => true,
            ]
        );

        PaymentMethod::updateOrCreate(
            ['slug' => PaymentMethod::SLUG_NON_CHARGEABLE],
            [
                'name' => 'Non-chargeable',
                'description' => 'Complimentary / non-chargeable settlement',
                'sort_order' => 5,
                'is_active' => true,
            ]
        );

        PaymentMethod::updateOrCreate(
            ['slug' => PaymentMethod::SLUG_CREDIT],
            [
                'name' => 'Credit',
                'description' => 'Historical credit tender. Not offered for new settlements.',
                'sort_order' => 6,
                'is_active' => false,
            ]
        );
    }
}
