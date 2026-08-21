import assert from 'node:assert/strict';
import test from 'node:test';
import { Window } from 'happy-dom';

import {
    filterSearchableOptions,
    initializeSearchableSelects,
} from '../../resources/js/searchable-select.js';

test('searchable select matches labels and identifiers case-insensitively', () => {
    const options = [
        { label: 'Warehouse Depok - WHJWB21060001', searchText: 'WHJWB21060001 Warehouse Depok' },
        { label: 'Warehouse Pontianak - WHKLB21060001', searchText: 'WHKLB21060001 Warehouse Pontianak' },
    ];

    assert.deepEqual(filterSearchableOptions(options, 'depok'), [options[0]]);
    assert.deepEqual(filterSearchableOptions(options, '21060001'), options);
    assert.deepEqual(filterSearchableOptions(options, 'not found'), []);
});

test('form reset restores the default hidden value and label', async () => {
    const { window, document } = selectFixture({ value: '2' });
    document.querySelector('[data-value="1"]').click();
    document.querySelector('form').reset();
    await window.happyDOM.whenAsyncComplete();

    assert.equal(document.querySelector('[data-ui-select-value]').value, '2');
    assert.equal(document.querySelector('[data-ui-select-label]').textContent, 'Mitra B');

    await window.close();
});

test('native select remains usable until JavaScript enhancement is initialized', async () => {
    const { window, document } = selectFixture({ value: '2', enhance: false });
    const nativeSelect = document.querySelector('[data-ui-select-native]');
    const customSelect = document.querySelector('[data-ui-select]');
    const hiddenValue = document.querySelector('[data-ui-select-value]');

    assert.equal(nativeSelect.hidden, false);
    assert.equal(nativeSelect.disabled, false);
    assert.equal(nativeSelect.value, '2');
    assert.equal(customSelect.hidden, true);
    assert.equal(hiddenValue.disabled, true);

    initializeSearchableSelects(document);
    document.dispatchEvent(new window.Event('DOMContentLoaded'));
    assert.equal(nativeSelect.hidden, true);
    assert.equal(nativeSelect.disabled, true);
    assert.equal(customSelect.hidden, false);
    assert.equal(hiddenValue.disabled, false);

    await window.close();
});

test('mouse interaction opens, selects, clears, and closes outside the select', async () => {
    const { window, document } = selectFixture();
    const trigger = document.querySelector('[data-ui-select-trigger]');
    const popup = document.querySelector('[data-ui-select-popup]');
    const first = document.querySelector('[data-value="1"]');

    trigger.click();
    assert.equal(popup.hidden, false);
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');

    trigger.click();
    assert.equal(popup.hidden, true);
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');

    trigger.click();

    first.click();
    assert.equal(document.querySelector('[data-ui-select-value]').value, '1');
    assert.equal(document.querySelector('[data-ui-select-label]').textContent, 'Mitra A');
    assert.equal(first.getAttribute('aria-selected'), 'true');
    assert.equal(popup.hidden, true);

    document.querySelector('[data-ui-select-clear]').click();
    assert.equal(document.querySelector('[data-ui-select-value]').value, '');
    assert.equal(document.querySelector('[data-ui-select-label]').textContent, 'Pilih Mitra');

    trigger.click();
    document.body.click();
    assert.equal(popup.hidden, true);
    assert.equal(trigger.getAttribute('aria-expanded'), 'false');

    await window.close();
});

test('keyboard interaction opens, moves focus, selects, and escapes to the trigger', async () => {
    const { window, document } = selectFixture();
    const trigger = document.querySelector('[data-ui-select-trigger]');
    const search = document.querySelector('[data-ui-select-search]');
    const [first, second] = document.querySelectorAll('[data-ui-select-option]');

    trigger.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    assert.equal(document.activeElement, search);

    search.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    assert.equal(document.activeElement, first);
    first.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    assert.equal(document.activeElement, second);
    second.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }));
    assert.equal(document.activeElement, first);
    first.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    assert.equal(document.querySelector('[data-ui-select-value]').value, '1');
    assert.equal(document.activeElement, trigger);

    for (const key of ['Enter', ' ']) {
        trigger.dispatchEvent(new window.KeyboardEvent('keydown', { key, bubbles: true }));
        assert.equal(document.querySelector('[data-ui-select-popup]').hidden, false);
        search.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        assert.equal(document.activeElement, trigger);
    }

    await window.close();
});

test('filtering exposes empty state and a disabled select cannot open', async () => {
    const enabled = selectFixture();
    const search = enabled.document.querySelector('[data-ui-select-search]');
    enabled.document.querySelector('[data-ui-select-trigger]').click();
    search.value = 'mtr-b';
    search.dispatchEvent(new enabled.window.Event('input', { bubbles: true }));
    assert.equal(enabled.document.querySelector('[data-value="1"]').hidden, true);
    assert.equal(enabled.document.querySelector('[data-value="2"]').hidden, false);

    search.value = 'tidak ada';
    search.dispatchEvent(new enabled.window.Event('input', { bubbles: true }));
    assert.equal(enabled.document.querySelector('[data-ui-select-empty]').hidden, false);
    await enabled.window.close();

    const disabled = selectFixture({ disabled: true });
    disabled.document.querySelector('[data-ui-select-trigger]').click();
    assert.equal(disabled.document.querySelector('[data-ui-select-popup]').hidden, true);
    await disabled.window.close();
});

function selectFixture({ value = '', disabled = false, enhance = true } = {}) {
    const window = new Window();
    const { document } = window;
    document.body.innerHTML = `
        <form>
            <select name="mitra_id" data-ui-select-native ${disabled ? 'disabled' : ''}>
                <option value="">Pilih Mitra</option>
                <option value="1" ${value === '1' ? 'selected' : ''}>Mitra A</option>
                <option value="2" ${value === '2' ? 'selected' : ''}>Mitra B</option>
            </select>
            <div data-ui-select data-placeholder="Pilih Mitra" data-clearable="true" data-disabled="${disabled}" hidden>
                <button type="button" data-ui-select-trigger aria-expanded="false" ${disabled ? 'disabled' : ''}><span data-ui-select-label></span></button>
                <button type="button" data-ui-select-clear hidden>Clear</button>
                <input type="hidden" name="mitra_id" value="${value}" data-ui-select-value disabled>
                <div data-ui-select-popup hidden>
                    <input type="search" data-ui-select-search>
                    <button type="button" data-ui-select-option data-value="1" data-label="Mitra A" data-search-text="MTR-A">Mitra A</button>
                    <button type="button" data-ui-select-option data-value="2" data-label="Mitra B" data-search-text="MTR-B">Mitra B</button>
                    <div data-ui-select-empty hidden>Tidak ditemukan</div>
                </div>
            </div>
        </form>`;
    document.querySelector('[data-ui-select-native]').value = value;

    if (enhance) {
        initializeSearchableSelects(document);
        document.dispatchEvent(new window.Event('DOMContentLoaded'));
    }

    return { window, document };
}
