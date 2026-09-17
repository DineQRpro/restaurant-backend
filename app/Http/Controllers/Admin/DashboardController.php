<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(): View
    {
        $user = auth()->user();

        return view('admin.dashboard.index', [
            'dashboard' => $this->dashboardService->summary(),
            'canSettleBills' => $user?->hasPermission('bills.settle') ?? false,
            'canViewKot' => $user?->hasPermission('kot.view') ?? false,
            'canEditKot' => $user?->hasPermission('kot.update') ?? false,
            'canViewBill' => $user?->hasPermission('bills.view') ?? false,
        ]);
    }
}
