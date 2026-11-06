<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floors', function (Blueprint $table): void {
            $table->unsignedInteger('layout_version')->default(1)->after('is_active');
        });

        Schema::table('tables', function (Blueprint $table): void {
            $table->unsignedSmallInteger('layout_x')->nullable()->after('sort_order');
            $table->unsignedSmallInteger('layout_y')->nullable()->after('layout_x');
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table): void {
            $table->dropColumn(['layout_x', 'layout_y']);
        });

        Schema::table('floors', function (Blueprint $table): void {
            $table->dropColumn('layout_version');
        });
    }
};
