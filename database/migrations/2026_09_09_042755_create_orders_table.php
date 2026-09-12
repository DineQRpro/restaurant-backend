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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number', 50)->unique();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('dine_type_id')
                ->constrained('dine_types')
                ->restrictOnDelete();

            $table->foreignId('table_id')
                ->nullable()
                ->constrained('tables')
                ->restrictOnDelete();

            $table->foreignId('waiter_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status', 30)->default('placed');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['table_id', 'status']);
            $table->index(['waiter_id', 'status']);
            $table->index(['customer_id']);
            $table->index(['dine_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
