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

        return DB::table('bill_payments')
            ->join('payment_methods', 'payment_methods.id', '=', 'bill_payments.payment_method_id')
            ->groupBy('bill_payments.bill_id')
            ->selectRaw(
                'bill_payments.bill_id,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS cash_amount,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS upi_amount,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS card_amount,
                SUM(CASE WHEN payment_methods.slug = ? THEN bill_payments.amount ELSE 0 END) AS nc_amount,
                SUM(bill_payments.amount) AS paid_amount,
                COUNT(DISTINCT payment_methods.slug) AS method_count',
                [$cash, $upi, $card, $nc],
            );
    }

    /**
     * SQL CASE matching VarsityKitchen payment-type labels, mapped onto Laravel
     * bill_payments + bill status. Credit/Cancel are not operational methods.
     */
    public function paymentTypeSql(): string
    {
        return "
            CASE
                WHEN COALESCE(payment_totals.nc_amount, 0) > 0
                    AND COALESCE(payment_totals.cash_amount, 0) = 0
                    AND COALESCE(payment_totals.upi_amount, 0) = 0
                    AND COALESCE(payment_totals.card_amount, 0) = 0
                    THEN 'Non Chargeable'
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

    public function paymentStatusSql(): string
    {
        return "
            CASE
                WHEN COALESCE(payment_totals.nc_amount, 0) > 0
                    AND COALESCE(payment_totals.cash_amount, 0) = 0
                    AND COALESCE(payment_totals.upi_amount, 0) = 0
                    AND COALESCE(payment_totals.card_amount, 0) = 0
                    THEN 'Non Chargeable'
                WHEN bills.status = 'settled' THEN 'Paid'
                ELSE 'Unpaid'
            END
        ";
    }
}
