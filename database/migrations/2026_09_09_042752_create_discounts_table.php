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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);

            $table->enum('type', ['custom', 'fixed']);

            $table->decimal('value', 10, 2)->default(0);

            $table->unsignedInteger('min_level')->default(0);

            $table->unsignedInteger('max_level')->default(0);

            $table->enum('applies_to', ['product', 'order'])
                ->default('order');

            $table->dateTime('start_date')->nullable();

            $table->dateTime('end_date')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('type');
            $table->index('applies_to');
            $table->index('is_active');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};