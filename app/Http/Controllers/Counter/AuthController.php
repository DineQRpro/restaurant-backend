<?php

namespace App\Http\Controllers\Counter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Counter\CounterLoginRequest;
use App\Services\Counter\WebAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly WebAuthService $webAuthService,
    ) {}

    public function create(): View
    {
        return view('counter.auth.login');
    }

    public function store(CounterLoginRequest $request): RedirectResponse
    {
        $this->webAuthService->login(
            $request->only('username', 'password'),
            $request->boolean('remember'),
        );

        $request->session()->regenerate();

        return redirect()->intended(route('counter.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->webAuthService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('counter.login');
    }
}
