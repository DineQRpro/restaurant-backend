<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('mobile', 10)->nullable()->unique()->after('email');
            $table->foreignId('role_id')->constrained('roles')->after('mobile');
            $table->boolean('is_active')->default(true)->after('role_id');

            // email becomes nullable (optional per docs)
            $table->string('email')->nullable()->change();

            // drop email_verified_at & remember_token (not needed for API-only auth)
            $table->dropColumn(['email_verified_at', 'remember_token']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['username', 'mobile', 'role_id', 'is_active']);

            $table->string('email')->nullable(false)->change();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
        });
    }
};
