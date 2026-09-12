<?php

namespace App\Services\Users;

use App\Exceptions\BusinessConflictException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * @param  array{
     *     search?: string,
     *     role?: string,
     *     is_active?: bool,
     *     per_page?: int
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()->with('role');

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('mobile', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['role'])) {
            $query->whereHas('role', function ($builder) use ($filters) {
                $builder->where('slug', $filters['role']);
            });
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        $data['is_active'] ??= true;

        return User::create($data)->load('role');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, ?User $actor = null): User
    {
        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $this->guardAgainstSelfDeactivation($user, $actor);
        }

        if (! array_key_exists('password', $data) || $data['password'] === null || $data['password'] === '') {
            unset($data['password']);
        }

        $user->update($data);

        return $user->fresh('role');
    }

    public function updateStatus(User $user, bool $isActive, ?User $actor = null): User
    {
        if ($isActive === false) {
            $this->guardAgainstSelfDeactivation($user, $actor);
        }

        $user->update(['is_active' => $isActive]);

        return $user->fresh('role');
    }

    public function delete(User $user, ?User $actor = null): void
    {
        if ($this->isSameUser($user, $actor)) {
            throw new BusinessConflictException('You cannot delete your own account.');
        }

        throw new BusinessConflictException(
            'Users cannot be permanently deleted. Deactivate the user instead.'
        );
    }

    private function guardAgainstSelfDeactivation(User $user, ?User $actor): void
    {
        if ($this->isSameUser($user, $actor)) {
            throw new BusinessConflictException('You cannot deactivate your own account.');
        }
    }

    private function isSameUser(User $user, ?User $actor): bool
    {
        return $actor !== null && $actor->is($user);
    }
}
