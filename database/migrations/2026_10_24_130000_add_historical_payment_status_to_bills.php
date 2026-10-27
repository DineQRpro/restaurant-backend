<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->unsignedTinyInteger('historical_pay_method')->nullable()->after('status');
            $table->unsignedTinyInteger('historical_pay_status')->nullable()->after('historical_pay_method');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropColumn(['historical_pay_method', 'historical_pay_status']);
        });
    }
};
