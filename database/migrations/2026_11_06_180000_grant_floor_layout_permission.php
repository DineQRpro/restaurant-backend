<?php

use App\Models\Permission;
use App\Services\Authorization\FloorLayoutPermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(FloorLayoutPermission::class)->sync();
    }

    public function down(): void
    {
        $permission = Permission::query()->where('slug', FloorLayoutPermission::SLUG)->first();

        if ($permission === null) {
            return;
        }

        $permission->roles()->detach();
        $permission->delete();
    }
};
