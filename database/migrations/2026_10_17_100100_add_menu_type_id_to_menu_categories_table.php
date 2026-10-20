<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->foreignId('menu_type_id')
                ->nullable()
                ->after('id')
                ->constrained('menu_types')
                ->restrictOnDelete();
        });

        $foodId = DB::table('menu_types')->where('slug', 'food')->value('id');

        DB::table('menu_categories')
            ->whereNull('menu_type_id')
            ->update(['menu_type_id' => $foodId]);

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_type_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_type_id');
        });
    }
};
