<?php

use App\Models\DocumentSequence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
            $table->unique('code');
            $table->index('is_active');
        });

        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('symbol', 20);
            $table->string('dimension', 30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
            $table->unique('symbol');
            $table->index('is_active');
        });

        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('gst_number', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->index('name');
            $table->index('is_active');
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->foreignId('inventory_category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('reorder_level', 12, 3);
            $table->decimal('maximum_level', 12, 3);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->index('name');
            $table->index('is_active');
        });

        $now = now();
        DB::table('document_sequences')->insert([
            'name' => DocumentSequence::VENDOR,
            'next_number' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('inventory_categories');

        DB::table('document_sequences')->where('name', DocumentSequence::VENDOR)->delete();
    }
};
