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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('allocated_seats')->nullable()->after('pax');
            $table->string('partition_label', 8)->nullable()->after('allocated_seats');
            $table->timestamp('seating_cleaned_at')->nullable()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['allocated_seats', 'partition_label', 'seating_cleaned_at']);
        });
    }
};
