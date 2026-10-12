<div id="track-kot-modal" class="admin-modal hidden" aria-hidden="true">
    <div class="admin-modal-backdrop" data-track-modal-close="track-kot-modal"></div>
    <div class="admin-modal-dialog kot-modal-dialog" role="dialog" aria-labelledby="track-kot-title">
        <div class="kot-modal-header">
            <h3 id="track-kot-title" class="text-lg font-bold text-[#1f2f3e]">KOT</h3>
            <button type="button" class="kot-modal-close" data-track-modal-close="track-kot-modal" aria-label="Close">Close</button>
        </div>
        <div id="track-kot-modal-body" class="kot-modal-body">
            <p class="kot-modal-status">Loading KOT details...</p>
        </div>
    </div>
</div>

<div id="track-edit-kot-modal" class="admin-modal hidden" aria-hidden="true">
    <div class="admin-modal-backdrop" data-track-modal-close="track-edit-kot-modal"></div>
    <div class="admin-modal-dialog kot-modal-dialog" role="dialog" aria-labelledby="track-edit-kot-title">
        <div class="kot-modal-header">
            <h3 id="track-edit-kot-title" class="text-lg font-bold text-[#1f2f3e]">Edit KOT</h3>
            <button type="button" class="kot-modal-close" data-track-modal-close="track-edit-kot-modal" aria-label="Close">Close</button>
        </div>
        <div id="track-edit-kot-modal-body" class="kot-modal-body">
            <p class="kot-modal-status">Loading Edit KOT...</p>
        </div>
    </div>
</div>

