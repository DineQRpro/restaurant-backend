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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();

            $table->string('bill_number', 50)->unique();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->decimal('subtotal', 10, 2)->default(0);

            $table->foreignId('discount_id')
                ->nullable()
                ->constrained('discounts')
                ->nullOnDelete();

            $table->decimal('other_discount_percentage', 5, 2)
                ->default(0);

            $table->string('other_discount_comment')->nullable();

            $table->decimal('discount_amount', 10, 2)
                ->default(0);

            $table->decimal('taxable_amount', 10, 2)
                ->default(0);

            $table->decimal('tax_amount', 10, 2)
                ->default(0);

            $table->decimal('round_off', 10, 2)
                ->default(0);

            $table->decimal('grand_total', 10, 2)
                ->default(0);

            $table->string('status', 30)->default('open');

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('discount_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
