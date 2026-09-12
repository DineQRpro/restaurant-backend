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
        return view('admin.dashboard.index', [
            'dashboard' => $this->dashboardService->summary(),
            'canSettleBills' => auth()->user()?->hasPermission('bills.settle') ?? false,
        ]);
    }
}
