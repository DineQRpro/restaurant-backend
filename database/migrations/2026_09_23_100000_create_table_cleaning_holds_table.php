<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_cleaning_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('reason');
            $table->unsignedTinyInteger('seats');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('cleaned_at')->nullable();
            $table->timestamps();

            $table->index(['table_id', 'cleaned_at']);
            $table->index(['expires_at', 'cleaned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_cleaning_holds');
    }
};
