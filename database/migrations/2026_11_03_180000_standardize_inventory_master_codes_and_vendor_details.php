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
        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->string('code', 20)->nullable()->after('id');
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('shop_name', 150)->nullable()->after('name');
            $table->string('poc_name', 150)->nullable()->after('shop_name');
            $table->string('bank_name', 150)->nullable()->after('gst_number');
            $table->string('bank_account_number', 34)->nullable()->after('bank_name');
            $table->string('ifsc_code', 11)->nullable()->after('bank_account_number');
        });

        $now = now();

        foreach ([
            DocumentSequence::INVENTORY_CATEGORY,
            DocumentSequence::UNIT_OF_MEASURE,
            DocumentSequence::INVENTORY_ITEM,
        ] as $name) {
            $exists = DB::table('document_sequences')->where('name', $name)->exists();

            if (! $exists) {
                DB::table('document_sequences')->insert([
                    'name' => $name,
                    'next_number' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (DB::table('inventory_categories')->whereNull('code')->orderBy('id')->pluck('id') as $id) {
            DB::table('inventory_categories')->where('id', $id)->update([
                'code' => $this->allocate(DocumentSequence::INVENTORY_CATEGORY, 'CAT'),
            ]);
        }

        foreach (DB::table('units_of_measure')->whereNull('code')->orderBy('id')->pluck('id') as $id) {
            DB::table('units_of_measure')->where('id', $id)->update([
                'code' => $this->allocate(DocumentSequence::UNIT_OF_MEASURE, 'UOM'),
            ]);
        }

        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->unique('code');
        });

        Schema::table('inventory_categories', function (Blueprint $table): void {
            $table->string('code', 50)->nullable(false)->change();
        });

        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->string('code', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::table('inventory_categories', function (Blueprint $table): void {
            $table->string('code', 50)->nullable()->change();
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn([
                'shop_name',
                'poc_name',
                'bank_name',
                'bank_account_number',
                'ifsc_code',
            ]);
        });

        DB::table('document_sequences')->whereIn('name', [
            DocumentSequence::INVENTORY_CATEGORY,
            DocumentSequence::UNIT_OF_MEASURE,
            DocumentSequence::INVENTORY_ITEM,
        ])->delete();
    }

    private function allocate(string $sequence, string $prefix): string
    {
        return DB::transaction(function () use ($sequence, $prefix): string {
            $row = DB::table('document_sequences')->where('name', $sequence)->lockForUpdate()->first();
            $number = (int) $row->next_number;

            DB::table('document_sequences')->where('name', $sequence)->update([
                'next_number' => $number + 1,
                'updated_at' => now(),
            ]);

            return sprintf('%s-%06d', $prefix, $number);
        });
    }
};
