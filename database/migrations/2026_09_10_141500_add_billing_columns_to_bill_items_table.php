<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('bill_items', 'bill_id')) {
            return;
        }

        Schema::table('bill_items', function (Blueprint $table) {
            $table->foreignId('bill_id')
                ->after('id')
                ->constrained('bills')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->restrictOnDelete();

            $table->string('item_name', 255);

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 10, 2);

            $table->decimal('packing_fee', 10, 2)
                ->default(0);

            $table->index('bill_id');
            $table->index('order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('bill_items', 'bill_id')) {
            return;
        }

        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_id');
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropColumn([
                'item_name',
                'quantity',
                'unit_price',
                'packing_fee',
            ]);
        });
    }
};
