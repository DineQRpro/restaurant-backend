<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AccountPasswordRequest;
use App\Services\Auth\PosLoginService;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountPasswordController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly PosLoginService $posLoginService,
    ) {}

    public function edit(): View
    {
        return view('auth.change-password', [
            'title' => 'Change password',
            'intro' => 'Enter your current password, then choose a new one.',
            'action' => route('account.password.update'),
            'backUrl' => $this->posLoginService->dashboardUrl(auth()->user()) ?? route('login'),
            'backLabel' => 'Back to dashboard',
            'requireUsername' => false,
        ]);
    }

    public function update(AccountPasswordRequest $request): RedirectResponse
    {
        $this->authService->updateOwnPassword(
            $request->user(),
            (string) $request->string('current_password'),
            (string) $request->string('password'),
        );

        $request->session()->regenerate();

        return redirect()
            ->to($this->posLoginService->dashboardUrl($request->user()) ?? route('login'))
            ->with('status', 'Password changed.');
    }
}
