@php($coverPreviewUrl = isset($book) ? $book->cover_image_url : asset('images/book-placeholder.svg'))

<div class="col-md-8">
    <label for="cover_image" class="form-label">{{ __('Book Cover Image') }}</label>
    <input id="cover_image" name="cover_image" type="file"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        class="form-control @error('cover_image') is-invalid @enderror"
        aria-describedby="cover_image-help cover_image-error"
        @if ($errors->has('cover_image')) aria-invalid="true" @endif>
    <div class="form-text" id="cover_image-help">{{ __('JPG, JPEG, PNG or WEBP. Maximum size: 2 MB.') }}</div>
    @error('cover_image')<div class="invalid-feedback" id="cover_image-error">{{ $message }}</div>@enderror
    <img id="cover-image-preview" src="{{ $coverPreviewUrl }}" data-fallback="{{ $coverPreviewUrl }}"
        alt="{{ __('Book cover preview') }}" class="book-cover-detail mt-2">
    @if (isset($book) && $book->cover_image)
        <div class="form-check mt-2">
            <input id="remove_cover_image" name="remove_cover_image" type="checkbox" value="1"
                class="form-check-input @error('remove_cover_image') is-invalid @enderror"
                @checked(old('remove_cover_image'))>
            <label for="remove_cover_image" class="form-check-label">{{ __('Remove current cover image') }}</label>
            @error('remove_cover_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif
</div>

<script>
    (() => {
        const input = document.getElementById('cover_image');
        const preview = document.getElementById('cover-image-preview');
        if (!input || !preview) return;

        let previewUrl = null;
        input.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);

            const [file] = input.files;
            if (!file) {
                previewUrl = null;
                preview.src = preview.dataset.fallback;
                return;
            }

            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
        });
    })();
</script>
