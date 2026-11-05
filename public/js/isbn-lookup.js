(() => {
    const isbnInput = document.getElementById('isbn');
    const lookupButton = document.getElementById('isbn-lookup');
    const status = document.getElementById('isbn-lookup-status');

    if (!isbnInput || !lookupButton || !status) return;

    const fields = {
        title: document.getElementById('title'),
        publication_year: document.getElementById('publication_year'),
        edition: document.getElementById('edition'),
        language: document.getElementById('language'),
        author: document.getElementById('author_id') || document.getElementById('author_name'),
        publisher: document.getElementById('publisher_id') || document.getElementById('publisher_name'),
    };
    const spinner = document.getElementById('isbn-lookup-spinner');
    const lookupCache = new Map();
    let lookupSequence = 0;

    Object.values(fields).filter(Boolean).forEach((field) => {
        field.addEventListener(field instanceof HTMLSelectElement ? 'change' : 'input', () => {
            field.dataset.userEdited = 'true';
        });
    });

    const normalizeIsbn = (value) => value.trim().replace(/[\s-]+/g, '').toUpperCase();
    const fieldHasValue = (field) => field instanceof HTMLSelectElement
        ? field.value !== ''
        : field.value.trim() !== '';

    const fillIfEmpty = (field, value) => {
        if (!field || value === null || value === undefined || String(value).trim() === ''
            || field.dataset.userEdited === 'true' || fieldHasValue(field)) {
            return;
        }

        field.value = String(value);
    };

    const selectExisting = (select, names) => {
        if (!select || !Array.isArray(names) || names.length === 0) {
            return false;
        }

        if (!(select instanceof HTMLSelectElement)) {
            if (names.length === 1) fillIfEmpty(select, names[0]);
            return false;
        }

        if (select.dataset.userEdited === 'true' || fieldHasValue(select)) return false;
        if (names.length !== 1) return true;

        const expectedName = names[0].trim().toLocaleLowerCase();
        const option = Array.from(select.options).find(
            (item) => item.text.trim().toLocaleLowerCase() === expectedName
        );
        if (!option) return true;

        select.value = option.value;
        return false;
    };

    const loadLookup = async (isbn) => {
        const url = new URL(lookupButton.dataset.url, window.location.origin);
        url.searchParams.set('isbn', isbn);
        if (lookupButton.dataset.currentBookId) {
            url.searchParams.set('current_book_id', lookupButton.dataset.currentBookId);
        }

        const response = await fetch(url, {
            headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        });
        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(Object.values(result.errors ?? {}).flat().join(' ')
                || result.message
                || `Lookup failed (HTTP ${response.status}).`);
        }

        return result;
    };

    const lookup = async () => {
        const isbn = normalizeIsbn(isbnInput.value);
        if (!isbn) {
            status.textContent = '';
            return;
        }

        const sequence = ++lookupSequence;
        status.textContent = 'Looking up ISBN…';
        lookupButton.disabled = true;
        spinner?.classList.remove('d-none');

        try {
            let result = lookupCache.get(isbn);
            if (!result) {
                const request = loadLookup(isbn);
                lookupCache.set(isbn, request);
                try {
                    result = await request;
                    lookupCache.set(isbn, result);
                } catch (error) {
                    lookupCache.delete(isbn);
                    throw error;
                }
            } else if (typeof result.then === 'function') {
                result = await result;
            }

            if (sequence !== lookupSequence || normalizeIsbn(isbnInput.value) !== isbn) return;

            if (result.existing_book) {
                status.textContent = `This ISBN is already in the catalog: ${result.existing_book.title} (${result.existing_book.book_id}). Do not create a duplicate.`;
                return;
            }

            if (!result.found || !result.metadata) {
                status.textContent = result.message || 'No matching book was found. You can enter the details manually.';
                return;
            }

            const metadata = result.metadata;
            fillIfEmpty(fields.title, metadata.title);
            fillIfEmpty(fields.publication_year, metadata.publication_year);
            fillIfEmpty(fields.edition, metadata.edition);
            fillIfEmpty(fields.language, metadata.language);

            const authorNeedsAdding = selectExisting(fields.author, metadata.authors || []);
            const publisherNeedsAdding = selectExisting(
                fields.publisher,
                metadata.publisher ? [metadata.publisher] : []
            );
            const catalogItemsToAdd = [
                authorNeedsAdding ? (metadata.authors.length > 1 ? 'authors' : 'author') : '',
                publisherNeedsAdding ? 'publisher' : '',
            ].filter(Boolean);

            status.textContent = `Metadata found. Review and edit the details before saving.${catalogItemsToAdd.length ? ` Add or select the ${catalogItemsToAdd.join(' and ')} using the catalog controls.` : ''}`;
        } catch (error) {
            if (sequence === lookupSequence) {
                status.textContent = error instanceof TypeError
                    ? 'Could not reach the lookup service. You can enter the details manually.'
                    : error.message;
            }
        } finally {
            if (sequence === lookupSequence) {
                lookupButton.disabled = false;
                spinner?.classList.add('d-none');
            }
        }
    };

    isbnInput.addEventListener('blur', lookup);
    isbnInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookup();
        }
    });
    lookupButton.addEventListener('click', lookup);
})();
