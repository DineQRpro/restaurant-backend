@extends('layouts.counter')

@section('title', 'Counter Dashboard')
@section('page-title', 'Counter Dashboard')
@section('page-subtitle', $dashboard['date'])

@section('content')
    @include('shared.dashboard.overview', ['dashboard' => $dashboard])

    @include('shared.dashboard.table-board', ['dashboard' => $dashboard])

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4">
            <h2 class="text-lg font-bold text-[#1f2f3e]">Track Orders</h2>
        </div>
        <div class="p-5">
            @include('shared.dashboard.track-orders', [
                'dashboard' => $dashboard,
                'canShowPaymentSelect' => $canShowPaymentSelect,
                'paymentInteractive' => true,
                'chooseMethodLabel' => 'Choose Method',
                'paymentStoreRouteName' => 'counter.bills.payments.store',
                'splitStoreRouteName' => 'counter.bills.split-payments.store',
                'selectablePaymentMethods' => $dashboard['payment_methods'],
            ])
        </div>
    </section>

    @if ($canShowPaymentSelect && $dashboard['payment_methods']->isNotEmpty())
        <div
            id="counter-payment-modal"
            class="admin-modal fixed inset-0 z-50 hidden items-center justify-center p-4"
            aria-hidden="true"
        >
            <div class="admin-modal-backdrop absolute inset-0 bg-black/40" data-payment-close></div>
            <div class="admin-modal-dialog relative z-10 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-labelledby="counter-payment-title">
                <h3 id="counter-payment-title" class="text-lg font-bold text-[#1f2f3e]">Confirm Payment</h3>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Order Number</dt>
                        <dd id="counter-payment-order-number" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Table Number</dt>
                        <dd id="counter-payment-table-number" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Bill Number</dt>
                        <dd id="counter-payment-bill-number" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Bill Total</dt>
                        <dd id="counter-payment-grand-total" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Already Paid</dt>
                        <dd id="counter-payment-paid-amount" class="mt-1 text-[#425466]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Balance Due</dt>
                        <dd id="counter-payment-balance" class="mt-1 text-lg font-bold text-[#04346a]"></dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Selected Payment Mode</dt>
                        <dd id="counter-payment-method-name" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                </dl>

                <form id="counter-payment-form" method="POST" class="mt-5 space-y-3">
                    @csrf
                    <input type="hidden" name="payment_method_id" id="counter_payment_method_id">
                    <input type="hidden" name="amount" id="counter_payment_amount">
                    <div id="counter-payment-reference-wrap" class="hidden">
                        <label for="counter_payment_reference" class="mb-1 block text-sm font-semibold text-[#1f2f3e]">Reference Number</label>
                        <input
                            id="counter_payment_reference"
                            type="text"
                            name="reference_number"
                            maxlength="150"
                            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                        >
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-payment-close class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                            Cancel
                        </button>
                        <button id="counter-payment-submit" type="submit" class="rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f] disabled:cursor-not-allowed disabled:opacity-50">
                            Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            id="counter-split-modal"
            class="admin-modal fixed inset-0 z-50 hidden items-center justify-center p-4"
            aria-hidden="true"
        >
            <div class="admin-modal-backdrop absolute inset-0 bg-black/40" data-split-close></div>
            <div class="admin-modal-dialog relative z-10 w-full max-w-xl rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-labelledby="counter-split-title">
                <h3 id="counter-split-title" class="text-lg font-bold text-[#1f2f3e]">Split Payment</h3>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Order Number</dt>
                        <dd id="counter-split-order-number" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Bill Number</dt>
                        <dd id="counter-split-bill-number" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Grand Total</dt>
                        <dd id="counter-split-grand-total" class="mt-1 font-semibold text-[#1f2f3e]"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Already Paid</dt>
                        <dd id="counter-split-paid-amount" class="mt-1 text-[#425466]"></dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Remaining Balance</dt>
                        <dd id="counter-split-balance" class="mt-1 text-lg font-bold text-[#04346a]"></dd>
                    </div>
                </dl>

                <form id="counter-split-form" method="POST" class="mt-5 space-y-4">
                    @csrf
                    @foreach ([0, 1] as $index)
                        @php $methodNumber = $index + 1; @endphp
                        <div class="rounded-lg border border-[#eef1f5] p-3">
                            <label for="split_method_{{ $methodNumber }}" class="mb-1 block text-sm font-semibold text-[#1f2f3e]">
                                Payment Method {{ $methodNumber }}
                            </label>
                            <select
                                id="split_method_{{ $methodNumber }}"
                                name="payments[{{ $index }}][payment_method_id]"
                                class="counter-split-method w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                                required
                            >
                                @foreach ($dashboard['splittable_payment_methods'] as $method)
                                    <option
                                        value="{{ $method->id }}"
                                        data-slug="{{ $method->slug }}"
                                        data-requires-reference="{{ $method->requiresReference() ? '1' : '0' }}"
                                        @selected($index === 0 ? $loop->first : $loop->iteration === 2)
                                    >{{ $method->displayLabel() }}</option>
                                @endforeach
                            </select>
                            <label for="split_amount_{{ $methodNumber }}" class="mb-1 mt-3 block text-sm font-semibold text-[#1f2f3e]">Amount</label>
                            <input
                                id="split_amount_{{ $methodNumber }}"
                                type="number"
                                name="payments[{{ $index }}][amount]"
                                step="0.01"
                                min="0.01"
                                required
                                class="counter-split-amount w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                            >
                            <div id="split-reference-wrap-{{ $methodNumber }}" class="mt-3 hidden">
                                <label for="split_reference_{{ $methodNumber }}" class="mb-1 block text-sm font-semibold text-[#1f2f3e]">Reference Number</label>
                                <input
                                    id="split_reference_{{ $methodNumber }}"
                                    type="text"
                                    name="payments[{{ $index }}][reference_number]"
                                    maxlength="150"
                                    class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                                >
                            </div>
                        </div>
                    @endforeach

                    <div class="flex items-center justify-between text-sm font-semibold">
                        <p>Split Total: <span id="counter-split-total">Rs. 0.00</span></p>
                        <p>Remaining: <span id="counter-split-remaining">Rs. 0.00</span></p>
                    </div>
                    <p id="counter-split-hint" class="hidden text-sm text-red-600"></p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-split-close class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                            Cancel
                        </button>
                        <button
                            id="counter-split-submit"
                            type="submit"
                            disabled
                            class="rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @push('scripts')
            <script>
                (() => {
                    const paymentModal = document.getElementById('counter-payment-modal');
                    const splitModal = document.getElementById('counter-split-modal');
                    const paymentForm = document.getElementById('counter-payment-form');
                    const splitForm = document.getElementById('counter-split-form');
                    const paymentSubmit = document.getElementById('counter-payment-submit');
                    const splitSubmit = document.getElementById('counter-split-submit');
                    const methodSelects = [
                        document.getElementById('split_method_1'),
                        document.getElementById('split_method_2'),
                    ];
                    const amountInputs = [
                        document.getElementById('split_amount_1'),
                        document.getElementById('split_amount_2'),
                    ];
                    const hint = document.getElementById('counter-split-hint');

                    if (!paymentModal || !splitModal || !paymentForm || !splitForm) {
                        return;
                    }

                    const money = (value) => `Rs. ${Number(value).toFixed(2)}`;
                    const toCents = (value) => Math.round(Number(value || 0) * 100);
                    let fillingSecondAmount = false;
                    let remainingBalanceCents = 0;
                    let activeSelect = null;

                    const resetActiveSelect = () => {
                        if (activeSelect) {
                            activeSelect.value = '';
                            activeSelect = null;
                        }
                    };

                    const showModal = (modal) => {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                        modal.style.display = 'flex';
                        modal.setAttribute('aria-hidden', 'false');
                        document.body.classList.add('overflow-hidden');
                    };

                    const hideModal = (modal) => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                        modal.style.display = 'none';
                        modal.setAttribute('aria-hidden', 'true');
                        if (paymentModal.classList.contains('hidden') && splitModal.classList.contains('hidden')) {
                            document.body.classList.remove('overflow-hidden');
                        }
                    };

                    const toggleReference = (select, wrap, input) => {
                        if (!select || !wrap) {
                            return;
                        }
                        const option = select.options[select.selectedIndex];
                        const needsReference = option && option.dataset.requiresReference === '1';
                        wrap.classList.toggle('hidden', !needsReference);
                        if (!needsReference && input) {
                            input.value = '';
                        }
                    };

                    const enableSubmit = (button, label) => {
                        if (!button) {
                            return;
                        }
                        button.disabled = false;
                        button.textContent = label;
                    };

                    const closePayment = () => {
                        hideModal(paymentModal);
                        paymentForm.reset();
                        enableSubmit(paymentSubmit, 'Confirm Payment');
                        document.getElementById('counter-payment-reference-wrap').classList.add('hidden');
                        resetActiveSelect();
                    };

                    const closeSplit = () => {
                        hideModal(splitModal);
                        splitForm.reset();
                        remainingBalanceCents = 0;
                        fillingSecondAmount = false;
                        enableSubmit(splitSubmit, 'Confirm Payment');
                        updateSplitTotals();
                        resetActiveSelect();
                    };

                    const openPayment = (select) => {
                        const option = select.options[select.selectedIndex];
                        activeSelect = select;
                        paymentForm.action = select.getAttribute('data-action') || '';
                        document.getElementById('counter_payment_method_id').value = select.value;
                        document.getElementById('counter_payment_amount').value = select.dataset.balance;
                        document.getElementById('counter-payment-order-number').textContent = select.dataset.orderNumber;
                        document.getElementById('counter-payment-table-number').textContent = select.dataset.tableNumber;
                        document.getElementById('counter-payment-bill-number').textContent = select.dataset.billNumber;
                        document.getElementById('counter-payment-grand-total').textContent = money(select.dataset.grandTotal);
                        document.getElementById('counter-payment-paid-amount').textContent = money(select.dataset.paidAmount);
                        document.getElementById('counter-payment-balance').textContent = money(select.dataset.balance);
                        document.getElementById('counter-payment-method-name').textContent = option.textContent.trim();
                        enableSubmit(paymentSubmit, 'Confirm Payment');
                        const referenceInput = document.getElementById('counter_payment_reference');
                        referenceInput.value = '';
                        toggleReference(
                            select,
                            document.getElementById('counter-payment-reference-wrap'),
                            referenceInput,
                        );
                        showModal(paymentModal);
                    };

                    const updateSplitTotals = () => {
                        if (!amountInputs[0] || !amountInputs[1] || !methodSelects[0] || !methodSelects[1] || !splitSubmit) {
                            return;
                        }

                        const amountOneCents = toCents(amountInputs[0].value);
                        const amountTwoCents = toCents(amountInputs[1].value);
                        const splitTotalCents = amountOneCents + amountTwoCents;
                        const leftoverCents = remainingBalanceCents - splitTotalCents;
                        const methodsDistinct = methodSelects[0].value && methodSelects[0].value !== methodSelects[1].value;
                        const exact = leftoverCents === 0 && amountOneCents > 0 && amountTwoCents > 0 && methodsDistinct;

                        document.getElementById('counter-split-total').textContent = money(splitTotalCents / 100);
                        document.getElementById('counter-split-remaining').textContent = money(Math.max(0, leftoverCents) / 100);
                        splitSubmit.disabled = !exact;

                        if (methodSelects[0].value && methodSelects[0].value === methodSelects[1].value) {
                            hint.textContent = 'Split methods must be different.';
                            hint.classList.remove('hidden');
                        } else if (!exact && (amountOneCents > 0 || amountTwoCents > 0) && leftoverCents !== 0) {
                            hint.textContent = 'Split total must equal the remaining balance.';
                            hint.classList.remove('hidden');
                        } else if (hint) {
                            hint.classList.add('hidden');
                        }
                    };

                    const openSplit = (select) => {
                        activeSelect = select;
                        splitForm.action = select.getAttribute('data-split-action') || '';
                        remainingBalanceCents = toCents(select.dataset.balance);
                        document.getElementById('counter-split-order-number').textContent = select.dataset.orderNumber;
                        document.getElementById('counter-split-bill-number').textContent = select.dataset.billNumber;
                        document.getElementById('counter-split-grand-total').textContent = money(select.dataset.grandTotal);
                        document.getElementById('counter-split-paid-amount').textContent = money(select.dataset.paidAmount);
                        document.getElementById('counter-split-balance').textContent = money(select.dataset.balance);
                        if (amountInputs[0]) amountInputs[0].value = '';
                        if (amountInputs[1]) amountInputs[1].value = '';
                        document.getElementById('split_reference_1').value = '';
                        document.getElementById('split_reference_2').value = '';
                        if (methodSelects[0] && methodSelects[0].options.length) {
                            methodSelects[0].selectedIndex = 0;
                        }
                        if (methodSelects[1] && methodSelects[1].options.length > 1) {
                            methodSelects[1].selectedIndex = 1;
                        }
                        enableSubmit(splitSubmit, 'Confirm Payment');
                        toggleReference(methodSelects[0], document.getElementById('split-reference-wrap-1'), document.getElementById('split_reference_1'));
                        toggleReference(methodSelects[1], document.getElementById('split-reference-wrap-2'), document.getElementById('split_reference_2'));
                        updateSplitTotals();
                        showModal(splitModal);
                    };

                    document.addEventListener('change', (event) => {
                        const select = event.target.closest('.payment-mode-select');
                        if (!select || select.hasAttribute('data-display-only')) {
                            return;
                        }
                        if (!select.value) {
                            return;
                        }

                        const option = select.options[select.selectedIndex];
                        if (option && option.dataset.slug === 'split') {
                            openSplit(select);
                            return;
                        }

                        openPayment(select);
                    });

                    paymentModal.querySelectorAll('[data-payment-close]').forEach((el) => {
                        el.addEventListener('click', closePayment);
                    });
                    splitModal.querySelectorAll('[data-split-close]').forEach((el) => {
                        el.addEventListener('click', closeSplit);
                    });

                    methodSelects.forEach((select, index) => {
                        if (!select) {
                            return;
                        }
                        select.addEventListener('change', () => {
                            toggleReference(
                                select,
                                document.getElementById(`split-reference-wrap-${index + 1}`),
                                document.getElementById(`split_reference_${index + 1}`),
                            );
                            updateSplitTotals();
                        });
                    });

                    if (amountInputs[0]) {
                        amountInputs[0].addEventListener('input', () => {
                            if (!fillingSecondAmount && amountInputs[1]) {
                                fillingSecondAmount = true;
                                const leftover = Math.max(0, remainingBalanceCents - toCents(amountInputs[0].value));
                                amountInputs[1].value = leftover > 0 ? (leftover / 100).toFixed(2) : '';
                                fillingSecondAmount = false;
                            }
                            updateSplitTotals();
                        });
                    }
                    if (amountInputs[1]) {
                        amountInputs[1].addEventListener('input', updateSplitTotals);
                    }

                    paymentForm.addEventListener('submit', () => {
                        if (paymentSubmit) {
                            paymentSubmit.disabled = true;
                            paymentSubmit.textContent = 'Recording...';
                        }
                    });
                    splitForm.addEventListener('submit', () => {
                        if (splitSubmit) {
                            splitSubmit.disabled = true;
                            splitSubmit.textContent = 'Recording...';
                        }
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key !== 'Escape') {
                            return;
                        }
                        if (!paymentModal.classList.contains('hidden')) {
                            closePayment();
                        }
                        if (!splitModal.classList.contains('hidden')) {
                            closeSplit();
                        }
                    });
                })();
            </script>
        @endpush
    @endif
@endsection

