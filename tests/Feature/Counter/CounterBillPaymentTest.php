<?php

namespace Tests\Feature\Counter;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\BillPayment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Services\Payment\PaymentService;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesRestaurantOrders;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class CounterBillPaymentTest extends TestCase
{
    use CreatesRestaurantOrders, InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(PaymentMethodSeeder::class);
    }

    public function test_counter_with_settle_permission_sees_active_payment_methods(): void
    {
        PaymentMethod::factory()->inactive()->create(['name' => 'Old Cheque', 'slug' => 'old-cheque']);
        $this->createCheckoutBill(['order_number' => 'ORD-C1005']);

        $html = $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('ORD-C1005')
            ->assertSee('Choose Method')
            ->assertSee('💵 Cash')
            ->assertSee('📱 UPI')
            ->assertSee('💳 Card')
            ->assertSee('🔀 Split')
            ->assertSee('Non-chargeable')
            ->assertDontSee('Old Cheque')
            ->getContent();

        $this->assertStringContainsString('class="payment-mode-select"', $html);
        $this->assertStringContainsString('id="counter-payment-modal"', $html);
        $this->assertStringContainsString('id="counter-split-modal"', $html);
        $this->assertStringContainsString('data-slug="split"', $html);
        $this->assertStringNotContainsString('data-slug="old-cheque"', $html);

        preg_match_all('/id="split_method_[12]".*?<\/select>/s', $html, $splitSelects);
        $this->assertCount(2, $splitSelects[0]);
        foreach ($splitSelects[0] as $selectHtml) {
            $this->assertStringNotContainsString('data-slug="split"', $selectHtml);
            $this->assertStringNotContainsString('data-slug="non-chargeable"', $selectHtml);
            $this->assertStringContainsString('data-slug="cash"', $selectHtml);
            $this->assertStringContainsString('data-slug="upi"', $selectHtml);
            $this->assertStringContainsString('data-slug="card"', $selectHtml);
        }
    }

    public function test_visiting_the_dashboard_does_not_record_payment(): void
    {
        [$order, $bill] = $this->createCheckoutBill(['order_number' => 'ORD-NOPAY'], ['grand_total' => 850]);

        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('Choose Method');

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
        $this->assertSame(OrderStatus::Checkout, $order->fresh()->status);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
    }

    public function test_cash_payment_records_and_settles_the_bill(): void
    {
        [$order, $bill] = $this->createCheckoutBill(['order_number' => 'ORD-CASH'], ['grand_total' => 850]);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_CASH)->id,
                'amount' => '850.00',
            ])
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHas('success', 'Payment recorded successfully.');

        $this->assertPaidInFull($order, $bill, 1);
        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'payment_method_id' => $this->method(PaymentMethod::SLUG_CASH)->id,
            'amount' => 850,
            'captain_id' => null,
        ]);

        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('ORD-CASH')
            ->assertSee('Cash')
            ->assertSee('Paid')
            ->assertDontSee('class="payment-mode-select"', false);
    }

    public function test_upi_payment_records_optional_reference(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 500]);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_UPI)->id,
                'amount' => '500.00',
                'reference_number' => 'UPI123',
            ])
            ->assertRedirect(route('counter.dashboard'));

        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'payment_method_id' => $this->method(PaymentMethod::SLUG_UPI)->id,
            'reference_number' => 'UPI123',
            'captain_id' => null,
        ]);
        $this->assertSame(BillStatus::Settled, $bill->fresh()->status);
    }

    public function test_card_payment_records_optional_reference(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 500]);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_CARD)->id,
                'amount' => '500.00',
                'reference_number' => 'CARD-99',
            ])
            ->assertRedirect(route('counter.dashboard'));

        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'payment_method_id' => $this->method(PaymentMethod::SLUG_CARD)->id,
            'reference_number' => 'CARD-99',
            'captain_id' => null,
        ]);
    }

    public function test_non_chargeable_records_the_remaining_balance(): void
    {
        [$order, $bill] = $this->createCheckoutBill([], ['grand_total' => 300]);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_NON_CHARGEABLE)->id,
                'amount' => '300.00',
            ])
            ->assertRedirect(route('counter.dashboard'));

        $this->assertPaidInFull($order, $bill, 1);
        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'payment_method_id' => $this->method(PaymentMethod::SLUG_NON_CHARGEABLE)->id,
            'amount' => 300,
            'captain_id' => null,
        ]);
    }

    public function test_normal_payment_pays_the_remaining_balance_after_partial(): void
    {
        [$order, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);
        $this->recordPayment($bill, $this->method(PaymentMethod::SLUG_CASH), 200);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_UPI)->id,
                'amount' => '650.00',
            ])
            ->assertRedirect(route('counter.dashboard'));

        $this->assertPaidInFull($order, $bill, 2);
    }

    public function test_split_cannot_be_recorded_as_a_single_payment(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 100]);

        $this->actingAs($this->userWithRole('counter'))
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_SPLIT)->id,
                'amount' => '100.00',
            ])
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHasErrors('payment_method_id');

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
    }

    public function test_cash_plus_upi_split_creates_two_payments_and_settles(): void
    {
        $this->assertValidSplitSettles(
            PaymentMethod::SLUG_CASH,
            PaymentMethod::SLUG_UPI,
            '400.00',
            '450.00',
            null,
            'UPI123',
            'Cash + UPI',
        );
    }

    public function test_cash_plus_card_split_is_accepted(): void
    {
        $this->assertValidSplitSettles(
            PaymentMethod::SLUG_CASH,
            PaymentMethod::SLUG_CARD,
            '300.00',
            '550.00',
            null,
            'CARD-1',
            'Cash + Card',
        );
    }

    public function test_upi_plus_card_split_is_accepted(): void
    {
        $this->assertValidSplitSettles(
            PaymentMethod::SLUG_UPI,
            PaymentMethod::SLUG_CARD,
            '200.00',
            '650.00',
            'UPI-9',
            'CARD-9',
            'UPI + Card',
        );
    }

    public function test_same_split_method_twice_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);

        $this->actingAs($this->userWithRole('counter'))
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_CASH),
                '400.00',
                '450.00',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHasErrors('payments.1.payment_method_id');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_split_cannot_be_selected_as_a_child_method(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);

        $this->actingAs($this->userWithRole('counter'))
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_SPLIT),
                '400.00',
                '450.00',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHasErrors('payments.1.payment_method_id');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_non_chargeable_cannot_be_used_in_a_split(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);

        $this->actingAs($this->userWithRole('counter'))
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_NON_CHARGEABLE),
                '400.00',
                '450.00',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHasErrors('payments.1.payment_method_id');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_inactive_split_method_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);
        $inactive = PaymentMethod::factory()->inactive()->create(['name' => 'Cheque', 'slug' => 'cheque']);

        $this->actingAs($this->userWithRole('counter'))
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $inactive,
                '400.00',
                '450.00',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHasErrors('payments.1.payment_method_id');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_zero_and_negative_split_amounts_are_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);
        $counter = $this->userWithRole('counter');

        $this->actingAs($counter)
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_UPI),
                '0',
                '850.00',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHasErrors('payments.0.amount');

        $this->actingAs($counter)
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_UPI),
                '400.00',
                '-10',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHasErrors('payments.1.amount');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_split_total_less_than_balance_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);

        $this->actingAs($this->userWithRole('counter'))
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_UPI),
                '400.00',
                '400.00',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
    }

    public function test_split_total_greater_than_balance_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);

        $this->actingAs($this->userWithRole('counter'))
            ->from(route('counter.dashboard'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_UPI),
                '400.00',
                '500.00',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
    }

    public function test_split_failure_rolls_back_both_rows(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 850]);
        $count = 0;
        $dispatcher = BillPayment::getEventDispatcher();
        BillPayment::flushEventListeners();
        BillPayment::created(function () use (&$count): void {
            $count++;
            if ($count === 2) {
                throw new RuntimeException('second payment failed');
            }
        });

        try {
            app(PaymentService::class)->recordSplit($bill, $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_UPI),
                '400.00',
                '450.00',
            )['payments'], $this->userWithRole('counter'));
            $this->fail('Split payment should have failed.');
        } catch (RuntimeException) {
            // expected
        } finally {
            BillPayment::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
        $this->assertNull($bill->fresh()->settled_at);
    }

    public function test_already_settled_bill_cannot_be_paid_again(): void
    {
        [, $bill] = $this->createCheckoutBill([], [
            'grand_total' => 100,
            'status' => BillStatus::Settled,
            'settled_at' => now(),
        ]);
        $this->recordPayment($bill, $this->method(PaymentMethod::SLUG_CASH), 100);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_UPI)->id,
                'amount' => '100.00',
            ])
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_UPI),
                '40.00',
                '60.00',
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHas('error');

        $this->assertSame(1, BillPayment::query()->count());
    }

    public function test_overpayment_is_rejected(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 200]);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_CASH)->id,
                'amount' => '201.00',
            ])
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
    }

    public function test_missing_permission_returns_403(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 100]);
        $counter = $this->userWithRole('counter');
        $permission = Permission::query()->where('slug', 'bills.settle')->firstOrFail();
        $counter->role->permissions()->detach($permission->id);

        $this->actingAs($counter->fresh())
            ->post(route('counter.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_CASH)->id,
                'amount' => '100.00',
            ])
            ->assertForbidden();

        $this->actingAs($counter->fresh())
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method(PaymentMethod::SLUG_CASH),
                $this->method(PaymentMethod::SLUG_UPI),
                '40.00',
                '60.00',
            ))
            ->assertForbidden();

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_guest_is_redirected_from_counter_payment_routes(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 100]);

        $this->post(route('counter.bills.payments.store', $bill), [
            'payment_method_id' => $this->method(PaymentMethod::SLUG_CASH)->id,
            'amount' => '100.00',
        ])->assertRedirect('/counter/login');

        $this->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
            $this->method(PaymentMethod::SLUG_CASH),
            $this->method(PaymentMethod::SLUG_UPI),
            '40.00',
            '60.00',
        ))->assertRedirect('/counter/login');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_admin_payment_still_works_when_split_exists(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 80]);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.bills.payments.store', $bill), [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_CASH)->id,
                'amount' => 80,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'captain_id' => null,
        ]);
        $this->assertSame(BillStatus::Settled, $bill->fresh()->status);
    }

    public function test_admin_dashboard_excludes_split_from_the_selector(): void
    {
        $this->createCheckoutBill(['order_number' => 'ORD-ADMIN']);

        $html = $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('ORD-ADMIN')
            ->assertSee('💵 Cash')
            ->getContent();

        $this->assertStringNotContainsString('data-slug="split"', $html);
        $this->assertStringNotContainsString('id="counter-split-modal"', $html);
    }

    public function test_split_service_rejects_posting_the_split_method_directly(): void
    {
        [, $bill] = $this->createCheckoutBill([], ['grand_total' => 50]);

        try {
            app(PaymentService::class)->record($bill, [
                'payment_method_id' => $this->method(PaymentMethod::SLUG_SPLIT)->id,
                'amount' => 50,
            ], $this->userWithRole('counter'));
            $this->fail('Split should not be recorded as a single payment.');
        } catch (BusinessConflictException $e) {
            $this->assertSame('Split payments must be recorded as two separate methods.', $e->getMessage());
        }

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame(BillStatus::Open, $bill->fresh()->status);
    }

    public function test_counter_dashboard_still_renders_after_payment_routes_exist(): void
    {
        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('Counter Dashboard')
            ->assertSee('Track Orders');
    }

    private function method(string $slug): PaymentMethod
    {
        return PaymentMethod::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @return array{payments: list<array{payment_method_id: int, amount: string, reference_number: string|null}>}
     */
    private function splitPayload(
        PaymentMethod $first,
        PaymentMethod $second,
        string $amount1,
        string $amount2,
        ?string $reference1 = null,
        ?string $reference2 = null,
    ): array {
        return [
            'payments' => [
                [
                    'payment_method_id' => $first->id,
                    'amount' => $amount1,
                    'reference_number' => $reference1,
                ],
                [
                    'payment_method_id' => $second->id,
                    'amount' => $amount2,
                    'reference_number' => $reference2,
                ],
            ],
        ];
    }

    private function assertValidSplitSettles(
        string $firstSlug,
        string $secondSlug,
        string $amount1,
        string $amount2,
        ?string $reference1,
        ?string $reference2,
        string $displayLabel,
    ): void {
        [$order, $bill] = $this->createCheckoutBill(['order_number' => 'ORD-SPLIT'], ['grand_total' => 850]);

        $this->actingAs($this->userWithRole('counter'))
            ->post(route('counter.bills.split-payments.store', $bill), $this->splitPayload(
                $this->method($firstSlug),
                $this->method($secondSlug),
                $amount1,
                $amount2,
                $reference1,
                $reference2,
            ))
            ->assertRedirect(route('counter.dashboard'))
            ->assertSessionHas('success', 'Split payment recorded successfully.');

        $this->assertDatabaseCount('bill_payments', 2);
        $this->assertPaidInFull($order, $bill, 2);
        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'payment_method_id' => $this->method($firstSlug)->id,
            'amount' => $amount1,
            'reference_number' => $reference1,
            'captain_id' => null,
        ]);
        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $bill->id,
            'payment_method_id' => $this->method($secondSlug)->id,
            'amount' => $amount2,
            'reference_number' => $reference2,
            'captain_id' => null,
        ]);

        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('ORD-SPLIT')
            ->assertSee($displayLabel)
            ->assertSee('Paid')
            ->assertDontSee('class="payment-mode-select"', false);
    }

    private function assertPaidInFull($order, $bill, int $paymentCount): void
    {
        $this->assertSame($paymentCount, BillPayment::query()->where('bill_id', $bill->id)->count());
        $this->assertSame(BillStatus::Settled, $bill->fresh()->status);
        $this->assertNotNull($bill->fresh()->settled_at);
        $this->assertSame(OrderStatus::Checkout, $order->fresh()->status);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
    }
}
