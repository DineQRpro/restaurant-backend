<?php

namespace Tests\Feature\Reports;

use App\Support\ReportCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class ReportNavigationTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_admin_report_catalog_matches_varsitykitchen_sidebar_order(): void
    {
        $this->assertSame([
            'Item Summary Report',
            'Bill Summary Report',
            'Bill Status Report',
            'Transaction Report',
            'Payment Mode Report',
            'Waiter Report',
            'Order Wise Report',
            'Item Wise Detail Report',
            'Customer Data Report',
        ], ReportCatalog::adminLabels());
    }

    public function test_counter_report_catalog_matches_varsitykitchen_counter_sidebar_order(): void
    {
        $this->assertSame([
            'Transaction Reports',
            'Order Wise Report',
            'Product Wise Report',
            'Waiter Report',
            'Payment Mode Report',
            'Bill Status Report',
            'Customer Report',
        ], ReportCatalog::counterLabels());
    }

    public function test_admin_sidebar_renders_reports_in_legacy_order(): void
    {
        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction')
            ->assertOk()
            ->getContent();

        $this->assertLabelsAppearInOrder($html, ReportCatalog::adminLabels());
        $this->assertStringNotContainsString('Daily Report', $html);
        $this->assertStringNotContainsString('Store', $html);
    }

    public function test_counter_sidebar_renders_reports_in_legacy_order(): void
    {
        $html = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/reports/transaction')
            ->assertOk()
            ->getContent();

        $this->assertLabelsAppearInOrder($html, ReportCatalog::counterLabels());
        $this->assertStringNotContainsString('Item Summary Report', $html);
        $this->assertStringNotContainsString('Bill Summary Report', $html);
        $this->assertStringNotContainsString('Item Wise Detail Report', $html);
        $this->assertStringNotContainsString('Customer Data Report', $html);
    }

    /**
     * @param  list<string>  $labels
     */
    private function assertLabelsAppearInOrder(string $html, array $labels): void
    {
        $cursor = 0;

        foreach ($labels as $label) {
            $position = strpos($html, $label, $cursor);
            $this->assertNotFalse($position, "Missing report label [{$label}] in navigation.");
            $cursor = $position + strlen($label);
        }
    }
}
