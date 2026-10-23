<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();
            $table->string('historical_customer_name', 150)->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('historical_customer_name');
            $table->dropForeign(['customer_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();
        });
    }
};
