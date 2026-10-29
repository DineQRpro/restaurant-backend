<?php

namespace App\Http\Controllers\Admin;

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
            'title' => 'Change password',
            'intro' => 'Enter your current password, then choose a new one. You can sign in after it is saved.',
            'action' => route('password.change.store'),
            'backUrl' => route('login'),
            'backLabel' => 'Back to sign in',
            'requireUsername' => true,
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
