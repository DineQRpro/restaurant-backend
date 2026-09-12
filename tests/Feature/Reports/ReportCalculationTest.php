<?php

namespace Tests\Feature\Reports;

use App\Enums\BillStatus;
use App\Enums\OrderItemStatus;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\MenuItem;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesReportFixtures;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class ReportCalculationTest extends TestCase
{
    use CreatesReportFixtures, InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(PaymentMethodSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-11 12:00:00'));
    }

    public function test_transaction_report_uses_default_last_ten_days_and_excludes_older_bills(): void
    {
        $admin = $this->userWithRole('admin');

        $this->createSettledBill(
            ['order_number' => 'ORD-OLD'],
            ['bill_number' => 'BILL-OLD', 'grand_total' => 100, 'issued_at' => now()->subDays(20)],
        );
        $this->createSettledBill(
            ['order_number' => 'ORD-NEW'],
            ['bill_number' => 'BILL-NEW', 'grand_total' => 250, 'tax_amount' => 12.5, 'discount_amount' => 10, 'issued_at' => now()],
        );

        $this->actingAs($admin)
            ->get('/admin/reports/transaction')
            ->assertOk()
            ->assertSee('BILL-NEW')
            ->assertSee('ORD-NEW')
            ->assertSee('250.00')
            ->assertSee('12.50')
            ->assertSee('10.00')
            ->assertDontSee('BILL-OLD')
            ->assertSee('Cash')
            ->assertSee('Paid');
    }

    public function test_transaction_show_all_includes_historical_bills(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-OLD'],
            ['bill_number' => 'BILL-HIST', 'grand_total' => 100, 'issued_at' => now()->subDays(40)],
        );

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction?show_all=1')
            ->assertOk()
            ->assertSee('BILL-HIST');
    }

    public function test_transaction_rejects_invalid_date_range(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/reports/transaction')
            ->get('/admin/reports/transaction?sdate=2026-09-10T00:00&edate=2026-09-01T00:00')
            ->assertRedirect('/admin/reports/transaction')
            ->assertSessionHasErrors('edate');
    }

    public function test_split_payments_are_broken_out_by_method_not_as_split(): void
    {
        [$order, $bill] = $this->createCheckoutBill(
            ['order_number' => 'ORD-SPLIT'],
            [
                'bill_number' => 'BILL-SPLIT',
                'grand_total' => 1000,
                'status' => BillStatus::Settled,
                'settled_at' => now(),
                'issued_at' => now(),
            ],
        );

        $this->recordPayment($bill, $this->paymentMethod(PaymentMethod::SLUG_CASH), 400);
        $this->recordPayment($bill, $this->paymentMethod(PaymentMethod::SLUG_UPI), 600);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/payment-mode')
            ->assertOk()
            ->assertSee('400.00')
            ->assertSee('600.00')
            ->assertSee('1,000.00')
            ->assertDontSee('Split')
            ->getContent();

        $this->assertStringContainsString('Cash', $html);
        $this->assertStringContainsString('UPI', $html);
    }

    public function test_non_chargeable_is_not_classified_as_cash(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-NC'],
            ['bill_number' => 'BILL-NC', 'grand_total' => 500, 'issued_at' => now()],
            PaymentMethod::SLUG_NON_CHARGEABLE,
        );

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction')
            ->assertOk()
            ->assertSee('BILL-NC')
            ->assertSee('Non Chargeable')
            ->assertDontSee('>Cash</td>');

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/payment-mode')
            ->assertOk()
            ->assertSee('500.00')
            ->assertSee('Non Chargeable');
    }

    public function test_bill_status_filters_paid_unpaid_and_credit_is_empty(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-PAID'],
            ['bill_number' => 'BILL-PAID', 'grand_total' => 200, 'issued_at' => now()],
        );
        $this->createCheckoutBill(
            ['order_number' => 'ORD-OPEN'],
            ['bill_number' => 'BILL-OPEN', 'grand_total' => 300, 'issued_at' => now(), 'status' => BillStatus::Open],
        );

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/bill-status?bill_status=paid')
            ->assertOk()
            ->assertSee('BILL-PAID')
            ->assertDontSee('BILL-OPEN');

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/bill-status?bill_status=unpaid')
            ->assertOk()
            ->assertSee('BILL-OPEN')
            ->assertDontSee('BILL-PAID');

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/bill-status?bill_status=credit')
            ->assertOk()
            ->assertDontSee('BILL-PAID')
            ->assertDontSee('BILL-OPEN')
            ->assertSee('No records found for the selected period.');
    }

    public function test_waiter_report_groups_by_waiter_and_uses_payment_amounts(): void
    {
        $waiter = $this->userWithRole('waiter', ['name' => 'Ravi Waiter']);

        $this->createSettledBill(
            ['waiter' => $waiter, 'order_number' => 'ORD-W1'],
            ['bill_number' => 'BILL-W1', 'grand_total' => 800, 'issued_at' => now()],
        );

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/waiter')
            ->assertOk()
            ->assertSee('Ravi Waiter')
            ->assertSee((string) $waiter->id)
            ->assertSee('800.00');
    }

    public function test_order_wise_excludes_cancelled_items_from_qty_and_packing(): void
    {
        [$order, $bill] = $this->createSettledBill(
            ['order_number' => 'ORD-OW', 'pax' => 4],
            ['bill_number' => 'BILL-OW', 'grand_total' => 400, 'issued_at' => now()],
        );

        $this->addOrderItem($order, ['name' => 'Tea', 'quantity' => 2, 'unit_price' => 50, 'packing_fee' => 10]);
        $this->addOrderItem($order, [
            'name' => 'Cancelled Soup',
            'quantity' => 5,
            'unit_price' => 90,
            'packing_fee' => 20,
            'status' => OrderItemStatus::Cancelled,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/order-wise')
            ->assertOk()
            ->assertSee('ORD-OW')
            ->assertSee('BILL-OW')
            ->assertSee('400.00')
            ->assertSee('10.00')
            ->assertDontSee('Cancelled Soup');
    }

    public function test_item_wise_uses_historical_unit_price_and_skips_cancelled(): void
    {
        $takeAway = DineType::query()->firstOrCreate(
            ['slug' => 'take-away'],
            ['name' => 'Take Away', 'sort_order' => 2, 'is_active' => true],
        );

        $item = MenuItem::factory()->create(['name' => 'Biryani', 'price' => 150]);

        [$dineOrder] = $this->createSettledBill(
            ['order_number' => 'ORD-DI'],
            ['bill_number' => 'BILL-DI', 'grand_total' => 300, 'issued_at' => now()],
        );
        $this->addOrderItem($dineOrder, ['menuItem' => $item, 'quantity' => 2, 'unit_price' => 150]);
        $this->addOrderItem($dineOrder, [
            'menuItem' => $item,
            'quantity' => 1,
            'unit_price' => 150,
            'status' => OrderItemStatus::Cancelled,
        ]);

        [$takeOrder] = $this->createSettledBill(
            ['order_number' => 'ORD-TA', 'dineType' => $takeAway],
            ['bill_number' => 'BILL-TA', 'grand_total' => 150, 'issued_at' => now()],
        );
        $this->addOrderItem($takeOrder, ['menuItem' => $item, 'quantity' => 1, 'unit_price' => 140]);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/item-wise')
            ->assertOk()
            ->assertSee('Biryani')
            ->getContent();

        $this->assertStringContainsString('440.00', $html);
        $this->assertStringNotContainsString('Cancelled', $html);
    }

    public function test_customer_report_groups_by_phone_and_computes_average(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Anita',
            'phone' => '9876543210',
        ]);

        $this->createSettledBill(
            ['customer' => $customer, 'order_number' => 'ORD-C1'],
            ['bill_number' => 'BILL-C1', 'grand_total' => 200, 'issued_at' => now()],
        );
        $this->createSettledBill(
            ['customer' => $customer, 'order_number' => 'ORD-C2'],
            ['bill_number' => 'BILL-C2', 'grand_total' => 400, 'issued_at' => now()],
        );

        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/reports/customer')
            ->assertOk()
            ->assertSee('Anita')
            ->assertSee('9876543210')
            ->assertSee('600.00')
            ->assertSee('300.00');
    }

    public function test_thermal_item_summary_defaults_to_last_36_hours_and_excludes_cancelled(): void
    {
        [$recent] = $this->createSettledBill(
            ['order_number' => 'ORD-TH'],
            ['bill_number' => 'BILL-TH', 'grand_total' => 80, 'tax_amount' => 4, 'issued_at' => now()->subHour()],
        );
        $this->addOrderItem($recent, ['name' => 'Idli', 'quantity' => 2, 'unit_price' => 40]);
        $this->addOrderItem($recent, [
            'name' => 'Old Dosa',
            'quantity' => 9,
            'unit_price' => 50,
            'status' => OrderItemStatus::Cancelled,
        ]);

        [$old] = $this->createSettledBill(
            ['order_number' => 'ORD-OLDTH'],
            ['bill_number' => 'BILL-OLDTH', 'grand_total' => 90, 'issued_at' => now()->subHours(48)],
        );
        $this->addOrderItem($old, ['name' => 'Old Dosa', 'quantity' => 3, 'unit_price' => 30]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/item-summary')
            ->assertOk()
            ->assertSee('Item Summary Report')
            ->assertSee('Idli')
            ->assertSee('80.00')
            ->assertDontSee('Old Dosa')
            ->assertSee('last 36 hours');
    }

    public function test_thermal_bill_summary_totals_tax_and_amount(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-BS'],
            ['bill_number' => 'BILL-BS', 'grand_total' => 199.50, 'tax_amount' => 9.50, 'issued_at' => now()],
        );

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/bill-summary')
            ->assertOk()
            ->assertSee('BILL-BS')
            ->assertSee('199.50')
            ->assertSee('9.50')
            ->assertSee('Total Bills:')
            ->assertSee('Print');
    }

    public function test_csv_export_is_read_only_and_contains_rows(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-CSV'],
            ['bill_number' => 'BILL-CSV', 'grand_total' => 75, 'issued_at' => now()],
        );

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction?export=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('BILL-CSV', $response->streamedContent());
        $this->assertDatabaseCount('bill_payments', 1);
    }

    public function test_empty_results_render_legacy_empty_copy(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction')
            ->assertOk()
            ->assertSee('There is no bill report for the selected date range.');
    }
}
