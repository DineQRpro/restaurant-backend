<?php

namespace App\Services\Counter;

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
                'username' => ['This counter portal is for restaurant counter staff. Please sign in using the staff application.'],
            ]);
        }

        if ($slug === 'admin') {
            throw ValidationException::withMessages([
                'username' => ['Please sign in using the admin portal.'],
            ]);
        }

        if ($slug !== 'counter') {
            throw ValidationException::withMessages([
                'username' => ['You do not have access to the counter portal.'],
            ]);
        }

        if (! $user->hasPermission('dashboard.view')) {
            throw ValidationException::withMessages([
                'username' => ['You do not have permission to access the counter dashboard.'],
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
