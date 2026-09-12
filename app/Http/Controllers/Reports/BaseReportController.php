<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Services\Reports\BillStatusReportService;
use App\Services\Reports\CustomerReportService;
use App\Services\Reports\ItemWiseReportService;
use App\Services\Reports\OrderWiseReportService;
use App\Services\Reports\PaymentBreakdownReportService;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\ReportExportAssembler;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\TransactionReportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseReportController extends Controller
{
    public function __construct(
        protected readonly ReportDateRange $dates,
        protected readonly ReportExportService $exporter,
        protected readonly ReportExportAssembler $assembler,
        protected readonly TransactionReportService $transactionReports,
        protected readonly BillStatusReportService $billStatusReports,
        protected readonly PaymentBreakdownReportService $paymentBreakdownReports,
        protected readonly OrderWiseReportService $orderWiseReports,
        protected readonly ItemWiseReportService $itemWiseReports,
        protected readonly CustomerReportService $customerReports,
    ) {}

    abstract protected function layout(): string;

    abstract protected function routePrefix(): string;

    abstract protected function dashboardRoute(): string;

    public function transaction(ReportFilterRequest $request): View|Response
    {
        $filters = $request->filters($this->dates);
        $rows = $filters->isExport()
            ? $this->transactionReports->all($filters)
            : $this->transactionReports->paginate($filters);
        $totals = $this->transactionReports->totals($filters);

        if ($filters->isExport()) {
            return $this->exporter->download(
                $this->assembler->transaction($this->transactionTitle(), $filters, $rows, $totals),
                $filters,
                (string) $filters->exportFormat,
            );
        }

        return $this->reportView('reports.transaction', $this->transactionTitle(), $filters, [
            'rows' => $rows,
            'totals' => $totals,
            'emptyMessage' => 'There is no bill report for the selected date range.',
            'showAll' => true,
            'billNoFilter' => true,
        ]);
    }

    public function billStatus(ReportFilterRequest $request): View|Response
    {
        $filters = $request->filters($this->dates);
        $rows = $filters->isExport()
            ? $this->billStatusReports->all($filters)
            : $this->billStatusReports->paginate($filters);

        if ($filters->isExport()) {
            return $this->exporter->download(
                $this->assembler->billStatus($filters, $rows),
                $filters,
                (string) $filters->exportFormat,
            );
        }

        return $this->reportView('reports.bill-status', 'Bill Status Report', $filters, [
            'rows' => $rows,
            'recordCount' => $rows instanceof LengthAwarePaginator ? $rows->total() : $rows->count(),
            'emptyMessage' => 'No records found for the selected period.',
            'billStatusFilter' => true,
        ]);
    }

    public function paymentMode(ReportFilterRequest $request): View|Response
    {
        $filters = $request->filters($this->dates);
        $rows = $this->paymentBreakdownReports->paymentMode($filters);
        $totals = $this->paymentBreakdownReports->paymentModeTotals($rows);

        if ($filters->isExport()) {
            return $this->exporter->download(
                $this->assembler->paymentMode($filters, $rows, $totals),
                $filters,
                (string) $filters->exportFormat,
            );
        }

        return $this->reportView('reports.payment-mode', 'Payment Mode Report', $filters, [
            'rows' => $rows,
            'totals' => $totals,
            'emptyMessage' => 'No records found for the selected period.',
        ]);
    }

    public function waiter(ReportFilterRequest $request): View|Response
    {
        $filters = $request->filters($this->dates);
        $rows = $this->paymentBreakdownReports->waiter($filters);
        $totals = $this->paymentBreakdownReports->waiterTotals($rows);

        if ($filters->isExport()) {
            return $this->exporter->download(
                $this->assembler->waiter($filters, $rows, $totals),
                $filters,
                (string) $filters->exportFormat,
            );
        }

        return $this->reportView('reports.waiter', 'Waiter Report', $filters, [
            'rows' => $rows,
            'totals' => $totals,
            'emptyMessage' => 'No records found for the selected period.',
        ]);
    }

    public function orderWise(ReportFilterRequest $request): View|Response
    {
        $filters = $request->filters($this->dates);
        $rows = $filters->isExport()
            ? $this->orderWiseReports->all($filters)
            : $this->orderWiseReports->paginate($filters);
        $totals = $this->orderWiseReports->totals($filters);

        if ($filters->isExport()) {
            return $this->exporter->download(
                $this->assembler->orderWise($filters, $rows, $totals),
                $filters,
                (string) $filters->exportFormat,
            );
        }

        return $this->reportView('reports.order-wise', 'Order Wise Report', $filters, [
            'rows' => $rows,
            'totals' => $totals,
            'emptyMessage' => 'There is no order wise report.',
            'billNoFilter' => true,
            'orderTypeFilter' => true,
        ]);
    }

    public function itemWise(ReportFilterRequest $request): View|Response
    {
        $filters = $request->filters($this->dates);
        $rows = $filters->isExport()
            ? $this->itemWiseReports->all($filters)
            : $this->itemWiseReports->paginate($filters);
        $totals = $this->itemWiseReports->totals($filters);

        if ($filters->isExport()) {
            return $this->exporter->download(
                $this->assembler->itemWise($this->itemWiseTitle(), $filters, $rows, $totals),
                $filters,
                (string) $filters->exportFormat,
            );
        }

        return $this->reportView('reports.item-wise', $this->itemWiseTitle(), $filters, [
            'rows' => $rows,
            'totals' => $totals,
            'itemOptions' => $this->itemWiseReports->itemOptions(),
            'emptyMessage' => 'There is no ordered product wise report.',
            'itemNameFilter' => true,
        ]);
    }

    public function customer(ReportFilterRequest $request): View|Response
    {
        $filters = $request->filters($this->dates);
        $rows = $filters->isExport()
            ? $this->customerReports->all($filters)
            : $this->customerReports->paginate($filters);
        $totals = $this->customerReports->totals($filters);

        if ($filters->isExport()) {
            return $this->exporter->download(
                $this->assembler->customer($this->customerTitle(), $filters, $rows, $totals),
                $filters,
                (string) $filters->exportFormat,
            );
        }

        return $this->reportView('reports.customer', $this->customerTitle(), $filters, [
            'rows' => $rows,
            'totals' => $totals,
            'emptyMessage' => 'No records found for the selected period.',
            'showAll' => true,
        ]);
    }

    abstract protected function transactionTitle(): string;

    abstract protected function itemWiseTitle(): string;

    abstract protected function customerTitle(): string;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function reportView(string $view, string $title, ReportFilters $filters, array $data): View
    {
        return view($view, array_merge([
            'layout' => $this->layout(),
            'title' => $title,
            'filters' => $filters,
            'filterRoute' => $this->currentFilterRoute(),
            'dashboardRoute' => $this->dashboardRoute(),
            'exportEnabled' => true,
            'todayMax' => now()->format('Y-m-d').'T23:59',
            'billNoFilter' => false,
            'billStatusFilter' => false,
            'orderTypeFilter' => false,
            'itemNameFilter' => false,
            'showAll' => false,
            'itemOptions' => collect(),
        ], $data));
    }

    protected function currentFilterRoute(): string
    {
        return request()->route()?->getName() ?? $this->routePrefix().'.transaction';
    }
}
