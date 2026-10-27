<?php

namespace App\Services\Reports;

use App\Models\PaymentMethod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BillPaymentTotals
{
    /**
     * Per-bill sums of actual bill_payments, split by payment method slug.
     *
     * Split settlements are multiple rows (Cash/UPI/Card), never one "Split" amount.
     */
    public function subquery(): Builder
    {
        $cash = PaymentMethod::SLUG_CASH;
        $upi = PaymentMethod::SLUG_UPI;
        $card = PaymentMethod::SLUG_CARD;
        $nc = PaymentMethod::SLUG_NON_CHARGEABLE;
        $credit = PaymentMethod::SLUG_CREDIT;

        return DB::table('bill_payments')
            ->join('payment_methods', 'payment_methods.id', '=', 'bill_payments.payment_method_id')
            ->groupBy('bill_payments.bill_id')
            ->selectRaw(
                'bill_payments.bill_id,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS cash_amount,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS upi_amount,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS card_amount,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS nc_amount,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS credit_amount,
                SUM(bill_payments.amount) AS paid_amount,
                COUNT(DISTINCT payment_methods.slug) AS method_count',
                [$cash, $upi, $card, $nc, $credit],
            );
    }

    /**
     * A bill whose only recorded tender is non-chargeable.
     */
    public function ncOnlySql(): string
    {
        return 'COALESCE(payment_totals.nc_amount, 0) > 0
            AND COALESCE(payment_totals.cash_amount, 0) = 0
            AND COALESCE(payment_totals.upi_amount, 0) = 0
            AND COALESCE(payment_totals.card_amount, 0) = 0
            AND COALESCE(payment_totals.credit_amount, 0) = 0';
    }

    /**
     * A bill whose only recorded tender is credit. Split bills that also
     * contain cash, UPI, or card are not credit bills.
     */
    public function creditOnlySql(): string
    {
        return 'COALESCE(payment_totals.credit_amount, 0) > 0
            AND COALESCE(payment_totals.cash_amount, 0) = 0
            AND COALESCE(payment_totals.upi_amount, 0) = 0
            AND COALESCE(payment_totals.card_amount, 0) = 0
            AND COALESCE(payment_totals.nc_amount, 0) = 0';
    }

    /**
     * SQL CASE matching VarsityKitchen payment-type labels.
     * Historical bills use tbl_bill.bill_pay_method and bill_pay_status.
     * A new Laravel bill is classified from its payment rows.
     */
    public function paymentTypeSql(): string
    {
        return "
            CASE
                WHEN bills.historical_pay_method IS NOT NULL THEN {$this->historicalPaymentTypeSql()}
                WHEN bills.status = 'cancelled' THEN 'Cancel'
                WHEN {$this->ncOnlySql()} THEN 'Non Chargeable'
                WHEN {$this->creditOnlySql()} THEN 'Credit'
                WHEN COALESCE(payment_totals.method_count, 0) > 1 THEN 'Split'
                WHEN COALESCE(payment_totals.cash_amount, 0) > 0 THEN 'Cash'
                WHEN COALESCE(payment_totals.upi_amount, 0) > 0 THEN 'UPI'
                WHEN COALESCE(payment_totals.card_amount, 0) > 0 THEN 'Card'
                WHEN COALESCE(payment_totals.nc_amount, 0) > 0 THEN 'Non Chargeable'
                WHEN bills.status = 'settled' THEN 'Paid'
                ELSE 'Unpaid'
            END
        ";
    }

    /**
     * Varsity bill-status report order:
     * method 4 or status 3, then method 6 or status 2, then method 7 or status 4,
     * then status 1, otherwise Unpaid. Payment totals are not consulted.
     */
    public function paymentStatusSql(): string
    {
        return "
            CASE
                WHEN bills.historical_pay_method IS NOT NULL THEN {$this->historicalPaymentStatusSql()}
                WHEN bills.status = 'cancelled' THEN 'Cancel'
                WHEN {$this->ncOnlySql()} THEN 'Non Chargeable'
                WHEN {$this->creditOnlySql()} THEN 'Credit'
                WHEN bills.status = 'settled' THEN 'Paid'
                ELSE 'Unpaid'
            END
        ";
    }

    public function historicalPaymentTypeSql(): string
    {
        return "
            CASE
                WHEN bills.historical_pay_method = 4 OR bills.historical_pay_status = 3 THEN 'Non Chargeable'
                WHEN bills.historical_pay_method = 6 OR bills.historical_pay_status = 2 THEN 'Credit'
                WHEN bills.historical_pay_method = 7 OR bills.historical_pay_status = 4 THEN 'Cancel'
                WHEN bills.historical_pay_method = 1 THEN 'Cash'
                WHEN bills.historical_pay_method = 2 THEN 'UPI'
                WHEN bills.historical_pay_method = 3 THEN 'Split'
                WHEN bills.historical_pay_method = 5 THEN 'Card'
                WHEN bills.historical_pay_status = 1 THEN 'Paid'
                ELSE 'Unpaid'
            END
        ";
    }

    public function historicalPaymentStatusSql(): string
    {
        return "
            CASE
                WHEN bills.historical_pay_method = 4 OR bills.historical_pay_status = 3 THEN 'Non Chargeable'
                WHEN bills.historical_pay_method = 6 OR bills.historical_pay_status = 2 THEN 'Credit'
                WHEN bills.historical_pay_method = 7 OR bills.historical_pay_status = 4 THEN 'Cancel'
                WHEN bills.historical_pay_status = 1 THEN 'Paid'
                ELSE 'Unpaid'
            END
        ";
    }
}
