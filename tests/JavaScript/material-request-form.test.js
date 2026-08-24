import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { Window } from 'happy-dom';

const bladePath = fileURLToPath(
    new URL('../../resources/views/material-requests/index.blade.php', import.meta.url),
);

/** Skrip inline halaman Request Material adalah artefak yang benar-benar dikirim ke browser. */
const inlineScript = () => {
    const blocks = [...readFileSync(bladePath, 'utf8').matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]);
    assert.ok(blocks.length > 0, 'blok <script> halaman Request Material tidak ditemukan');

    return blocks.join('\n');
};

const materialOptions = `
    <option value="">Pilih Material</option>
    <option value="1" data-kind="biasa">MAT-1 — Kabel Patch (pcs)</option>
    <option value="2" data-kind="ber_sn">MAT-2 — ONT (pcs)</option>
    <option value="3" data-kind="drum_kabel">MAT-3 — Drum Kabel (meter)</option>
`;

const itemMarkup = (index) => `
    <div class="material-request-item">
        <label>Material<select name="items[${index}][material_id]" required>${materialOptions}</select></label>
        <label>Qty <input name="items[${index}][qty]" type="number" min="0.001" step="0.001" required></label>
    </div>
`;

const requestPage = (t) => {
    const window = new Window({ url: 'https://example.test/material-requests' });
    window.document.body.innerHTML = `
        <form>
            <div id="material-request-items">${itemMarkup(0)}</div>
            <button type="button" id="add-material-request-item">Tambah Material</button>
        </form>
        <template id="material-request-item-template">${itemMarkup('__INDEX__')}</template>
    `;
    window.eval(inlineScript());
    t.after(async () => {
        await window.close();
    });

    return window;
};

const rows = (window) => [...window.document.querySelectorAll('.material-request-item')];

const choose = (window, row, value) => {
    const select = row.querySelector('select');
    select.value = value;
    select.dispatchEvent(new window.Event('change', { bubbles: true }));
};

const qty = (row) => row.querySelector('input[type="number"]');

test('memilih material ber-SN mengunci Qty ke bilangan bulat', (t) => {
    const window = requestPage(t);
    const [row] = rows(window);

    choose(window, row, '2');

    assert.equal(qty(row).getAttribute('step'), '1', 'satu Serial Number adalah tepat satu pcs');
    assert.equal(qty(row).getAttribute('min'), '1', 'setengah pcs bukan benda');
});

test('material biasa dan drum kabel sama-sama mengunci Qty ke bilangan bulat', (t) => {
    const window = requestPage(t);
    const [biasa] = rows(window);

    choose(window, biasa, '1');

    assert.equal(qty(biasa).getAttribute('step'), '1', 'material biasa dihitung per unit utuh');
    assert.equal(qty(biasa).getAttribute('min'), '1');

    window.document.getElementById('add-material-request-item').click();
    const drum = rows(window).at(-1);
    choose(window, drum, '3');

    assert.equal(qty(drum).getAttribute('step'), '1', 'kabel dihitung per meter utuh');
    assert.equal(qty(drum).getAttribute('min'), '1');
});

test('mengganti pilihan kembali ke material biasa melepas kuncinya', (t) => {
    const window = requestPage(t);
    const [row] = rows(window);
    choose(window, row, '2');
    assert.equal(qty(row).getAttribute('step'), '1', 'prasyarat: qty terkunci');

    choose(window, row, '1');

    assert.equal(qty(row).getAttribute('step'), '0.001');
});

test('baris Material tambahan ikut mengunci Qty-nya sendiri', (t) => {
    const window = requestPage(t);
    window.document.getElementById('add-material-request-item').click();
    const tambahan = rows(window).at(-1);

    choose(window, tambahan, '2');

    assert.equal(qty(tambahan).getAttribute('step'), '1', 'baris klon tidak boleh lolos dari aturan yang sama');
    assert.equal(qty(rows(window)[0]).getAttribute('step'), '0.001', 'baris lain tidak ikut terpengaruh');
});

test('markup awal Qty tidak mengunci apa pun sebelum material dipilih', (t) => {
    const window = requestPage(t);

    assert.equal(qty(rows(window)[0]).getAttribute('step'), '0.001');
});
