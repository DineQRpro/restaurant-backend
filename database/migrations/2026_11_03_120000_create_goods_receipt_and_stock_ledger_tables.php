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
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number', 20);
            $table->char('idempotency_key', 36);
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('vendor_code_snapshot', 20);
            $table->string('vendor_name_snapshot', 150);
            $table->string('vendor_bill_number', 50);
            $table->string('vendor_bill_key', 50);
            $table->date('vendor_bill_date');
            $table->date('receipt_date');
            $table->string('payment_status', 30);
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('gst_total', 14, 2);
            $table->decimal('grand_total', 14, 2);
            $table->decimal('paid_amount', 14, 2);
            $table->decimal('outstanding_amount', 14, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->unique('receipt_number');
            $table->unique('idempotency_key');
            $table->unique(['vendor_id', 'vendor_bill_key']);
            $table->index('receipt_date');
            $table->index('status');
            $table->index('payment_status');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('item_code_snapshot', 50);
            $table->string('item_name_snapshot', 150);
            $table->string('category_name_snapshot', 150);
            $table->string('unit_name_snapshot', 100);
            $table->string('unit_symbol_snapshot', 20);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2);
            $table->decimal('gst_amount', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index('inventory_item_id');
        });

        Schema::create('inventory_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->decimal('quantity_in', 12, 3)->default(0);
            $table->decimal('quantity_out', 12, 3)->default(0);
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained('goods_receipt_items')->restrictOnDelete();
            $table->unsignedBigInteger('reversal_of_movement_id')->nullable();
            $table->timestamp('posted_at');
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('reversal_of_movement_id')
                ->references('id')
                ->on('inventory_stock_movements')
                ->restrictOnDelete();
            $table->unique(['goods_receipt_item_id', 'movement_type'], 'stock_movements_line_type_unique');
            $table->unique('reversal_of_movement_id', 'stock_movements_reversal_unique');
            $table->index(['inventory_item_id', 'posted_at']);
        });

        $this->addChecks();

        $now = now();
        DB::table('document_sequences')->insert([
            'name' => DocumentSequence::GOODS_RECEIPT,
            'next_number' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');

        DB::table('document_sequences')->where('name', DocumentSequence::GOODS_RECEIPT)->delete();
    }

    private function addChecks(): void
    {
        $checks = [
            'goods_receipts' => [
                "payment_status in ('paid', 'unpaid', 'partially_paid', 'credit')",
                "status in ('draft', 'posted', 'voided')",
                'subtotal >= 0 AND gst_total >= 0 AND grand_total >= 0 AND paid_amount >= 0 AND outstanding_amount >= 0',
            ],
            'goods_receipt_items' => [
                'quantity > 0',
                'unit_price >= 0',
                'gst_rate >= 0 AND gst_rate <= 100',
                'taxable_amount >= 0 AND gst_amount >= 0 AND line_total >= 0',
            ],
            'inventory_stock_movements' => [
                "movement_type in ('receipt', 'reversal')",
                '(quantity_in > 0 AND quantity_out = 0) OR (quantity_out > 0 AND quantity_in = 0)',
            ],
        ];

        foreach ($checks as $table => $expressions) {
            foreach ($expressions as $index => $expression) {
                $name = "{$table}_check_{$index}";

                if (Schema::getConnection()->getDriverName() === 'sqlite') {
                    $triggerExpression = preg_replace(
                        '/\b(payment_status|status|subtotal|gst_total|grand_total|paid_amount|outstanding_amount|quantity|unit_price|gst_rate|taxable_amount|gst_amount|line_total|movement_type|quantity_in|quantity_out)\b/',
                        'NEW.$1',
                        $expression,
                    );

                    foreach (['insert', 'update'] as $event) {
                        DB::statement("create trigger {$name}_{$event} before {$event} on {$table} when not ({$triggerExpression}) begin select raise(abort, '{$name}'); end");
                    }

                    continue;
                }

                DB::statement("alter table {$table} add constraint {$name} check ({$expression})");
            }
        }
    }
};
