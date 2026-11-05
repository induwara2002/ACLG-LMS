<dialog class="catalog-quick-add" id="author-dialog" aria-labelledby="author-dialog-title">
    <div data-catalog-dialog="author" data-endpoint="{{ route('admin.authors.store') }}">
        <input type="hidden" value="{{ csrf_token() }}" data-catalog-token>
        <h2 class="h5" id="author-dialog-title">{{ __('Add Author') }}</h2>
        <label class="form-label" for="quick-author-name">{{ __('Full name') }}</label>
        <input class="form-control" id="quick-author-name" data-catalog-name maxlength="255" required>
        <p class="text-danger small mt-2 mb-0" role="alert" data-catalog-errors></p>
        <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-outline-secondary" data-close-catalog-dialog>{{ __('Cancel') }}</button><button type="button" class="btn btn-primary" data-create-catalog>{{ __('Save Author') }}</button></div>
    </div>
</dialog>

<dialog class="catalog-quick-add" id="publisher-dialog" aria-labelledby="publisher-dialog-title">
    <div data-catalog-dialog="publisher" data-endpoint="{{ route('admin.publishers.store') }}">
        <input type="hidden" value="{{ csrf_token() }}" data-catalog-token>
        <h2 class="h5" id="publisher-dialog-title">{{ __('Add Publisher') }}</h2>
        <label class="form-label" for="quick-publisher-name">{{ __('Name') }}</label>
        <input class="form-control" id="quick-publisher-name" data-catalog-name maxlength="255" required>
        <p class="text-danger small mt-2 mb-0" role="alert" data-catalog-errors></p>
        <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-outline-secondary" data-close-catalog-dialog>{{ __('Cancel') }}</button><button type="button" class="btn btn-primary" data-create-catalog>{{ __('Save Publisher') }}</button></div>
    </div>
</dialog>

<script>
    (() => {
        document.querySelectorAll('[data-open-catalog-dialog]').forEach((button) => {
            button.addEventListener('click', () => document.getElementById(button.dataset.openCatalogDialog)?.showModal());
        });
        document.querySelectorAll('[data-close-catalog-dialog]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog')?.close());
        });
        document.querySelectorAll('[data-create-catalog]').forEach((button) => {
            button.addEventListener('click', async () => {
                const dialog = button.closest('dialog');
                const data = dialog.querySelector('[data-catalog-dialog]');
                const name = dialog.querySelector('[data-catalog-name]');
                const resource = data.dataset.catalogDialog;
                const errors = dialog.querySelector('[data-catalog-errors]');
                if (!name.reportValidity()) return;
                errors.textContent = '';
                button.disabled = true;
                try {
                    const body = new FormData();
                    body.append('_token', data.querySelector('[data-catalog-token]').value);
                    body.append('name', name.value.trim());
                    const response = await fetch(data.dataset.endpoint, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        body,
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        if (response.status === 419) {
                            errors.textContent = 'Your session expired. Refresh the page and try again.';
                        } else if (response.status === 403) {
                            errors.textContent = 'You are not authorized to create this record.';
                        } else if (response.status === 422) {
                            errors.textContent = Object.values(result.errors ?? {}).flat().join(' ')
                                || result.message
                                || 'Please correct the submitted information.';
                        } else if (response.status >= 500) {
                            errors.textContent = 'The server could not save this record. Please try again or contact support.';
                        } else {
                            errors.textContent = result.message || `The request failed (HTTP ${response.status}).`;
                        }
                        return;
                    }

                    const select = document.getElementById(`${resource}_id`);
                    if (select) {
                        select.add(new Option(result.name, result.id, true, true));
                    } else {
                        const input = document.getElementById(`${resource}_name`);
                        if (!input) {
                            errors.textContent = 'The catalog form could not accept this record. Refresh and try again.';
                            return;
                        }
                        input.value = result.name;
                        input.dispatchEvent(new Event('input', {bubbles: true}));
                    }

                    dialog.close();
                    name.value = '';
                } catch (error) {
                    console.error('Catalog quick-add request failed.', error);
                    errors.textContent = 'Could not reach the server. Check your connection and try again.';
                } finally {
                    button.disabled = false;
                }
            });
        });
    })();
</script>
