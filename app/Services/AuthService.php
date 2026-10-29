<?php

namespace App\Services;

use App\Exceptions\PasswordChangeRequiredException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Validate username/password without issuing a token or session.
     *
     * @param  array{username: string, password: string}  $credentials
     *
     * @throws ValidationException
     */
    public function authenticate(array $credentials): User
    {
        $user = User::query()
            ->with('role')
            ->where('username', $credentials['username'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['This account has been deactivated.'],
            ]);
        }

        return $user;
    }

    /**
     * @param  array{username: string, current_password: string, password: string}  $credentials
     *
     * @throws ValidationException
     */
    public function changePassword(array $credentials): User
    {
        $this->ensurePasswordChangeIsNotRateLimited($credentials['username']);

        try {
            $user = $this->authenticate([
                'username' => $credentials['username'],
                'password' => $credentials['current_password'],
            ]);

            if (! $user->must_change_password) {
                throw ValidationException::withMessages([
                    'password' => ['This account does not require a password change.'],
                ]);
            }

            $this->storeNewPassword($user, $credentials['password']);
        } catch (ValidationException $exception) {
            RateLimiter::hit($this->passwordChangeKey($credentials['username']));

            throw $exception;
        }

        RateLimiter::clear($this->passwordChangeKey($credentials['username']));

        return $user;
    }

    /**
     * Change the password for a user who is already signed in.
     *
     * @throws ValidationException
     */
    public function updateOwnPassword(User $user, string $currentPassword, string $newPassword): User
    {
        $this->ensurePasswordChangeIsNotRateLimited($user->username);

        try {
            if (! Hash::check($currentPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            $this->storeNewPassword($user, $newPassword);
        } catch (ValidationException $exception) {
            RateLimiter::hit($this->passwordChangeKey($user->username));

            throw $exception;
        }

        RateLimiter::clear($this->passwordChangeKey($user->username));

        return $user;
    }

    /**
     * @throws ValidationException
     */
    public function ensurePasswordIsCurrent(User $user): void
    {
        if ($user->must_change_password) {
            throw PasswordChangeRequiredException::make();
        }
    }

    private function storeNewPassword(User $user, string $password): void
    {
        $user->password = $password;
        $user->must_change_password = false;
        $user->save();
        $user->tokens()->delete();
    }

    /**
     * @throws ValidationException
     */
    private function ensurePasswordChangeIsNotRateLimited(string $username): void
    {
        if (! RateLimiter::tooManyAttempts($this->passwordChangeKey($username), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->passwordChangeKey($username));

        throw ValidationException::withMessages([
            'password' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ])],
        ]);
    }

    private function passwordChangeKey(string $username): string
    {
        return 'password-change|'.Str::transliterate(Str::lower($username).'|'.request()->ip());
    }

    /**
     * Authenticate user by username/password and return a Sanctum token.
     *
     * @param  array{username: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $user = $this->authenticate($credentials);
        $this->ensurePasswordIsCurrent($user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
