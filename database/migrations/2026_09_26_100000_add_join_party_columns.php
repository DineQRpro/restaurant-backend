<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('joined_into_order_id')
                ->nullable()
                ->after('merged_tables')
                ->constrained('orders')
                ->nullOnDelete();
            $table->timestamp('joined_at')->nullable()->after('joined_into_order_id');
            $table->index('joined_into_order_id');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('origin_order_id')
                ->nullable()
                ->after('order_id')
                ->constrained('orders')
                ->nullOnDelete();
        });

        DB::table('order_items')
            ->whereNull('origin_order_id')
            ->update(['origin_order_id' => DB::raw('order_id')]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('origin_order_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('joined_into_order_id');
            $table->dropColumn('joined_at');
        });
    }
};
