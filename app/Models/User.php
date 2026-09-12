<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'mobile',
        'password',
        'role_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'waiter_id');
    }

    /**
     * Check if the user has a specific permission through their role.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->role?->permissions()
            ->where('slug', $permissionSlug)
            ->exists() ?? false;
    }

    /**
     * Get all permission slugs for this user's role.
     *
     * @return list<string>
     */
    public function getPermissionSlugs(): array
    {
        return $this->role?->permissions()
            ->pluck('slug')
            ->all() ?? [];
    }
}
