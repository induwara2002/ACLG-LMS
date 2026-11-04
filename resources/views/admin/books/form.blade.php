@if ($errors->any())
    <div class="alert alert-danger" id="book-form-errors" role="alert" tabindex="-1">
        <h2 class="h6">{{ __('The book could not be saved. Please correct the following fields:') }}</h2>
        <ul class="mb-0">
            @foreach ($errors->getMessages() as $field => $messages)
                @foreach ($messages as $message)
                    <li><a class="alert-link" href="#{{ $field }}">{{ __($field === 'book_id' ? 'Acquisition Number' : \Illuminate\Support\Str::headline($field)) }}: {{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
<div class="row g-3 mb-3">
    <div class="col-md-8"><label for="title" class="form-label">{{ __('Book Name *') }}</label><input id="title" name="title" type="text" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $book->title ?? '') }}" maxlength="255" required @if ($errors->has('title')) aria-invalid="true" aria-describedby="title-error" @endif>@error('title')<div class="invalid-feedback" id="title-error">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label for="book_id" class="form-label">{{ __('Acquisition Number *') }}</label><input id="book_id" name="book_id" type="text" class="form-control @error('book_id') is-invalid @enderror" value="{{ old('book_id', $book->book_id ?? '') }}" maxlength="100" required @if ($errors->has('book_id')) aria-invalid="true" aria-describedby="book_id-error" @endif>@error('book_id')<div class="invalid-feedback" id="book_id-error">{{ $message }}</div>@enderror</div>
    <div class="col-md-6">
        <label for="isbn" class="form-label">{{ __('ISBN-10 or ISBN-13') }}</label>
        <div class="input-group">
            <input id="isbn" name="isbn" type="text" class="form-control @error('isbn') is-invalid @enderror"
                value="{{ old('isbn', $book->isbn ?? '') }}" maxlength="20" inputmode="text" autocomplete="off"
                aria-describedby="isbn-help isbn-lookup-status"
                @if ($errors->has('isbn')) aria-invalid="true" @endif>
            <button class="btn btn-outline-primary" id="isbn-lookup" type="button"
                data-url="{{ route('admin.books.isbn-lookup') }}"
                @if (isset($book)) data-current-book-id="{{ $book->id }}" @endif>
                <span id="isbn-lookup-spinner" class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>
                {{ __('Look up') }}
            </button>
        </div>
        <div class="form-text" id="isbn-help">{{ __('Enter a valid ISBN-10 or ISBN-13. Details are looked up when you leave this field; you can also enter details manually.') }}</div>
        <div class="small mt-1" id="isbn-lookup-status" role="status" aria-live="polite"></div>
        @error('isbn')<div class="invalid-feedback d-block" id="isbn-error">{{ $message }}</div>@enderror
    </div>
    @include('admin.books.cover-image-field')
    <div class="col-md-8">
        <label for="author_id" class="form-label">{{ __('Author *') }}</label>
        <div class="d-flex gap-2"><select id="author_id" name="author_id" class="form-select @error('author_id') is-invalid @enderror" required @if ($errors->has('author_id')) aria-invalid="true" aria-describedby="author_id-error" @endif><option value="">{{ __('Select author') }}</option>@foreach ($authors as $author)<option value="{{ $author->id }}" @selected(old('author_id', $book->author_id ?? '') == $author->id)>{{ $author->name }}</option>@endforeach</select><button class="btn btn-outline-secondary text-nowrap" type="button" data-open-catalog-dialog="author-dialog">{{ __('+ Add Author') }}</button></div>
        @error('author_id')<div class="text-danger small mt-1" id="author_id-error">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label for="publisher_id" class="form-label">{{ __('Publisher *') }}</label>
        <div class="d-flex gap-2"><select id="publisher_id" name="publisher_id" class="form-select @error('publisher_id') is-invalid @enderror" required @if ($errors->has('publisher_id')) aria-invalid="true" aria-describedby="publisher_id-error" @endif><option value="">{{ __('Select publisher') }}</option>@foreach ($publishers as $publisher)<option value="{{ $publisher->id }}" @selected(old('publisher_id', $book->publisher_id ?? '') == $publisher->id)>{{ $publisher->name }}</option>@endforeach</select><button class="btn btn-outline-secondary text-nowrap" type="button" data-open-catalog-dialog="publisher-dialog">{{ __('+ Add Publisher') }}</button></div>
        @error('publisher_id')<div class="text-danger small mt-1" id="publisher_id-error">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4"><label for="category_id" class="form-label">{{ __('Category *') }}</label><select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required @if ($errors->has('category_id')) aria-invalid="true" aria-describedby="category_id-error" @endif><option value="">{{ __('Select category') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $book->category_id ?? '') == $category->id)>{{ $category->code }} - {{ \App\Models\Category::MAIN_DDC_RANGES[$category->code] ?? $category->name }}@if (isset(\App\Models\Category::MAIN_DDC_RANGES[$category->code])) · {{ $category->name }}@endif</option>@endforeach</select>@error('category_id')<div class="invalid-feedback" id="category_id-error">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label for="ddc_number" class="form-label">{{ __('DDC Number *') }}</label><input id="ddc_number" name="ddc_number" type="text" inputmode="decimal" class="form-control @error('ddc_number') is-invalid @enderror" value="{{ old('ddc_number', $book->ddc_number ?? '') }}" maxlength="20" required @if ($errors->has('ddc_number')) aria-invalid="true" aria-describedby="ddc_number-error" @endif>@error('ddc_number')<div class="invalid-feedback" id="ddc_number-error">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label for="publication_year" class="form-label">{{ __('Published Year') }}</label><input id="publication_year" name="publication_year" type="number" min="1" class="form-control @error('publication_year') is-invalid @enderror" value="{{ old('publication_year', $book->publication_year ?? '') }}" @if ($errors->has('publication_year')) aria-invalid="true" aria-describedby="publication_year-error" @endif>@error('publication_year')<div class="invalid-feedback" id="publication_year-error">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label for="edition" class="form-label">{{ __('Edition') }}</label><input id="edition" name="edition" type="text" class="form-control @error('edition') is-invalid @enderror" value="{{ old('edition', $book->edition ?? '') }}" maxlength="100" @if ($errors->has('edition')) aria-invalid="true" aria-describedby="edition-error" @endif>@error('edition')<div class="invalid-feedback" id="edition-error">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label for="shelf_number" class="form-label">{{ __('Shelf Location') }}</label><input id="shelf_number" name="shelf_number" type="text" class="form-control @error('shelf_number') is-invalid @enderror" value="{{ old('shelf_number', $book->shelf_number ?? '') }}" maxlength="100" @if ($errors->has('shelf_number')) aria-invalid="true" aria-describedby="shelf_number-error" @endif>@error('shelf_number')<div class="invalid-feedback" id="shelf_number-error">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label for="language" class="form-label">{{ __('Language') }}</label><input id="language" name="language" type="text" class="form-control @error('language') is-invalid @enderror" value="{{ old('language', $book->language ?? '') }}" maxlength="100" @if ($errors->has('language')) aria-invalid="true" aria-describedby="language-error" @endif>@error('language')<div class="invalid-feedback" id="language-error">{{ $message }}</div>@enderror</div>
    @if (! isset($book))
        <div class="col-md-4"><label for="total_copies" class="form-label">{{ __('Number of Copies *') }}</label><input id="total_copies" name="total_copies" type="number" min="1" max="1000" class="form-control @error('total_copies') is-invalid @enderror" value="{{ old('total_copies', 1) }}" required @if ($errors->has('total_copies')) aria-invalid="true" aria-describedby="total_copies-error" @endif>@error('total_copies')<div class="invalid-feedback" id="total_copies-error">{{ $message }}</div>@enderror</div>
    @else
        <div class="col-md-4"><label class="form-label">{{ __('Physical copies') }}</label><p class="form-control-plaintext mb-0">{{ $book->copies()->count() }}</p></div>
    @endif
</div>

<script src="{{ asset('js/isbn-lookup.js') }}"></script>

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
                const submit = button;
                if (!name.reportValidity()) return;
                errors.textContent = '';
                submit.disabled = true;
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
                    select.add(new Option(result.name, result.id, true, true));
                    dialog.close();
                    name.value = '';
                } catch (error) {
                    console.error('Catalog quick-add request failed.', error);
                    errors.textContent = 'Could not reach the server. Check your connection and try again.';
                } finally {
                    submit.disabled = false;
                }
            });
        });
    })();
</script>