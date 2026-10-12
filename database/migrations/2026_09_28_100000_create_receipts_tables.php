<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->date('receipt_date');
            $table->string('customer_name', 150);
            $table->string('mobile', 20);
            $table->text('program');
            $table->text('other_instructions')->nullable();
            $table->unsignedInteger('pax_count')->default(0);
            $table->decimal('advance_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->boolean('include_gst')->default(false);
            $table->timestamps();

            $table->index('receipt_date');
        });

        Schema::create('receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')
                ->constrained('receipts')
                ->cascadeOnDelete();
            $table->string('item_name', 255);
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('rate_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
    }
};
