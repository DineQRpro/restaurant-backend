<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->foreignId('category_id')
                ->constrained('menu_categories')
                ->restrictOnDelete();
            $table->string('dietary_type')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_chef_special')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('spicy_level')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('dietary_type');
            $table->index('is_available');
            $table->index('is_chef_special');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
