<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floors', function (Blueprint $table): void {
            $table->unsignedTinyInteger('layout_columns')->default(8)->after('layout_version');
            $table->unsignedTinyInteger('layout_rows')->default(6)->after('layout_columns');
        });
    }

    public function down(): void
    {
        Schema::table('floors', function (Blueprint $table): void {
            $table->dropColumn(['layout_columns', 'layout_rows']);
        });
    }
};