<div id="track-bill-modal" class="admin-modal hidden" aria-hidden="true">
    <div class="admin-modal-backdrop" data-track-modal-close="track-bill-modal"></div>
    <div class="admin-modal-dialog kot-modal-dialog" role="dialog" aria-labelledby="track-bill-title">
        <div class="kot-modal-header">
            <h3 id="track-bill-title" class="text-lg font-bold text-[#1f2f3e]">View Bill</h3>
            <button type="button" class="kot-modal-close" data-track-modal-close="track-bill-modal" aria-label="Close">Close</button>
        </div>
        <div id="track-bill-modal-body" class="kot-modal-body">
            <p class="kot-modal-status">Loading bill details...</p>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let dirtyBillForm = null;
            const actions = [
                { button: '[data-kot-url]', modal: 'track-kot-modal', error: 'Unable to load KOT details.' },
                { button: '[data-edit-kot-url]', modal: 'track-edit-kot-modal', error: 'Unable to load Edit KOT.' },
                { button: '[data-bill-url]', modal: 'track-bill-modal', error: 'Unable to load bill details.' },
            ];

            const anyOpen = () => document.querySelector('.admin-modal:not(.hidden)');

            const showModal = (modal) => {
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            const hideModal = (modal, body, loading) => {
                if (modal.id === 'track-bill-modal') {
                    dirtyBillForm = null;
                }
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                if (body && loading) {
                    body.innerHTML = loading;
                }
                if (!anyOpen()) {
                    document.body.classList.remove('overflow-hidden');
                }
            };

            const loadPartial = async (url, body, errorMessage) => {
                try {
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'text/html',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        body.innerHTML = `<p class="kot-modal-status">${errorMessage}</p>`;
                        return;
                    }

                    body.innerHTML = await response.text();
                    const viewBill = body.querySelector('[data-view-bill]');
                    if (viewBill) {
                        initBillAdjustments(viewBill);
                    }
                } catch (error) {
                    body.innerHTML = `<p class="kot-modal-status">${errorMessage}</p>`;
                }
            };

            actions.forEach((action) => {
                const modal = document.getElementById(action.modal);
                const body = document.getElementById(`${action.modal}-body`);
                if (!modal || !body) {
                    return;
                }

                const loading = body.innerHTML;

                document.addEventListener('click', (event) => {
                    const button = event.target.closest(action.button);
                    if (!button) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    body.innerHTML = loading;
                    showModal(modal);
                    loadPartial(button.getAttribute(action.button.replace(/[\[\]]/g, '')), body, action.error);
                });

                modal.querySelectorAll(`[data-track-modal-close="${action.modal}"]`).forEach((el) => {
                    el.addEventListener('click', () => hideModal(modal, body, loading));
                });
            });

            document.addEventListener('click', (event) => {
                const cancel = event.target.closest('[data-cancel-item]');
                if (!cancel) {
                    return;
                }
                const row = cancel.closest('[data-edit-kot-item]');
                const status = row?.querySelector('select[name$="[status]"]');
                if (status) {
                    status.value = 'cancelled';
                }
            });

            document.addEventListener('submit', async (event) => {
                const form = event.target.closest('[data-edit-kot-form]');
                if (!form) {
                    return;
                }

                event.preventDefault();
                const error = form.querySelector('[data-edit-kot-error]');
                const save = form.querySelector('.edit-kot-save');
                const items = [...form.querySelectorAll('[data-edit-kot-item]')].map((row) => ({
                    id: Number(row.dataset.itemId),
                    quantity: Number(row.querySelector('[name$="[quantity]"]').value),
                    status: row.querySelector('[name$="[status]"]').value,
                    special_instructions: row.querySelector('[name$="[special_instructions]"]').value,
                }));

                if (error) {
                    error.classList.add('hidden');
                    error.textContent = '';
                }
                if (save) {
                    save.disabled = true;
                    save.textContent = 'Saving...';
                }

                try {
                    const response = await fetch(form.getAttribute('data-action'), {
                        method: 'PATCH',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ items }),
                    });

                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        throw new Error(payload.message || 'Unable to save KOT.');
                    }

                    const modal = document.getElementById('track-edit-kot-modal');
                    hideModal(modal, document.getElementById('track-edit-kot-modal-body'), '<p class="kot-modal-status">Loading Edit KOT...</p>');
                } catch (err) {
                    if (error) {
                        error.textContent = err.message || 'Unable to save KOT.';
                        error.classList.remove('hidden');
                    }
                } finally {
                    if (save) {
                        save.disabled = false;
                        save.textContent = 'Save KOT';
                    }
                }
            });

            const normalizeMoney = (value) => {
                if (value === '' || value === null || value === undefined) {
                    return '0.00';
                }
                const number = Number(value);
                if (! Number.isFinite(number)) {
                    return String(value).trim();
                }

                return number.toFixed(2);
            };

            const normalizePercent = (value) => {
                if (value === '' || value === null || value === undefined) {
                    return '';
                }
                const number = Number(value);
                if (! Number.isFinite(number)) {
                    return String(value).trim();
                }

                return number.toFixed(2);
            };

            const currentAdjustmentState = (form) => {
                const select = form.querySelector('[data-bill-discount-select]');
                const option = select?.selectedOptions[0];
                const isCustom = option?.dataset.type === 'custom';
                const percentageInput = form.querySelector('[data-bill-custom-percentage]');
                const commentInput = form.querySelector('[data-bill-discount-comment]');
                const chargesInput = form.querySelector('[data-bill-additional-charges]');

                return {
                    discount_id: select?.value || '',
                    custom_percentage: isCustom ? normalizePercent(percentageInput?.value ?? '') : '',
                    comment: isCustom ? (commentInput?.value || '').trim() : '',
                    additional_charges: normalizeMoney(chargesInput?.value ?? ''),
                };
            };

            const appliedAdjustmentState = (form) => {
                try {
                    return JSON.parse(form.getAttribute('data-applied') || '{}');
                } catch (error) {
                    return {};
                }
            };

            const statesMatch = (current, applied) => (
                String(current.discount_id || '') === String(applied.discount_id || '')
                && normalizePercent(current.custom_percentage || '') === normalizePercent(applied.custom_percentage || '')
                && String(current.comment || '').trim() === String(applied.comment || '').trim()
                && normalizeMoney(current.additional_charges) === normalizeMoney(applied.additional_charges)
            );

            const isLocallyValid = (form) => {
                const charges = (form.querySelector('[data-bill-additional-charges]')?.value ?? '').trim();
                if (charges !== '') {
                    if (! /^\d+(\.\d{1,2})?$/.test(charges)) {
                        return false;
                    }
                    if (Number(charges) < 0) {
                        return false;
                    }
                }

                const option = form.querySelector('[data-bill-discount-select]')?.selectedOptions[0];
                if (option?.dataset.type === 'custom') {
                    const percent = (form.querySelector('[data-bill-custom-percentage]')?.value ?? '').trim();
                    if (percent === '' || ! Number.isFinite(Number(percent))) {
                        return false;
                    }
                }

                return true;
            };

            const hasUnappliedBillChanges = (form) => ! statesMatch(
                currentAdjustmentState(form),
                appliedAdjustmentState(form),
            );

            const syncPrintGuard = (root, dirty) => {
                if (! root) {
                    return;
                }
                root.dataset.hasUnappliedBillChanges = dirty ? 'true' : 'false';
                const printBtn = root.querySelector('[data-bill-print]');
                if (! printBtn) {
                    return;
                }
                if (dirty) {
                    printBtn.setAttribute('aria-disabled', 'true');
                    printBtn.classList.add('is-print-blocked');
                    printBtn.title = 'Apply changes before printing.';
                    printBtn.setAttribute('aria-label', 'Apply changes before printing.');
                    printBtn.dataset.printBlockedReason = 'unapplied-changes';
                    return;
                }
                printBtn.removeAttribute('aria-disabled');
                printBtn.classList.remove('is-print-blocked');
                printBtn.title = 'Print Bill';
                printBtn.setAttribute('aria-label', 'Print Bill');
                printBtn.dataset.printBlockedReason = 'ready';
            };

            const setDirtyWarning = (form, dirty) => {
                form.dataset.dirty = dirty ? 'true' : 'false';
                if (dirty) {
                    dirtyBillForm = form;
                    return;
                }
                if (dirtyBillForm === form) {
                    dirtyBillForm = null;
                }
            };

            const syncBillAdjustments = (form) => {
                const root = form.closest('[data-view-bill]');
                const apply = form.querySelector('[data-bill-apply]');
                const option = form.querySelector('[data-bill-discount-select]')?.selectedOptions[0];
                const isCustom = option?.dataset.type === 'custom';
                form.querySelector('[data-bill-discount-custom]')?.classList.toggle('hidden', ! isCustom);
                const dirty = hasUnappliedBillChanges(form);
                const valid = isLocallyValid(form);
                if (apply && apply.dataset.submitting !== 'true') {
                    apply.disabled = ! (dirty && valid);
                }
                setDirtyWarning(form, dirty);
                syncPrintGuard(root, dirty);
            };

            const initBillAdjustments = (root) => {
                const form = root.querySelector('[data-bill-adjustments-form]');
                if (! form) {
                    syncPrintGuard(root, false);
                    return;
                }
                syncBillAdjustments(form);
            };

            const applyBillAdjustments = async (form) => {
                const error = form.querySelector('[data-bill-adjustments-error]');
                const apply = form.querySelector('[data-bill-apply]');
                const select = form.querySelector('[data-bill-discount-select]');
                const option = select?.selectedOptions[0];
                const isCustom = option?.dataset.type === 'custom';
                const percentageInput = form.querySelector('[data-bill-custom-percentage]');
                const commentInput = form.querySelector('[data-bill-discount-comment]');
                const chargesInput = form.querySelector('[data-bill-additional-charges]');
                const payload = {
                    discount_id: select?.value || null,
                    custom_percentage: isCustom && percentageInput?.value !== '' ? Number(percentageInput.value) : null,
                    comment: isCustom ? (commentInput?.value || null) : null,
                    additional_charges: chargesInput?.value === '' ? 0 : chargesInput?.value,
                };

                if (error) {
                    error.classList.add('hidden');
                    error.textContent = '';
                }
                if (apply) {
                    apply.disabled = true;
                    apply.dataset.submitting = 'true';
                    apply.textContent = 'Applying...';
                }

                try {
                    const response = await fetch(form.getAttribute('data-action'), {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (response.status === 422) {
                        const first = Object.values(data.errors || {}).flat()[0];
                        throw new Error(first || data.message || 'Unable to apply changes.');
                    }
                    if (! response.ok || data.success === false) {
                        throw new Error(data.message || 'Unable to apply changes.');
                    }
                    const root = form.closest('[data-view-bill]');
                    if (data.summary && root) {
                        Object.entries(data.summary).forEach(([field, value]) => {
                            if (field === 'additional_charges') {
                                return;
                            }
                            const cell = root.querySelector(`[data-bill-field="${field}"]`);
                            if (cell && cell.tagName !== 'INPUT') {
                                cell.textContent = value;
                            }
                        });
                    }
                    if (chargesInput && data.additional_charges !== undefined) {
                        chargesInput.value = data.additional_charges;
                    }
                    if (data.applied) {
                        form.setAttribute('data-applied', JSON.stringify(data.applied));
                        if (percentageInput && data.applied.custom_percentage !== undefined) {
                            percentageInput.value = data.applied.custom_percentage;
                        }
                        if (commentInput && data.applied.comment !== undefined) {
                            commentInput.value = data.applied.comment;
                        }
                    }
                    if (root && data.paid_amount) {
                        const paid = root.querySelector('[data-bill-field="paid_amount"]');
                        if (paid) {
                            paid.textContent = data.paid_amount;
                        }
                    }
                    if (root && data.remaining_balance) {
                        const remaining = root.querySelector('[data-bill-field="remaining_balance"]');
                        if (remaining) {
                            remaining.textContent = data.remaining_balance;
                        }
                    }
                } catch (err) {
                    if (error) {
                        error.textContent = err.message || 'Unable to apply changes.';
                        error.classList.remove('hidden');
                    }
                } finally {
                    if (apply) {
                        apply.dataset.submitting = 'false';
                        apply.textContent = 'Apply';
                    }
                    syncBillAdjustments(form);
                }
            };

            document.addEventListener('change', (event) => {
                const form = event.target.closest('[data-bill-adjustments-form]');
                if (! form) {
                    return;
                }
                syncBillAdjustments(form);
            });

            document.addEventListener('input', (event) => {
                const form = event.target.closest('[data-bill-adjustments-form]');
                if (! form) {
                    return;
                }
                syncBillAdjustments(form);
            });

            document.addEventListener('submit', (event) => {
                const form = event.target.closest('[data-bill-adjustments-form]');
                if (! form) {
                    return;
                }
                event.preventDefault();
                if (! hasUnappliedBillChanges(form) || ! isLocallyValid(form)) {
                    return;
                }
                applyBillAdjustments(form);
            });

            document.addEventListener('click', (event) => {
                const printBtn = event.target.closest('[data-bill-print]');
                if (! printBtn) {
                    return;
                }
                const root = printBtn.closest('[data-view-bill]');
                const blocked = printBtn.getAttribute('aria-disabled') === 'true'
                    || printBtn.dataset.printBlockedReason === 'unapplied-changes'
                    || root?.dataset.hasUnappliedBillChanges === 'true';
                if (blocked) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, true);

            window.addEventListener('beforeunload', (event) => {
                if (! dirtyBillForm) {
                    return;
                }
                event.preventDefault();
                event.returnValue = '';
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }
                const open = anyOpen();
                if (!open || !open.id.startsWith('track-')) {
                    return;
                }
                const body = document.getElementById(`${open.id}-body`);
                hideModal(open, body, body?.dataset.loading || body?.innerHTML);
            });
        })();
    </script>
@endpush
