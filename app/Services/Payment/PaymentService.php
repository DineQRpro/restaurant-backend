<?php

namespace App\Services\Payment;

use App\Enums\BillStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Record a payment against an open bill.
     *
     * captain_id is set only when the authenticated actor's role slug is
     * `captain`. Admin and counter users leave it null so we do not invent a
     * floor captain for a payment they recorded at the desk.
     *
     * The Admin portal is restricted to the admin role, so payments from this
     * UI will typically store captain_id as null.
     *
     * This method does not change table status or order status. Checkout already
     * finalized the order and moved the table to Cleaning.
     *
     * Split is a UI mode, not a bill_payments method. Record split children
     * through recordSplit() instead.
     *
     * @param  array{payment_method_id: int, amount: mixed, reference_number?: string|null, notes?: string|null}  $data
     */
    public function record(Bill $bill, array $data, User $actor): BillPayment
    {
        return DB::transaction(function () use ($bill, $data, $actor) {
            $bill = $this->lockOpenBill($bill);
            $method = $this->resolveRecordableMethod((int) $data['payment_method_id']);

            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw new BusinessConflictException('Payment amount must be greater than zero.');
            }

            $paidTotal = $this->paidTotal($bill);
            $balance = $this->balance($bill, $paidTotal);

            if ($this->toCents($amount) > $this->toCents($balance)) {
                throw new BusinessConflictException('Payment amount cannot exceed the remaining balance.');
            }

            $payment = $this->createPayment($bill, $method, [
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], $actor);

            $this->settleIfPaid($bill);

            return $payment;
        });
    }

    /**
     * Record a two-method split against the remaining bill balance.
     *
     * The two rows are inserted in one transaction. If either insert fails,
     * neither payment is kept.
     *
     * @param  list<array{payment_method_id: int, amount: mixed, reference_number?: string|null, notes?: string|null}>  $payments
     * @return Collection<int, BillPayment>
     */
    public function recordSplit(Bill $bill, array $payments, User $actor): Collection
    {
        return DB::transaction(function () use ($bill, $payments, $actor) {
            $bill = $this->lockOpenBill($bill);

            if (count($payments) !== 2) {
                throw new BusinessConflictException('Split payment requires exactly two payment methods.');
            }

            $paidTotal = $this->paidTotal($bill);
            $balance = $this->balance($bill, $paidTotal);
            $balanceCents = $this->toCents($balance);

            if ($balanceCents <= 0) {
                throw new BusinessConflictException('This bill has no remaining balance.');
            }

            $methodIds = [];
            $normalized = [];
            $sumCents = 0;

            foreach ($payments as $payload) {
                $method = $this->resolveRecordableMethod((int) $payload['payment_method_id']);

                if (! $method->isSplittable()) {
                    throw new BusinessConflictException('The selected payment method cannot be used in a split payment.');
                }

                if (in_array($method->id, $methodIds, true)) {
                    throw new BusinessConflictException('Split payment methods must be different.');
                }

                $amount = round((float) $payload['amount'], 2);

                if ($amount <= 0) {
                    throw new BusinessConflictException('Payment amount must be greater than zero.');
                }

                $methodIds[] = $method->id;
                $sumCents += $this->toCents($amount);
                $normalized[] = [
                    'method' => $method,
                    'amount' => $amount,
                    'reference_number' => $payload['reference_number'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ];
            }

            if ($sumCents > $balanceCents) {
                throw new BusinessConflictException('Payment amount cannot exceed the remaining balance.');
            }

            if ($sumCents < $balanceCents) {
                throw new BusinessConflictException('Split payment total must equal the remaining balance.');
            }

            $created = collect();

            foreach ($normalized as $item) {
                $created->push($this->createPayment($bill, $item['method'], $item, $actor));
            }

            $this->settleIfPaid($bill);

            return $created;
        });
    }

    public function paidTotal(Bill $bill): float
    {
        return round((float) $bill->payments()->sum('amount'), 2);
    }

    public function balance(Bill $bill, ?float $paidTotal = null): float
    {
        $paid = $paidTotal ?? $this->paidTotal($bill);

        return round(max(0, (float) $bill->grand_total - $paid), 2);
    }

    /**
     * Sum of recorded bill_payments for today's calendar date.
     *
     * Laravel has no finalized business-day helper. Core PHP Amount Received
     * used CURDATE() on bills, not getBusinessDayRange(). We use the same
     * calendar-day window, but only on actual payment rows (paid_at).
     */
    public function amountReceivedToday(): float
    {
        return round((float) BillPayment::query()
            ->whereDate('paid_at', now()->toDateString())
            ->sum('amount'), 2);
    }

    private function lockOpenBill(Bill $bill): Bill
    {
        /** @var Bill $bill */
        $bill = Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

        if ($bill->status !== BillStatus::Open) {
            throw new BusinessConflictException('This bill is not open for payment.');
        }

        return $bill;
    }

    private function resolveRecordableMethod(int $paymentMethodId): PaymentMethod
    {
        $method = PaymentMethod::query()->whereKey($paymentMethodId)->first();

        if (! $method || ! $method->is_active) {
            throw new BusinessConflictException('The selected payment method is not available.');
        }

        if ($method->isSplit()) {
            throw new BusinessConflictException('Split payments must be recorded as two separate methods.');
        }

        return $method;
    }

    /**
     * @param  array{amount: mixed, reference_number?: string|null, notes?: string|null}  $data
     */
    private function createPayment(Bill $bill, PaymentMethod $method, array $data, User $actor): BillPayment
    {
        return BillPayment::query()->create([
            'bill_id' => $bill->id,
            'payment_method_id' => $method->id,
            'amount' => round((float) $data['amount'], 2),
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'paid_at' => now(),
            'captain_id' => $this->captainIdFor($actor),
        ]);
    }

    private function settleIfPaid(Bill $bill): void
    {
        $newPaidTotal = $this->paidTotal($bill);

        if ($newPaidTotal >= round((float) $bill->grand_total, 2)) {
            $bill->update([
                'status' => BillStatus::Settled,
                'settled_at' => now(),
            ]);
        }
    }

    private function toCents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function captainIdFor(User $actor): ?int
    {
        $actor->loadMissing('role');

        return $actor->role?->slug === 'captain' ? $actor->id : null;
    }
}
