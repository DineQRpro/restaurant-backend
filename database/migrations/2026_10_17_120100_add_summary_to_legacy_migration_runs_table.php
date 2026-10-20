<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_migration_runs', function (Blueprint $table) {
            $table->json('summary')->nullable()->after('pending_count');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_migration_runs', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
