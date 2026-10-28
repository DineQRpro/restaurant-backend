<?php

namespace App\Http\Controllers\Counter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function create(): View
    {
        return view('auth.change-password', [
            'title' => 'Change counter password',
            'action' => route('counter.password.update'),
            'loginUrl' => route('login'),
        ]);
    }

    public function store(ChangePasswordRequest $request): RedirectResponse
    {
        $this->authService->changePassword($request->safe()->only([
            'username',
            'current_password',
            'password',
        ]));

        return redirect()->route('login')->with('status', 'Password changed. Sign in with the new password.');
    }
}
