import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {runInNewContext} from 'node:vm';

const lookupScript = readFileSync(new URL('../../public/js/isbn-lookup.js', import.meta.url), 'utf8');

class FakeElement {
    constructor(value = '') {
        this.value = value;
        this.dataset = {};
        this.options = [];
        this.listeners = new Map();
        this.textContent = '';
        this.disabled = false;
        this.classList = {
            add() {},
            remove() {},
        };
    }

    addEventListener(eventName, listener) {
        const listeners = this.listeners.get(eventName) ?? [];
        listeners.push(listener);
        this.listeners.set(eventName, listeners);
    }

    async dispatch(eventName, event = {}) {
        const listeners = this.listeners.get(eventName) ?? [];
        await Promise.all(listeners.map((listener) => listener(event)));
    }
}

class FakeSelect extends FakeElement {
}

function mountLookup(fetch) {
    const elements = new Map([
        ['isbn', new FakeElement('978-0-306-40615-7')],
        ['isbn-lookup', new FakeElement()],
        ['isbn-lookup-status', new FakeElement()],
        ['isbn-lookup-spinner', new FakeElement()],
        ['title', new FakeElement()],
        ['publication_year', new FakeElement()],
        ['edition', new FakeElement()],
        ['language', new FakeElement()],
        ['author_id', new FakeSelect()],
        ['publisher_id', new FakeSelect()],
    ]);
    elements.get('isbn-lookup').dataset.url = '/admin/books/isbn-lookup';
    elements.get('author_id').options = [
        {text: 'Select author', value: ''},
        {text: 'Existing Author', value: '3'},
        {text: 'Manually selected Author', value: '4'},
    ];
    elements.get('publisher_id').options = [
        {text: 'Select publisher', value: ''},
        {text: 'Example Press', value: '4'},
    ];

    runInNewContext(lookupScript, {
        document: {getElementById: (id) => elements.get(id) ?? null},
        window: {location: {origin: 'http://localhost'}},
        URL,
        HTMLSelectElement: FakeSelect,
        fetch,
        Map,
        Promise,
        TypeError,
        String,
        Array,
    });

    return elements;
}

test('blur lookup normalizes ISBN, deduplicates in-flight calls, and preserves manually edited values', async () => {
    let resolveResponse;
    const requests = [];
    const elements = mountLookup((url) => {
        requests.push(String(url));
        return new Promise((resolve) => {
            resolveResponse = resolve;
        });
    });
    const title = elements.get('title');
    const author = elements.get('author_id');
    title.value = 'Manually entered title';
    await title.dispatch('input');
    author.value = '4';
    await author.dispatch('change');
    assert.equal(author.dataset.userEdited, 'true');
    assert.equal(author.value, '4');

    const firstLookup = elements.get('isbn').dispatch('blur');
    const duplicateLookup = elements.get('isbn').dispatch('blur');
    assert.equal(requests.length, 1);
    assert.match(requests[0], /isbn=9780306406157/);

    resolveResponse({
        ok: true,
        json: async () => ({
            found: true,
            metadata: {
                title: 'API title',
                authors: ['Existing Author'],
                publisher: 'Example Press',
                publication_year: 2001,
                edition: 'Second edition',
                description: 'Metadata description',
                language: 'en',
            },
        }),
    });
    await Promise.all([firstLookup, duplicateLookup]);

    assert.equal(title.value, 'Manually entered title');
    assert.equal(author.value, '4');
    assert.equal(elements.get('publisher_id').value, '4');
    assert.equal(elements.get('publication_year').value, '2001');
    assert.equal(elements.get('edition').value, 'Second edition');
    assert.equal(elements.get('language').value, 'en');
    assert.match(elements.get('isbn-lookup-status').textContent, /Metadata found/);
});
