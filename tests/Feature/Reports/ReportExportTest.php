<?php

namespace Tests\Feature\Reports;

use App\Enums\BillStatus;
use App\Enums\OrderItemStatus;
use App\Models\PaymentMethod;
use App\Models\Permission;
use Carbon\Carbon;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesReportFixtures;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;
use ZipArchive;

class ReportExportTest extends TestCase
{
    use CreatesReportFixtures, InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(PaymentMethodSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-11 12:00:00'));
    }

    public function test_csv_export_uses_filters_filename_and_totals(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-CSV'],
            ['bill_number' => 'BILL-CSV', 'grand_total' => 75, 'discount_amount' => 5, 'tax_amount' => 3.5, 'issued_at' => now()],
        );

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction?export=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'transaction-report-2026-09-02-to-2026-09-11.csv',
            (string) $response->headers->get('content-disposition'),
        );

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Transaction Report', $csv);
        $this->assertStringContainsString('BILL-CSV', $csv);
        $this->assertStringContainsString('75', $csv);
        $this->assertStringContainsString('Totals', $csv);
        $this->assertDatabaseCount('bill_payments', 1);
    }

    public function test_excel_export_contains_filtered_rows_and_xlsx_signature(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-XLS'],
            ['bill_number' => 'BILL-XLS', 'grand_total' => 120, 'issued_at' => now()],
        );
        $this->createSettledBill(
            ['order_number' => 'ORD-OLD'],
            ['bill_number' => 'BILL-OLD', 'grand_total' => 50, 'issued_at' => now()->subDays(20)],
        );

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction?export=excel');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
        $this->assertStringContainsString(
            'transaction-report-2026-09-02-to-2026-09-11.xlsx',
            (string) $response->headers->get('content-disposition'),
        );

        $sheet = $this->xlsxSheet($response->getContent());
        $this->assertStringContainsString('BILL-XLS', $sheet);
        $this->assertStringContainsString('Totals', $sheet);
        $this->assertStringNotContainsString('BILL-OLD', $sheet);
    }

    public function test_pdf_export_contains_report_dataset_and_filename(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-PDF'],
            ['bill_number' => 'BILL-PDF', 'grand_total' => 88, 'issued_at' => now()],
        );

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction?export=pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'transaction-report-2026-09-02-to-2026-09-11.pdf',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('BILL-PDF', $response->getContent());
        $this->assertStringContainsString('Transaction Report', $response->getContent());
    }

    public function test_exports_honor_bill_status_filter(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-PAID'],
            ['bill_number' => 'BILL-PAID', 'grand_total' => 200, 'issued_at' => now()],
        );
        $this->createCheckoutBill(
            ['order_number' => 'ORD-OPEN'],
            ['bill_number' => 'BILL-OPEN', 'grand_total' => 300, 'issued_at' => now(), 'status' => BillStatus::Open],
        );

        $csv = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/bill-status?bill_status=paid&export=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('BILL-PAID', $csv);
        $this->assertStringNotContainsString('BILL-OPEN', $csv);
        $this->assertStringContainsString('Bill Status: paid', $csv);
    }

    public function test_split_and_non_chargeable_exports_match_screen_logic(): void
    {
        [, $bill] = $this->createCheckoutBill(
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

        $this->createSettledBill(
            ['order_number' => 'ORD-NC'],
            ['bill_number' => 'BILL-NC', 'grand_total' => 500, 'issued_at' => now()],
            PaymentMethod::SLUG_NON_CHARGEABLE,
        );

        $csv = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/payment-mode?export=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('400', $csv);
        $this->assertStringContainsString('600', $csv);
        $this->assertStringContainsString('500', $csv);
        $this->assertStringContainsString('Non Chargeable', $csv);
        $this->assertStringNotContainsString('Split', $csv);
    }

    public function test_cancelled_items_remain_excluded_from_item_wise_export(): void
    {
        [$order] = $this->createSettledBill(
            ['order_number' => 'ORD-IT'],
            ['bill_number' => 'BILL-IT', 'grand_total' => 80, 'issued_at' => now()],
        );
        $this->addOrderItem($order, ['name' => 'Idli', 'quantity' => 2, 'unit_price' => 40]);
        $this->addOrderItem($order, [
            'name' => 'Cancelled Dosa',
            'quantity' => 9,
            'unit_price' => 50,
            'status' => OrderItemStatus::Cancelled,
        ]);

        $csv = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/reports/item-wise?export=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Idli', $csv);
        $this->assertStringContainsString('80', $csv);
        $this->assertStringNotContainsString('Cancelled Dosa', $csv);
    }

    public function test_guest_cannot_export_reports(): void
    {
        $this->get('/admin/reports/transaction?export=csv')->assertRedirect('/admin/login');
        $this->get('/counter/reports/transaction?export=excel')->assertRedirect('/counter/login');
        $this->get('/admin/reports/transaction?export=pdf')->assertRedirect('/admin/login');
    }

    public function test_wrong_portal_and_missing_permission_cannot_export(): void
    {
        $this->actingAs($this->userWithRole('waiter'))
            ->get('/admin/reports/transaction?export=csv')
            ->assertForbidden();

        $this->actingAs($this->userWithRole('counter'))
            ->get('/admin/reports/transaction?export=excel')
            ->assertForbidden();

        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'reports.view')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/reports/payment-mode?export=pdf')
            ->assertForbidden();
    }

    public function test_standard_report_page_still_renders_export_buttons(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction')
            ->assertOk()
            ->assertSee('CSV')
            ->assertSee('Excel')
            ->assertSee('PDF')
            ->assertSee('There is no bill report for the selected date range.');
    }

    public function test_thermal_pages_have_print_controls_and_no_file_exports(): void
    {
        $this->createSettledBill(
            ['order_number' => 'ORD-TH'],
            ['bill_number' => 'BILL-TH', 'grand_total' => 40, 'tax_amount' => 2, 'issued_at' => now()],
        );

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/bill-summary')
            ->assertOk()
            ->assertSee('Print')
            ->assertSee('data-thermal-print', false)
            ->assertSee('id="thermal-print-root"', false)
            ->assertSee('thermal-print-report')
            ->assertSee('BILL-TH')
            ->assertSee('window.print()')
            ->getContent();

        $this->assertStringNotContainsString('export=csv', $html);
        $this->assertStringNotContainsString('export=excel', $html);
        $this->assertStringNotContainsString('export=pdf', $html);
    }

    private function xlsxSheet(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->assertNotFalse($path);
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        return $sheet;
    }
}
