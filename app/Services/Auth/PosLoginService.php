<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosLoginService
{
    /**
     * Web dashboards that exist in this application.
     *
     * Each user has one role (users.role_id). Waiter and captain use the
     * staff API, not a web dashboard. Legacy kitchen accounts were imported
     * as waiters. There is no kitchen web dashboard.
     *
     * @var array<string, string>
     */
    private const DASHBOARD_ROUTES = [
        'admin' => 'admin.dashboard',
        'counter' => 'counter.dashboard',
    ];

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
        $this->ensureIsNotRateLimited($credentials['username']);

        try {
            $user = $this->authService->authenticate($credentials);
            $this->authService->ensurePasswordIsCurrent($user);
            $this->assertCanOpenDashboard($user);
        } catch (ValidationException $exception) {
            RateLimiter::hit($this->throttleKey($credentials['username']));

            throw $exception;
        }

        RateLimiter::clear($this->throttleKey($credentials['username']));

        Auth::login($user, $remember);

        return $user;
    }

    public function dashboardUrl(?User $user): ?string
    {
        $slug = $user?->role?->slug;
        $route = self::DASHBOARD_ROUTES[$slug] ?? null;

        if ($route === null || ! $user->hasPermission('dashboard.view')) {
            return null;
        }

        return route($route);
    }

    public function logout(): void
    {
        Auth::logout();
    }

    /**
     * @throws ValidationException
     */
    private function assertCanOpenDashboard(User $user): void
    {
        $slug = $user->role?->slug;

        if (in_array($slug, ['waiter', 'captain'], true)) {
            throw ValidationException::withMessages([
                'username' => ['This account uses the staff application. It does not have a web dashboard.'],
            ]);
        }

        if ($slug !== null && isset(self::DASHBOARD_ROUTES[$slug]) && ! $user->hasPermission('dashboard.view')) {
            throw ValidationException::withMessages([
                'username' => ['You do not have permission to access the dashboard.'],
            ]);
        }

        if ($this->dashboardUrl($user) === null) {
            throw ValidationException::withMessages([
                'username' => ['Your account does not have a web dashboard.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(string $username): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($username), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($username));

        throw ValidationException::withMessages([
            'username' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ])],
        ]);
    }

    private function throttleKey(string $username): string
    {
        return Str::transliterate(Str::lower($username).'|'.request()->ip());
    }
}
