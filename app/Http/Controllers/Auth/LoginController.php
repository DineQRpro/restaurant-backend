<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\PosLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly PosLoginService $posLoginService,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $this->posLoginService->login(
            $request->only('username', 'password'),
            $request->boolean('remember'),
        );

        $request->session()->regenerate();

        return redirect()->to($this->posLoginService->dashboardUrl($user) ?? route('auth.unavailable'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->posLoginService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function unavailable(): RedirectResponse|View
    {
        $user = auth()->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $dashboard = $this->posLoginService->dashboardUrl($user);

        if ($dashboard !== null) {
            return redirect()->to($dashboard);
        }

        return view('auth.unavailable');
    }
}
