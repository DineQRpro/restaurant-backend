<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('target_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('from_table_id')->constrained('tables')->restrictOnDelete();
            $table->foreignId('to_table_id')->constrained('tables')->restrictOnDelete();
            $table->string('type');
            $table->unsignedInteger('source_pax');
            $table->unsignedInteger('source_allocated_seats');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_order_id', 'transferred_at']);
            $table->index(['from_table_id', 'to_table_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_transfers');
    }
};
