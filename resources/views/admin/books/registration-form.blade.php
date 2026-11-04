@if ($errors->any())
    <div class="alert alert-danger" id="book-form-errors" role="alert" tabindex="-1">
        <h2 class="h6">{{ __('The book could not be saved. Please correct the following fields:') }}</h2>
        <ul class="mb-0">
            @foreach ($errors->getMessages() as $field => $messages)
                @foreach ($messages as $message)
                    <li><a class="alert-link" href="#{{ $field }}">{{ __(\Illuminate\Support\Str::headline($field)) }}: {{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <label for="title" class="form-label">{{ __('Book title *') }}</label>
        <input id="title" name="title" type="text" maxlength="255" required
            class="form-control @error('title') is-invalid @enderror"
            value="{{ old('title') }}" @if ($errors->has('title')) aria-invalid="true" aria-describedby="title-error" @endif>
        @error('title')<div class="invalid-feedback" id="title-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="book_id" class="form-label">{{ __('Acquisition number *') }}</label>
        <input id="book_id" name="book_id" type="text" maxlength="100" required
            class="form-control @error('book_id') is-invalid @enderror"
            value="{{ old('book_id') }}" @if ($errors->has('book_id')) aria-invalid="true" aria-describedby="book_id-error" @endif>
        @error('book_id')<div class="invalid-feedback" id="book_id-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="isbn" class="form-label">{{ __('ISBN') }}</label>
        <input id="isbn" name="isbn" type="text" maxlength="100"
            class="form-control @error('isbn') is-invalid @enderror"
            value="{{ old('isbn') }}" @if ($errors->has('isbn')) aria-invalid="true" aria-describedby="isbn-error" @endif>
        @error('isbn')<div class="invalid-feedback" id="isbn-error">{{ $message }}</div>@enderror
    </div>

    @include('admin.books.cover-image-field')

    <div class="col-md-8">
        <label for="author_name" class="form-label">{{ __('Author *') }}</label>
        <input id="author_name" name="author_name" type="text" maxlength="255" required
            class="form-control @error('author_name') is-invalid @enderror"
            placeholder="{{ __('Enter author name') }}" value="{{ old('author_name') }}"
            @if ($errors->has('author_name')) aria-invalid="true" aria-describedby="author_name-error" @endif>
        @error('author_name')<div class="invalid-feedback" id="author_name-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label for="publisher_name" class="form-label">{{ __('Publisher *') }}</label>
        <input id="publisher_name" name="publisher_name" type="text" maxlength="255" required
            class="form-control @error('publisher_name') is-invalid @enderror"
            placeholder="{{ __('Enter publisher name') }}" value="{{ old('publisher_name') }}"
            @if ($errors->has('publisher_name')) aria-invalid="true" aria-describedby="publisher_name-error" @endif>
        @error('publisher_name')<div class="invalid-feedback" id="publisher_name-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="category_id" class="form-label">{{ __('Category *') }}</label>
        <select id="category_id" name="category_id" required
            class="form-select @error('category_id') is-invalid @enderror"
            @if ($errors->has('category_id')) aria-invalid="true" aria-describedby="category_id-error" @endif>
            <option value="">{{ __('Select Category') }}</option>
            @foreach ($categoryOptions as $categoryOption)
                <option value="{{ $categoryOption['id'] }}" @selected((string) old('category_id') === (string) $categoryOption['id'])>{{ $categoryOption['label'] }}</option>
            @endforeach
        </select>
        @error('category_id')<div class="invalid-feedback" id="category_id-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="ddc_number" class="form-label">{{ __('DDC number *') }}</label>
        <input id="ddc_number" name="ddc_number" type="text" inputmode="decimal" maxlength="20" required
            class="form-control @error('ddc_number') is-invalid @enderror"
            value="{{ old('ddc_number') }}" @if ($errors->has('ddc_number')) aria-invalid="true" aria-describedby="ddc_number-error" @endif>
        @error('ddc_number')<div class="invalid-feedback" id="ddc_number-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="language" class="form-label">{{ __('Language') }}</label>
        <input id="language" name="language" type="text" maxlength="100"
            class="form-control @error('language') is-invalid @enderror"
            value="{{ old('language') }}" @if ($errors->has('language')) aria-invalid="true" aria-describedby="language-error" @endif>
        @error('language')<div class="invalid-feedback" id="language-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="publication_year" class="form-label">{{ __('Publication year') }}</label>
        <input id="publication_year" name="publication_year" type="number" min="1"
            class="form-control @error('publication_year') is-invalid @enderror"
            value="{{ old('publication_year') }}" @if ($errors->has('publication_year')) aria-invalid="true" aria-describedby="publication_year-error" @endif>
        @error('publication_year')<div class="invalid-feedback" id="publication_year-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="edition" class="form-label">{{ __('Edition') }}</label>
        <input id="edition" name="edition" type="text" maxlength="100"
            class="form-control @error('edition') is-invalid @enderror"
            value="{{ old('edition') }}" @if ($errors->has('edition')) aria-invalid="true" aria-describedby="edition-error" @endif>
        @error('edition')<div class="invalid-feedback" id="edition-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="shelf_number" class="form-label">{{ __('Shelf number') }}</label>
        <input id="shelf_number" name="shelf_number" type="text" maxlength="100"
            class="form-control @error('shelf_number') is-invalid @enderror"
            value="{{ old('shelf_number') }}" @if ($errors->has('shelf_number')) aria-invalid="true" aria-describedby="shelf_number-error" @endif>
        @error('shelf_number')<div class="invalid-feedback" id="shelf_number-error">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="total_copies" class="form-label">{{ __('Number of copies *') }}</label>
        <input id="total_copies" name="total_copies" type="number" min="1" max="1000" required
            class="form-control @error('total_copies') is-invalid @enderror"
            value="{{ old('total_copies', 1) }}" @if ($errors->has('total_copies')) aria-invalid="true" aria-describedby="total_copies-error" @endif>
        @error('total_copies')<div class="invalid-feedback" id="total_copies-error">{{ $message }}</div>@enderror
    </div>
</div>
