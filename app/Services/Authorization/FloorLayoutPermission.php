<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\Role;

class FloorLayoutPermission
{
    public const SLUG = 'tables.layout';

    /**
     * Create the floor-layout permission and grant it to Admin.
     * Safe to run more than once. Other roles are left unchanged.
     */
    public function sync(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => self::SLUG],
            ['name' => 'Arrange Floor Layout'],
        );

        $admin = Role::query()->where('slug', 'admin')->first();

        if ($admin === null) {
            return;
        }

        $admin->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
