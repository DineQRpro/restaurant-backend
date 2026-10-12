<?php

namespace App\Console\Commands;

use App\Enums\TableOperationalStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\BillPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransfer;
use App\Models\Table;
use App\Models\TableCleaningHold;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetTransactions extends Command
{
    protected $signature = 'dinepro:reset-transactions
        {--yes : Skip confirmation in non-production environments}';

    protected $description = 'Delete restaurant transaction data while preserving customers and master configuration';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('Transaction reset is disabled in production.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirmReset()) {
            $this->comment('Cancelled. No changes were made.');

            return self::SUCCESS;
        }

        $this->info('Resetting restaurant transactions...');

        $counts = DB::transaction(function (): array {
            $deleted = [
                'bill_payments' => BillPayment::query()->delete(),
                'bill_items' => BillItem::query()->delete(),
                'bills' => Bill::query()->delete(),
                'order_items' => OrderItem::query()->delete(),
                'order_transfers' => OrderTransfer::query()->delete(),
                'table_cleaning_holds' => TableCleaningHold::query()->delete(),
                'orders' => Order::query()->delete(),
            ];

            $tables = Table::query()->count();
            Table::query()->update(['status' => TableOperationalStatus::Available]);

            return [...$deleted, 'tables' => $tables];
        });

        $this->newLine();
        $this->line('Deleted:');
        $this->line('  '.$counts['bill_payments'].' bill payments');
        $this->line('  '.$counts['bill_items'].' bill items');
        $this->line('  '.$counts['bills'].' bills');
        $this->line('  '.$counts['order_items'].' order items');
        $this->line('  '.$counts['order_transfers'].' order transfers');
        $this->line('  '.$counts['table_cleaning_holds'].' table cleaning holds');
        $this->line('  '.$counts['orders'].' orders');
        $this->newLine();
        $this->line('Reset:');
        $this->line('  '.$counts['tables'].' physical tables → Available');
        $this->newLine();
        $this->info('Restaurant transaction data reset successfully.');

        return self::SUCCESS;
    }

    private function confirmReset(): bool
    {
        $this->warn('This will permanently delete all restaurant transaction data:');
        $this->line('- Orders');
        $this->line('- Order Items');
        $this->line('- Bills');
        $this->line('- Bill Items');
        $this->line('- Bill Payments');
        $this->newLine();
        $this->line('Customers, users, menu, tables and master data will be preserved.');

        return $this->confirm('Continue?', false);
    }
}
