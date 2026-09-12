<?php

namespace Tests\Feature\Counter;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Models\BillPayment;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Table;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRestaurantOrders;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class CounterDashboardTest extends TestCase
{
    use CreatesRestaurantOrders, InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_table_board_renders_operational_statuses(): void
    {
        Table::factory()->create([
            'number' => 'T1',
            'capacity' => 4,
            'status' => TableOperationalStatus::Available,
        ]);
        Table::factory()->occupied()->create(['number' => 'T2']);
        Table::factory()->cleaning()->create(['number' => 'T3']);
        Table::factory()->inactive()->create(['number' => 'T99']);

        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('Table T1')
            ->assertSee('Table T2')
            ->assertSee('Table T3')
            ->assertDontSee('Table T99')
            ->assertSee('Available')
            ->assertSee('Occupied')
            ->assertSee('Cleaning')
            ->assertSee('admin-table-status-available', false)
            ->assertSee('admin-table-status-occupied', false)
            ->assertSee('admin-table-status-cleaning', false);
    }

    public function test_track_orders_headers_appear_in_exact_order(): void
    {
        $html = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertSame(
            ['Order#', 'Table#', 'Type', 'KOT', 'View Bill', 'Date', 'Status', 'Payment Mode', 'Status'],
            $this->trackOrderHeaders($html),
        );
    }

