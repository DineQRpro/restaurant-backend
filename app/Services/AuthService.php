<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
        $user = $this->authenticate([
            'username' => $credentials['username'],
            'password' => $credentials['current_password'],
        ]);

        if (! $user->must_change_password) {
            throw ValidationException::withMessages([
                'password' => ['This account does not require a password change.'],
            ]);
        }

        $user->password = $credentials['password'];
        $user->must_change_password = false;
        $user->save();

        return $user;
    }

    /**
     * @throws ValidationException
     */
    public function ensurePasswordIsCurrent(User $user): void
    {
        if ($user->must_change_password) {
            throw ValidationException::withMessages([
                'password' => ['Change your password before signing in.'],
            ]);
        }
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
