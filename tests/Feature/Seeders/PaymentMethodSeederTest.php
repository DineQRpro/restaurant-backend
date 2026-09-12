<?php

namespace Tests\Feature\Seeders;

use App\Models\PaymentMethod;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_payment_methods_are_seeded(): void
    {
        $this->seed(PaymentMethodSeeder::class);

        foreach ([
            PaymentMethod::SLUG_CASH,
            PaymentMethod::SLUG_UPI,
            PaymentMethod::SLUG_CARD,
            PaymentMethod::SLUG_SPLIT,
            PaymentMethod::SLUG_NON_CHARGEABLE,
        ] as $slug) {
            $this->assertDatabaseHas('payment_methods', [
                'slug' => $slug,
                'is_active' => true,
            ]);
        }

        $this->assertSame(5, PaymentMethod::query()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PaymentMethodSeeder::class);
        $countAfterFirst = PaymentMethod::query()->count();

        $this->seed(PaymentMethodSeeder::class);

        $this->assertSame($countAfterFirst, PaymentMethod::query()->count());
        $this->assertSame(5, $countAfterFirst);
    }
}