    public function test_operational_track_orders_render_with_disabled_kot_and_bill(): void
    {
        $this->createPlacedOrder([
            'order_number' => 'ORD-C1001',
            'customer' => Customer::factory()->create(['name' => 'Arun']),
            'table' => Table::factory()->occupied()->create(['number' => 'T1']),
        ]);

        $html = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('ORD-C1001')
            ->assertSee('T1')
            ->assertSee('Dine In')
            ->assertSee('Active')
            ->assertSee('Pending')
            ->getContent();

        $this->assertStringContainsString('Coming soon', $html);
        $this->assertStringContainsString('aria-label="KOT coming soon"', $html);
        $this->assertStringContainsString('aria-label="View Bill coming soon"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString('data-action=', $html);
        $this->assertStringNotContainsString('admin.bills.payments.store', $html);
    }

    public function test_dine_type_and_order_status_render(): void
    {
        $candle = DineType::factory()->create([
            'name' => 'Candle Light Dinner',
            'slug' => 'candle-light-dinner',
        ]);

        $this->createCheckoutBill([
            'order_number' => 'ORD-CANDLE',
            'dineType' => $candle,
            'table' => Table::factory()->cleaning()->create(['number' => 'T5']),
        ]);

        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('ORD-CANDLE')
            ->assertSee('Candle Light Dinner')
            ->assertSee('Checkout')
            ->assertSee('Unpaid');
    }

    public function test_checkout_unpaid_row_renders_seeded_payment_selector(): void
    {
        $this->seed(PaymentMethodSeeder::class);
        $this->createPlacedOrder([
            'order_number' => 'ORD-20260910-2398',
            'table' => Table::factory()->occupied()->create(['number' => '3']),
        ]);
        $this->createCheckoutBill([
            'order_number' => 'ORD-20260909-7953',
            'table' => Table::factory()->cleaning()->create(['number' => '2']),
        ]);

        $html = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('Choose Method')
            ->assertSee('💵 Cash')
            ->assertSee('📱 UPI')
            ->assertSee('💳 Card')
            ->assertSee('🔀 Split')
            ->assertSee('Non-chargeable')
            ->getContent();

        $checkoutRow = $this->trackOrderRow($html, 'ORD-20260909-7953');
        $this->assertNotSame('', $checkoutRow);
        $this->assertStringContainsString('class="payment-mode-select"', $checkoutRow);
        $this->assertStringContainsString('Choose Method', $checkoutRow);
        $this->assertStringContainsString('data-bill-id="', $checkoutRow);
        $this->assertStringContainsString('data-action=', $checkoutRow);
        $this->assertStringContainsString('data-split-action=', $checkoutRow);
        $this->assertStringContainsString('data-order-number="ORD-20260909-7953"', $checkoutRow);
        $this->assertStringContainsString('data-table-number="2"', $checkoutRow);
        $this->assertStringContainsString('data-grand-total=', $checkoutRow);
        $this->assertStringContainsString('data-paid-amount=', $checkoutRow);
        $this->assertStringContainsString('data-balance=', $checkoutRow);
        $this->assertStringContainsString('data-slug="cash"', $checkoutRow);
        $this->assertStringContainsString('data-slug="upi"', $checkoutRow);
        $this->assertStringContainsString('data-slug="card"', $checkoutRow);
        $this->assertStringContainsString('data-slug="split"', $checkoutRow);
        $this->assertStringContainsString('data-slug="non-chargeable"', $checkoutRow);
        $this->assertStringContainsString('/counter/bills/', $checkoutRow);
        $this->assertStringNotContainsString('class="payment-pending-text"', $checkoutRow);

        $this->assertStringContainsString('id="counter-payment-modal"', $html);
        $this->assertStringContainsString('id="counter-split-modal"', $html);
        $this->assertStringContainsString('id="counter-payment-form"', $html);
        $this->assertStringContainsString('id="counter-split-form"', $html);
        $this->assertStringContainsString("closest('.payment-mode-select')", $html);
        $this->assertStringContainsString("style.display = 'flex'", $html);
        $this->assertStringContainsString('name="_token"', $html);

        $activeRow = $this->trackOrderRow($html, 'ORD-20260910-2398');
        $this->assertNotSame('', $activeRow);
        $this->assertStringContainsString('class="payment-pending-text"', $activeRow);
        $this->assertStringNotContainsString('class="payment-mode-select"', $activeRow);
    }

    public function test_active_payment_methods_render_with_database_ids(): void
    {
        $cash = PaymentMethod::factory()->create(['name' => 'Cash Desk', 'slug' => 'cash-desk', 'sort_order' => 1]);
        $upi = PaymentMethod::factory()->create(['name' => 'UPI Desk', 'slug' => 'upi-desk', 'sort_order' => 2]);
        PaymentMethod::factory()->inactive()->create(['name' => 'Old Cheque', 'slug' => 'old-cheque']);

        $this->createCheckoutBill(['order_number' => 'ORD-PAY']);

        $html = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('Choose Method')
            ->assertSee('Cash Desk')
            ->assertSee('UPI Desk')
            ->assertDontSee('Old Cheque')
            ->getContent();

        $this->assertStringContainsString('value="'.$cash->id.'"', $html);
        $this->assertStringContainsString('value="'.$upi->id.'"', $html);
        $this->assertSame(1, substr_count($html, 'class="payment-mode-select"'));
        $this->assertStringContainsString('>Cash Desk</option>', $html);
        $this->assertStringContainsString('data-action=', $html);
        $this->assertStringContainsString('/counter/bills/', $html);
        $this->assertStringContainsString('/payments', $html);
        preg_match('/<select[^>]*class="payment-mode-select"[^>]*>/', $html, $selectTag);
        $this->assertNotSame('', $selectTag[0] ?? '');
        $this->assertStringNotContainsString('data-display-only', $selectTag[0]);
        $this->assertStringNotContainsString('admin.bills.payments.store', $html);
    }

    public function test_selecting_payment_mode_does_not_persist_or_settle(): void
    {
        $method = PaymentMethod::factory()->create();
        [$order, $bill] = $this->createCheckoutBill([
            'order_number' => 'ORD-NOPAY',
        ], [
            'grand_total' => 800,
        ]);

        $this->assertDatabaseCount('bill_payments', 0);

        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('ORD-NOPAY')
            ->assertSee('Choose Method');

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
        $this->assertNull($bill->fresh()->settled_at);
        $this->assertSame(OrderStatus::Checkout, $order->fresh()->status);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $method->id,
                'amount' => 800,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
        $this->assertSame(OrderStatus::Checkout, $order->fresh()->status);
    }

    public function test_payment_select_is_hidden_without_settle_permission(): void
    {
        PaymentMethod::factory()->create();
        $this->createCheckoutBill(['order_number' => 'ORD-NOPAY']);

        $counter = $this->userWithRole('counter');
        $permission = Permission::query()->where('slug', 'bills.settle')->firstOrFail();
        $counter->role->permissions()->detach($permission->id);

        $html = $this->actingAs($counter->fresh())
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('ORD-NOPAY')
            ->assertDontSee('class="payment-mode-select"', false)
            ->getContent();

        $row = $this->trackOrderRow($html, 'ORD-NOPAY');
        $this->assertStringContainsString('class="payment-pending-text"', $row);
    }

    public function test_settled_bill_shows_paid_without_editable_select(): void
    {
        $method = PaymentMethod::factory()->create(['name' => 'Card', 'slug' => 'card']);
        [, $bill] = $this->createCheckoutBill([
            'order_number' => 'ORD-PAID',
        ], [
            'grand_total' => 650,
            'status' => BillStatus::Settled,
            'settled_at' => now(),
        ]);
        $this->recordPayment($bill, $method, 650);

        $html = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('ORD-PAID')
            ->assertSee('Card')
            ->assertSee('Paid')
            ->getContent();

        $this->assertStringNotContainsString('class="payment-mode-select"', $html);
        $this->assertSame(1, BillPayment::query()->count());
    }

    public function test_amount_received_uses_actual_payments(): void
    {
        $method = PaymentMethod::factory()->create();
        [, $todayBill] = $this->createCheckoutBill([], ['grand_total' => 1500]);
        $this->recordPayment($todayBill, $method, 200);

        $html = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/text-2xl font-bold text-\[#04346a\]">\s*Rs\. 200\.00\s*<\/p>\s*<p class="mt-1 text-sm font-semibold text-\[#1f2f3e\]">Total Amount Received<\/p>/',
            $html,
        );
    }

    /**
     * @return list<string>
     */
    private function trackOrderHeaders(string $html): array
    {
        preg_match('/<table class="orders-table.*?<\/thead>/s', $html, $table);
        preg_match_all('/<th>(.*?)<\/th>/', $table[0] ?? '', $headers);

        return $headers[1];
    }

    private function trackOrderRow(string $html, string $orderNumber): string
    {
        preg_match(
            '/<tr class="order-row" data-order-number="'.preg_quote($orderNumber, '/').'".*?<\/tr>/s',
            $html,
            $row,
        );

        return $row[0] ?? '';
    }
}
