<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_user_cannot_lookup_customer(): void
    {
        $this->getJson('/api/v1/customers/lookup?phone=9876543210')->assertUnauthorized();
    }

    public function test_user_without_orders_create_cannot_lookup_customer(): void
    {
        $counter = $this->userWithRole('counter');

        Sanctum::actingAs($counter);

        $this->getJson('/api/v1/customers/lookup?phone=9876543210')->assertForbidden();
    }

    public function test_waiter_can_lookup_existing_customer(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Anita',
            'phone' => '9876543210',
        ]);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/customers/lookup?phone=9876543210')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'found' => true,
                    'customer' => [
                        'id' => $customer->id,
                        'name' => 'Anita',
                        'phone' => '9876543210',
                    ],
                ],
            ]);
    }

    public function test_lookup_of_unknown_phone_returns_found_false(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/customers/lookup?phone=9123456789')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'found' => false,
                    'customer' => null,
                ],
            ]);
    }

    public function test_customer_can_be_created(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/customers', [
            'name' => 'Ravi Kumar',
            'phone' => '9876501234',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ravi Kumar')
            ->assertJsonPath('data.phone', '9876501234');

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_same_phone_does_not_create_duplicate_customer(): void
    {
        $customer = Customer::factory()->inactive()->create([
            'name' => 'Old Name',
            'phone' => '9876501234',
        ]);

        Sanctum::actingAs($this->userWithRole('captain'));

        $this->postJson('/api/v1/customers', [
            'name' => 'New Name',
            'phone' => '9876501234',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_lookup_rejects_invalid_phone(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/customers/lookup?phone=12345')
            ->assertUnprocessable();
    }
}
