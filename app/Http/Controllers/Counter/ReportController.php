<?php

namespace App\Http\Controllers\Counter;

use App\Http\Controllers\Reports\BaseReportController;

class ReportController extends BaseReportController
{
    protected function layout(): string
    {
        return 'layouts.counter';
    }

    protected function routePrefix(): string
    {
        return 'counter.reports';
    }

    protected function dashboardRoute(): string
    {
        return 'counter.dashboard';
    }

    protected function transactionTitle(): string
    {
        return 'Transaction Reports';
    }

    protected function itemWiseTitle(): string
    {
        return 'Product Wise Report';
    }

    protected function customerTitle(): string
    {
        return 'Customer Report';
    }
}
