<?php

namespace Tests\Feature\Admin;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRestaurantOrders;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class AdminTrackOrdersTest extends TestCase
{
    use CreatesRestaurantOrders, InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_track_orders_headers_appear_in_exact_order(): void
    {
        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Order#')
            ->assertSee('Table#')
            ->assertSee('Type')
            ->assertSee('KOT')
            ->assertSee('View Bill')
            ->assertSee('Date')
            ->assertSee('Payment Mode')
            ->getContent();

        $this->assertSame(
            ['Order#', 'Table#', 'Type', 'KOT', 'View Bill', 'Date', 'Status', 'Payment Mode', 'Status'],
            $this->trackOrderHeaders($html),
        );
    }

    public function test_current_active_order_appears_with_pending_payment(): void
    {
        $table = Table::factory()->occupied()->create(['number' => 'T1']);
        $order = $this->createPlacedOrder([
            'order_number' => 'ORD-1001',
            'customer' => Customer::factory()->create(['name' => 'Arun']),
            'table' => $table,
        ]);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('ORD-1001')
            ->assertSee('T1')
            ->assertSee('Dine In')
            ->assertSee('Active')
            ->assertSee('Pending')
            ->assertSee($order->created_at->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A'))
            ->getContent();

        $this->assertStringContainsString('Coming soon', $html);
        $this->assertStringContainsString('aria-label="KOT coming soon"', $html);
        $this->assertStringContainsString('aria-label="View Bill coming soon"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString('class="payment-mode-select"', $html);
        $this->assertStringNotContainsString('Edit KOT', $html);
        $this->assertStringNotContainsString('admin.kot', $html);
        $this->assertStringNotContainsString('admin.bills.show', $html);
    }

    public function test_checked_out_unpaid_bill_shows_payment_select(): void
    {
        PaymentMethod::factory()->create(['name' => 'Cash Desk', 'slug' => 'cash-desk']);
        PaymentMethod::factory()->inactive()->create(['name' => 'Old Cheque', 'slug' => 'old-cheque']);
        $table = Table::factory()->cleaning()->create(['number' => 'T4']);
        [$order] = $this->createCheckoutBill([
            'order_number' => 'ORD-1002',
            'customer' => Customer::factory()->create(['name' => 'Manoj']),
            'table' => $table,
        ], [
            'grand_total' => 980,
        ]);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('ORD-1002')
            ->assertSee('T4')
            ->assertSee('Checkout')
            ->assertSee('Unpaid')
            ->assertSee('Select Payment')
            ->assertSee('Cash Desk')
            ->assertDontSee('Old Cheque')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="payment-mode-select"'));
        $this->assertSame(OrderStatus::Checkout, $order->status);
    }

    public function test_old_fully_settled_historical_order_is_excluded(): void
    {
        $this->createCheckoutBill([
            'order_number' => 'ORD-OLD',
        ], [
            'bill_number' => 'BILL-OLD',
            'grand_total' => 400,
            'status' => BillStatus::Settled,
            'settled_at' => now()->subDay(),
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('ORD-OLD')
            ->assertDontSee('BILL-OLD');
    }

    public function test_type_shows_actual_dine_type(): void
    {
        $candle = DineType::factory()->create([
            'name' => 'Candle Light Dinner',
            'slug' => 'candle-light-dinner',
        ]);

        $this->createPlacedOrder([
            'order_number' => 'ORD-CANDLE',
            'dineType' => $candle,
            'table' => Table::factory()->occupied()->create(['number' => 'T8']),
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('ORD-CANDLE')
            ->assertSee('Candle Light Dinner');
    }

    public function test_takeaway_order_shows_take_away_table_label(): void
    {
        $takeAway = DineType::query()->firstOrCreate(
            ['slug' => 'take-away'],
            ['name' => 'Take Away', 'sort_order' => 2, 'is_active' => true],
        );

        $this->createPlacedOrder([
            'order_number' => 'ORD-TA',
            'dineType' => $takeAway,
            'table' => null,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('ORD-TA')
            ->assertSee('Take Away');
    }

    public function test_partial_payment_shows_method_and_partially_paid(): void
    {
        $method = PaymentMethod::factory()->create(['name' => 'Cash', 'slug' => 'cash']);
        PaymentMethod::factory()->create(['name' => 'UPI', 'slug' => 'upi']);
        [, $bill] = $this->createCheckoutBill([
            'order_number' => 'ORD-PAY-ROW',
        ], [
            'grand_total' => 1500,
        ]);
        $this->recordPayment($bill, $method, 500);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Cash')
            ->assertSee('Partially Paid')
            ->assertSee('class="payment-mode-select"', false)
            ->getContent();

        $this->assertStringNotContainsString('Total Rs. 1,500.00', $html);
    }

    public function test_multiple_payment_methods_display_together_when_paid(): void
    {
        $cash = PaymentMethod::factory()->create(['name' => 'Cash', 'slug' => 'cash']);
        $upi = PaymentMethod::factory()->create(['name' => 'UPI', 'slug' => 'upi']);
        [, $bill] = $this->createCheckoutBill([
            'order_number' => 'ORD-SPLIT',
        ], [
            'grand_total' => 1000,
            'status' => BillStatus::Settled,
            'settled_at' => now(),
        ]);
        $this->recordPayment($bill, $cash, 400);
        $this->recordPayment($bill, $upi, 600);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('ORD-SPLIT')
            ->assertSee('Cash + UPI')
            ->assertSee('Paid')
            ->getContent();

        $this->assertStringNotContainsString('class="payment-mode-select"', $html);
    }

    public function test_fully_settled_order_from_today_shows_paid_without_select(): void
    {
        $method = PaymentMethod::factory()->create(['name' => 'Card', 'slug' => 'card']);
        [, $bill] = $this->createCheckoutBill([
            'order_number' => 'ORD-SETTLED',
        ], [
            'grand_total' => 650,
            'status' => BillStatus::Settled,
            'settled_at' => now(),
        ]);
        $this->recordPayment($bill, $method, 650);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('ORD-SETTLED')
            ->assertSee('Card')
            ->assertSee('Paid')
            ->getContent();

        $this->assertStringNotContainsString('class="payment-mode-select"', $html);
    }

    public function test_amount_received_uses_actual_payment_rows_for_today(): void
    {
        $method = PaymentMethod::factory()->create();
        [, $todayBill] = $this->createCheckoutBill([], ['grand_total' => 1500]);
        [, $yesterdayBill] = $this->createCheckoutBill([], ['grand_total' => 999]);

        $this->recordPayment($todayBill, $method, 200);
        $this->recordPayment($yesterdayBill, $method, 999, [
            'paid_at' => now()->subDay(),
        ]);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/text-2xl font-bold text-\[#04346a\]">\s*Rs\. 200\.00\s*<\/p>\s*<p class="mt-1 text-sm font-semibold text-\[#1f2f3e\]">Total Amount Received<\/p>/',
            $html,
        );
    }

    public function test_payment_select_is_hidden_without_settle_permission(): void
    {
        PaymentMethod::factory()->create();
        $this->createCheckoutBill(['order_number' => 'ORD-NOPAY']);

        $admin = $this->userWithRole('admin');
        $permission = Permission::query()->where('slug', 'bills.settle')->firstOrFail();
        $admin->role->permissions()->detach($permission->id);

        $this->actingAs($admin->fresh())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('ORD-NOPAY')
            ->assertDontSee('class="payment-mode-select"', false);
    }

    /**
     * @return list<string>
     */
    private function trackOrderHeaders(string $html): array
    {
        $this->assertMatchesRegularExpression('/<table class="orders-table.*?<\/table>/s', $html);
        preg_match('/<table class="orders-table.*?<\/thead>/s', $html, $table);
        preg_match_all('/<th>(.*?)<\/th>/', $table[0], $headers);

        return $headers[1];
    }
}
