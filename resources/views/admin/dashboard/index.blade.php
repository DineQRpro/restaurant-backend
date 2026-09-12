@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')
@section('page-subtitle', $dashboard['date'])

@section('content')
    @include('shared.dashboard.overview', ['dashboard' => $dashboard])

    <section class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <article class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-[#898989]">Menu categories</p>
            <p class="mt-1 text-2xl font-bold text-[#04346a]">{{ $dashboard['category_count'] }}</p>
        </article>
        <article class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-[#898989]">Menu items</p>
            <p class="mt-1 text-2xl font-bold text-[#04346a]">{{ $dashboard['menu_item_count'] }}</p>
        </article>
        <article class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-[#898989]">Unavailable dishes</p>
            <p class="mt-1 text-2xl font-bold text-[#04346a]">{{ $dashboard['highlights'][2]['value'] }}</p>
        </article>
    </section>

    @include('shared.dashboard.table-board', ['dashboard' => $dashboard])

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4">
            <h2 class="text-lg font-bold text-[#1f2f3e]">Track Orders</h2>
        </div>
        <div class="p-5">
            @include('shared.dashboard.track-orders', [
                'dashboard' => $dashboard,
                'canShowPaymentSelect' => $canSettleBills,
                'paymentInteractive' => true,
                'chooseMethodLabel' => 'Select Payment',
                'paymentStoreRouteName' => 'admin.bills.payments.store',
                'selectablePaymentMethods' => $dashboard['payment_methods']
                    ->reject(fn ($method) => $method->isSplit())
                    ->values(),
            ])

            @if ($dashboard['unavailable_items']->isNotEmpty())
                <h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-[#898989]">Sold out dishes</h3>
                <ul class="mt-3 divide-y divide-[#eef1f5] rounded-lg border border-[#eef1f5]">
                    @foreach ($dashboard['unavailable_items'] as $item)
                        <li class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="font-semibold">{{ $item->name }}</span>
                            <span class="text-[#898989]">{{ $item->category?->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @if ($canSettleBills && $dashboard['payment_methods']->isNotEmpty())
        <div id="admin-payment-modal" class="admin-modal hidden" aria-hidden="true">
            <div class="admin-modal-backdrop" data-payment-close></div>
            <div class="admin-modal-dialog" role="dialog" aria-labelledby="admin-payment-title">
                <h3 id="admin-payment-title" class="text-lg font-bold text-[#1f2f3e]">Confirm Payment</h3>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Order Number</dt>
                        <dd id="payment-order-number" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Bill Number</dt>
                        <dd id="payment-bill-number" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Bill Total</dt>
                        <dd id="payment-grand-total" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Already Paid</dt>
                        <dd id="payment-paid-amount" class="mt-1 text-[#425466]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Balance</dt>
                        <dd id="payment-balance" class="mt-1 text-lg font-bold text-[#04346a]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Selected Payment Mode</dt>
                        <dd id="payment-method-name" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                </dl>

                <form id="admin-payment-form" method="POST" class="mt-5 space-y-3">
                    @csrf
                    <input type="hidden" name="payment_method_id" id="payment_method_id">
                    <div>
                        <label for="payment_amount" class="mb-1 block text-sm font-semibold text-[#1f2f3e]">Amount</label>
                        <input
                            id="payment_amount"
                            type="number"
                            name="amount"
                            step="0.01"
                            min="0.01"
                            required
                            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                        >
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-payment-close class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                            Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @push('scripts')
            <script>
                (() => {
                    const modal = document.getElementById('admin-payment-modal');
                    if (!modal) {
                        return;
                    }

                    const form = document.getElementById('admin-payment-form');
                    const money = (value) => `Rs. ${Number(value).toFixed(2)}`;
                    const selects = document.querySelectorAll('.payment-mode-select:not([data-display-only])');

                    const resetSelects = () => {
                        selects.forEach((select) => {
                            select.value = '';
                        });
                    };

                    const open = (select) => {
                        const option = select.options[select.selectedIndex];
                        form.action = select.dataset.action;
                        document.getElementById('payment_method_id').value = select.value;
                        document.getElementById('payment-order-number').textContent = select.dataset.orderNumber;
                        document.getElementById('payment-bill-number').textContent = select.dataset.billNumber;
                        document.getElementById('payment-grand-total').textContent = money(select.dataset.grandTotal);
                        document.getElementById('payment-paid-amount').textContent = money(select.dataset.paidAmount);
                        document.getElementById('payment-balance').textContent = money(select.dataset.balance);
                        document.getElementById('payment-method-name').textContent = option.textContent;
                        document.getElementById('payment_amount').value = select.dataset.balance;
                        modal.classList.remove('hidden');
                        modal.setAttribute('aria-hidden', 'false');
                        document.body.classList.add('overflow-hidden');
                    };

                    const close = () => {
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                        document.body.classList.remove('overflow-hidden');
                        resetSelects();
                    };

                    selects.forEach((select) => {
                        select.addEventListener('change', () => {
                            if (!select.value) {
                                return;
                            }

                            const option = select.options[select.selectedIndex];
                            if (option?.dataset.slug === 'split') {
                                select.value = '';
                                return;
                            }

                            open(select);
                        });
                    });
                    modal.querySelectorAll('[data-payment-close]').forEach((el) => {
                        el.addEventListener('click', close);
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                            close();
                        }
                    });
                })();
            </script>
        @endpush
    @endif
@endsection
