<?php

namespace App\Services\Inventory;

use App\Enums\GoodsReceiptPaymentStatus;
use App\Exceptions\BusinessConflictException;

final class InventoryMoney
{
    /**
     * @return array{taxable_amount: string, gst_amount: string, line_total: string}
     */
    public static function line(string $quantity, string $unitPrice, string $gstRate): array
    {
        $taxable = self::roundHalfUp(bcmul(self::scale($quantity, 3), self::scale($unitPrice, 2), 6));
        $gst = self::roundHalfUp(bcdiv(bcmul($taxable, self::scale($gstRate, 2), 6), '100', 6));

        return [
            'taxable_amount' => $taxable,
            'gst_amount' => $gst,
            'line_total' => bcadd($taxable, $gst, 2),
        ];
    }

    /**
     * @param  list<array{taxable_amount: string, gst_amount: string, line_total: string}>  $lines
     * @return array{subtotal: string, gst_total: string, grand_total: string}
     */
    public static function header(array $lines): array
    {
        $subtotal = '0.00';
        $gstTotal = '0.00';
        $grand = '0.00';

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, $line['taxable_amount'], 2);
            $gstTotal = bcadd($gstTotal, $line['gst_amount'], 2);
            $grand = bcadd($grand, $line['line_total'], 2);
        }

        return [
            'subtotal' => $subtotal,
            'gst_total' => $gstTotal,
            'grand_total' => $grand,
        ];
    }

    public static function outstanding(string $grandTotal, string $paidAmount): string
    {
        return bcsub(self::scale($grandTotal, 2), self::scale($paidAmount, 2), 2);
    }

    public static function quantity(mixed $value): string
    {
        return self::scale($value === null || $value === '' ? '0' : (string) $value, 3);
    }

    public static function money(mixed $value): string
    {
        return self::scale($value === null || $value === '' ? '0' : (string) $value, 2);
    }

    public static function assertPayment(GoodsReceiptPaymentStatus $status, string $paidAmount, string $grandTotal): void
    {
        $paid = self::money($paidAmount);
        $grand = self::money($grandTotal);

        if (bccomp($paid, '0.00', 2) < 0) {
            throw new BusinessConflictException('Paid amount cannot be negative.');
        }

        if (bccomp($grand, '0.00', 2) === 0) {
            if ($status === GoodsReceiptPaymentStatus::PartiallyPaid) {
                throw new BusinessConflictException('A zero-total receipt cannot be partially paid.');
            }

            if (bccomp($paid, '0.00', 2) !== 0) {
                throw new BusinessConflictException('Paid amount must be 0.00 when the invoice total is 0.00.');
            }

            return;
        }

        $matches = match ($status) {
            GoodsReceiptPaymentStatus::Paid => bccomp($paid, $grand, 2) === 0,
            GoodsReceiptPaymentStatus::Unpaid, GoodsReceiptPaymentStatus::Credit => bccomp($paid, '0.00', 2) === 0,
            GoodsReceiptPaymentStatus::PartiallyPaid => bccomp($paid, '0.00', 2) > 0 && bccomp($paid, $grand, 2) < 0,
        };

        if (! $matches) {
            throw new BusinessConflictException(match ($status) {
                GoodsReceiptPaymentStatus::Paid => 'Paid amount must equal the invoice total.',
                GoodsReceiptPaymentStatus::Unpaid => 'Unpaid receipts must have a paid amount of 0.00.',
                GoodsReceiptPaymentStatus::Credit => 'Credit receipts must have a paid amount of 0.00. Credit is not the same as Unpaid.',
                GoodsReceiptPaymentStatus::PartiallyPaid => 'Partially paid amount must be greater than 0.00 and less than the invoice total.',
            });
        }
    }

    public static function roundHalfUp(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        $absolute = $negative ? substr($amount, 1) : $amount;
        $shifted = bcadd($absolute, '0.005', 6);
        [$whole, $fraction] = array_pad(explode('.', $shifted, 2), 2, '0');
        $rounded = $whole.'.'.str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? '-'.$rounded : $rounded;
    }

    private static function scale(string $value, int $scale): string
    {
        if (! is_numeric($value)) {
            return $scale === 2 ? '0.00' : '0.000';
        }

        return bcadd($value, '0', $scale);
    }
}
