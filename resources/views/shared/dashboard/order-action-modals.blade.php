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
