<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WebAuthService
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * @param  array{username: string, password: string}  $credentials
     *
     * @throws ValidationException
     */
    public function login(array $credentials, bool $remember = false): User
    {
        $user = $this->authService->authenticate($credentials);
        $slug = $user->role?->slug;

        if (in_array($slug, ['waiter', 'captain'], true)) {
            throw ValidationException::withMessages([
                'username' => ['This admin portal is for restaurant administrators. Please sign in using the staff application.'],
            ]);
        }

        if ($slug === 'counter') {
            throw ValidationException::withMessages([
                'username' => ['Please sign in using the counter portal.'],
            ]);
        }

        if ($slug === 'kitchen') {
            throw ValidationException::withMessages([
                'username' => ['Your dashboard is not available yet.'],
            ]);
        }

        if ($slug !== 'admin') {
            throw ValidationException::withMessages([
                'username' => ['You do not have access to the admin portal.'],
            ]);
        }

        if (! $user->hasPermission('dashboard.view')) {
            throw ValidationException::withMessages([
                'username' => ['You do not have permission to access the admin dashboard.'],
            ]);
        }

        $this->authService->ensurePasswordIsCurrent($user);

        Auth::login($user, $remember);

        return $user;
    }

    public function logout(): void
    {
        Auth::logout();
    }
}
