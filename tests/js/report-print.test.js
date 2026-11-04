import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {runInNewContext} from 'node:vm';

const printScript = readFileSync(new URL('../../public/js/report-print.js', import.meta.url), 'utf8');

class FakeElement {
    constructor() {
        this.dataset = {imageError: 'Logo failed'};
        this.disabled = false;
        this.listeners = new Map();
    }

    addEventListener(eventName, listener) {
        const listeners = this.listeners.get(eventName) ?? [];
        listeners.push(listener);
        this.listeners.set(eventName, listeners);
    }

    removeEventListener(eventName, listener) {
        this.listeners.set(eventName, (this.listeners.get(eventName) ?? []).filter((item) => item !== listener));
    }

    async dispatch(eventName) {
        await Promise.all((this.listeners.get(eventName) ?? []).map((listener) => listener()));
    }
}

function mountPrint(image) {
    const button = new FakeElement();
    const documentListeners = new Map();
    const calls = {print: 0, alerts: []};

    runInNewContext(printScript, {
        document: {
            addEventListener: (eventName, listener) => documentListeners.set(eventName, listener),
            querySelector: () => button,
            querySelectorAll: () => [image],
        },
        window: {
            alert: (message) => calls.alerts.push(message),
            clearTimeout,
            print: () => calls.print++,
            setTimeout,
        },
        Array,
        Promise,
    });
    documentListeners.get('DOMContentLoaded')();

    return {button, calls};
}

test('report printing waits for the logo to load', async () => {
    const image = new FakeElement();
    image.complete = false;
    image.naturalWidth = 0;
    const {button, calls} = mountPrint(image);

    const printPromise = button.dispatch('click');
    assert.equal(button.disabled, true);
    assert.equal(calls.print, 0);

    image.naturalWidth = 64;
    await image.dispatch('load');
    await printPromise;

    assert.equal(calls.print, 1);
    assert.deepEqual(calls.alerts, []);
    assert.equal(button.disabled, false);
});

test('report printing is cancelled with a message when the logo fails to load', async () => {
    const image = new FakeElement();
    image.complete = false;
    image.naturalWidth = 0;
    const {button, calls} = mountPrint(image);

    const printPromise = button.dispatch('click');
    await image.dispatch('error');
    await printPromise;

    assert.equal(calls.print, 0);
    assert.deepEqual(calls.alerts, ['Logo failed']);
});