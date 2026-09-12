<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_id')
                ->constrained('bills')
                ->cascadeOnDelete();

            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();

            $table->decimal('amount', 10, 2);

            $table->string('reference_number', 150)->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->foreignId('captain_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('bill_id');
            $table->index('payment_method_id');
            $table->index('captain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};