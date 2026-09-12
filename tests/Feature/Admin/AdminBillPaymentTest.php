<?php

namespace Tests\Feature\Admin;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Models\BillPayment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesRestaurantOrders;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class AdminBillPaymentTest extends TestCase
{
    use CreatesRestaurantOrders, InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_user_cannot_record_payment(): void
    {
        [, $bill] = $this->createCheckoutBill();
        $method = PaymentMethod::factory()->create();

        $this->post(route('admin.bills.payments.store', $bill), [
            'payment_method_id' => $method->id,
            'amount' => 100,
        ])->assertRedirect('/admin/login');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_user_without_required_permission_receives_403(): void
    {
        [, $bill] = $this->createCheckoutBill();
        $method = PaymentMethod::factory()->create();

        $admin = $this->userWithRole('admin');
        $permission = Permission::query()->where('slug', 'bills.settle')->firstOrFail();
        $admin->role->permissions()->detach($permission->id);

        $this->actingAs($admin->fresh())
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 100,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_active_payment_method_is_accepted(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 1500]);
        $method = PaymentMethod::factory()->create(['name' => 'UPI Desk', 'slug' => 'upi-desk']);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 500,
                'reference_number' => 'UPI-1',
                'notes' => 'Partial at counter',
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'payment_method_id' => $method->id,
            'amount' => 500,
            'reference_number' => 'UPI-1',
            'captain_id' => null,
        ]);
    }

    public function test_inactive_payment_method_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill();
        $method = PaymentMethod::factory()->inactive()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.dashboard'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 100,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('payment_method_id');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_invalid_payment_method_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill();

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.dashboard'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => 9999,
                'amount' => 100,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('payment_method_id');
    }

    public function test_partial_payment_succeeds_without_settling_the_bill(): void
    {
        [$order, $bill] = $this->createCheckoutBill([], ['grand_total' => 1500]);
        $method = PaymentMethod::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 500,
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseCount('bill_payments', 1);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
        $this->assertNull($bill->fresh()->settled_at);
        $this->assertSame(1000.0, app(PaymentService::class)->balance($bill->fresh()));
        $this->assertSame(OrderStatus::Checkout, $order->fresh()->status);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
    }

    public function test_final_payment_marks_bill_settled(): void
    {
        [$order, $bill] = $this->createCheckoutBill([], ['grand_total' => 1500]);
        $method = PaymentMethod::factory()->create();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 500,
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 1000,
            ])
            ->assertRedirect(route('admin.dashboard'));

        $bill->refresh();

        $this->assertDatabaseCount('bill_payments', 2);
        $this->assertSame(BillStatus::Settled, $bill->status);
        $this->assertNotNull($bill->settled_at);
        $this->assertSame(0.0, app(PaymentService::class)->balance($bill));
        $this->assertSame(OrderStatus::Checkout, $order->fresh()->status);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);

        $html = $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Paid')
            ->getContent();

        $this->assertStringNotContainsString('class="payment-mode-select"', $html);
    }

    public function test_overpayment_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 200]);
        $method = PaymentMethod::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 201,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
    }

    public function test_zero_payment_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill();
        $method = PaymentMethod::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.dashboard'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 0,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('amount');
    }

    public function test_negative_payment_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill();
        $method = PaymentMethod::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.dashboard'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => -10,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('amount');
    }

    public function test_failed_payment_transaction_rolls_back(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 100]);
        $method = PaymentMethod::factory()->create();

        $dispatcher = BillPayment::getEventDispatcher();
        BillPayment::flushEventListeners();
        BillPayment::created(function (): void {
            throw new RuntimeException('payment failed after insert');
        });

        try {
            app(PaymentService::class)->record($bill, [
                'payment_method_id' => $method->id,
                'amount' => 100,
            ], $this->userWithRole('admin'));
            $this->fail('Payment should have failed.');
        } catch (RuntimeException) {
            // expected
        } finally {
            BillPayment::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
        $this->assertNull($bill->fresh()->settled_at);
    }

    public function test_payment_does_not_change_table_or_order_state(): void
    {
        [$order, $bill] = $this->createCheckoutBill([], ['grand_total' => 80]);
        $method = PaymentMethod::factory()->create();

        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->status);
        $this->assertSame(OrderStatus::Checkout, $order->status);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 80,
            ])
            ->assertRedirect(route('admin.dashboard'));

        $order->refresh();
        $order->table->refresh();
        $bill->refresh();

        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->status);
        $this->assertSame(OrderStatus::Checkout, $order->status);
        $this->assertSame(BillStatus::Settled, $bill->status);
        $this->assertNull($order->completed_at);
    }

    public function test_admin_payment_leaves_captain_id_null(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 50]);
        $method = PaymentMethod::factory()->create();

        app(PaymentService::class)->record($bill, [
            'payment_method_id' => $method->id,
            'amount' => 50,
        ], $this->userWithRole('admin'));

        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'captain_id' => null,
        ]);
    }

    public function test_captain_actor_stores_captain_id(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 50]);
        $method = PaymentMethod::factory()->create();
        $captain = $this->userWithRole('captain');

        app(PaymentService::class)->record($bill, [
            'payment_method_id' => $method->id,
            'amount' => 50,
        ], $captain);

        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'captain_id' => $captain->id,
        ]);
    }
}
