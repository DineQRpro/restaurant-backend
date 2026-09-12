<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Reports\BaseReportController;
use App\Http\Requests\Reports\ThermalReportFilterRequest;
use App\Services\Reports\BillStatusReportService;
use App\Services\Reports\CustomerReportService;
use App\Services\Reports\ItemWiseReportService;
use App\Services\Reports\OrderWiseReportService;
use App\Services\Reports\PaymentBreakdownReportService;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\ReportExportAssembler;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ThermalReportService;
use App\Services\Reports\TransactionReportService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends BaseReportController
{
    public function __construct(
        ReportDateRange $dates,
        ReportExportService $exporter,
        ReportExportAssembler $assembler,
        TransactionReportService $transactionReports,
        BillStatusReportService $billStatusReports,
        PaymentBreakdownReportService $paymentBreakdownReports,
        OrderWiseReportService $orderWiseReports,
        ItemWiseReportService $itemWiseReports,
        CustomerReportService $customerReports,
        private readonly ThermalReportService $thermalReports,
    ) {
        parent::__construct(
            $dates,
            $exporter,
            $assembler,
            $transactionReports,
            $billStatusReports,
            $paymentBreakdownReports,
            $orderWiseReports,
            $itemWiseReports,
            $customerReports,
        );
    }

    public function itemSummary(ThermalReportFilterRequest $request): View
    {
        $range = $request->range($this->dates);
        $rows = $this->thermalReports->itemSummary($range);

        return $this->thermalView(
            title: 'Item Summary Report',
            reportType: 'items_served',
            range: $range,
            rows: $rows,
            totals: $this->thermalReports->itemSummaryTotals($rows),
        );
    }

    public function billSummary(ThermalReportFilterRequest $request): View
    {
        $range = $request->range($this->dates);
        $rows = $this->thermalReports->billSummary($range);

        return $this->thermalView(
            title: 'Bill Summary Report',
            reportType: 'bill_summary',
            range: $range,
            rows: $rows,
            totals: $this->thermalReports->billSummaryTotals($rows),
        );
    }

    protected function layout(): string
    {
        return 'layouts.admin';
    }

    protected function routePrefix(): string
    {
        return 'admin.reports';
    }

    protected function dashboardRoute(): string
    {
        return 'admin.dashboard';
    }

    protected function transactionTitle(): string
    {
        return 'Transaction Report';
    }

    protected function itemWiseTitle(): string
    {
        return 'Item Wise Detail Report';
    }

    protected function customerTitle(): string
    {
        return 'Customer Data Report';
    }

    /**
     * @param  array<string, mixed>  $range
     * @param  Collection<int, object>  $rows
     * @param  array<string, mixed>  $totals
     */
    private function thermalView(string $title, string $reportType, array $range, Collection $rows, array $totals): View
    {
        $isFiltered = (bool) ($range['is_filtered'] ?? false);
        $page = max(1, (int) request('page', 1));
        $perPage = ThermalReportService::PER_PAGE;
        $pageRows = $rows;

        if ($isFiltered) {
            $pageRows = $rows->slice(($page - 1) * $perPage, $perPage)->values();
        }

        return view('reports.thermal', [
            'layout' => $this->layout(),
            'title' => $title,
            'reportType' => $reportType,
            'filterRoute' => $this->currentFilterRoute(),
            'dashboardRoute' => $this->dashboardRoute(),
            'range' => $range,
            'rows' => $rows,
            'pageRows' => $pageRows,
            'totals' => $totals,
            'isFiltered' => $isFiltered,
            'page' => $page,
            'perPage' => $perPage,
            'totalRows' => $rows->count(),
            'todayMax' => now()->format('Y-m-d').'T23:59',
        ]);
    }
}
