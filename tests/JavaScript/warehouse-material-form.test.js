import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { Window } from 'happy-dom';

const bladePath = fileURLToPath(
    new URL('../../resources/views/warehouse/index.blade.php', import.meta.url),
);

/** Skrip inline halaman Warehouse adalah artefak yang benar-benar dikirim ke browser. */
const inlineScript = () => {
    const blade = readFileSync(bladePath, 'utf8');
    const match = blade.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'blok <script> halaman Warehouse tidak ditemukan');

    return match[1];
};

const materialOptions = `
    <option value="1" data-kind="biasa">MAT-1 — Kabel Patch</option>
    <option value="2" data-kind="ber_sn">MAT-2 — ONT</option>
    <option value="3" data-kind="drum_kabel">MAT-3 — Drum Kabel</option>
`;

const identityFields = (prefix) => `
    <label data-identity="serial_number" hidden>Serial Number<input name="${prefix}[serial_number]"></label>
    <label data-identity="drum_id" hidden>Drum ID<input name="${prefix}[drum_id]"></label>
`;

const warehousePage = () => {
    const window = new Window({ url: 'https://example.test/warehouse' });

    window.document.body.innerHTML = `
        <form data-submit-loading action="/warehouse/stock/receive">
            <label>Material<select name="material_id" data-material-select required>
                <option value="">Pilih Material</option>${materialOptions}
            </select></label>
            ${identityFields('receive')}
            <button type="submit">Catat penerimaan</button>
        </form>
        <form data-submit-loading action="/warehouse/stock/issue">
            <label>Material<select name="material_id" data-material-select required>
                <option value="">Pilih Material</option>${materialOptions}
            </select></label>
            ${identityFields('issue')}
            <button type="submit">Catat pengeluaran</button>
        </form>
        <form data-submit-loading data-transfer-form target="_blank" rel="noopener noreferrer">
            <div data-transfer-items>
                <div class="ui-list__item" data-transfer-row>
                    <label>Material<select name="items[0][material_id]" required>
                        <option value="">Pilih Material</option>${materialOptions}
                    </select></label>
                    <label>Qty<input type="number" name="items[0][qty]" required></label>
                    ${identityFields('items[0]')}
                    <button type="button" data-remove-item hidden>Hapus item</button>
                </div>
            </div>
            <button type="button" data-add-item>Tambah item</button>
            <button type="submit">Terbitkan Surat Jalan</button>
        </form>
    `;

    window.eval(inlineScript());

    return window;
};

const rows = (window) => [...window.document.querySelectorAll('[data-transfer-row]')];

const identity = (row, name) => row.querySelector(`[data-identity="${name}"]`);

/** Form Penerimaan/Pengeluaran memakai satu select tanpa baris item. */
const stockForm = (window, action) => window.document.querySelector(`form[action="/warehouse/stock/${action}"]`);

const choose = (window, select, value) => {
    select.value = value;
    select.dispatchEvent(new window.Event('change'));
};

test('tombol Tambah item menambah baris item Surat Jalan', () => {
    const window = warehousePage();

    window.document.querySelector('[data-add-item]').click();

    assert.equal(rows(window).length, 2);
    assert.equal(
        rows(window)[1].querySelector('select').name,
        'items[1][material_id]',
        'baris baru harus memakai indeks berikutnya',
    );
});

test('memilih material ber-SN membuka kotak Serial Number pada baris Surat Jalan', () => {
    const window = warehousePage();
    const row = rows(window)[0];

    choose(window, row.querySelector('select'), '2');

    assert.equal(identity(row, 'serial_number').hidden, false);
    assert.equal(identity(row, 'serial_number').querySelector('input').required, true);
    assert.equal(identity(row, 'drum_id').hidden, true);
});

test('identitas satu baris tidak bocor ke baris Surat Jalan lain', () => {
    const window = warehousePage();
    window.document.querySelector('[data-add-item]').click();
    const [first, second] = rows(window);

    choose(window, first.querySelector('select'), '3');

    assert.equal(identity(first, 'drum_id').hidden, false);
    assert.equal(identity(second, 'drum_id').hidden, true, 'baris lain harus tetap tertutup');
    assert.equal(
        identity(second, 'drum_id').querySelector('input').required,
        false,
        'baris lain tidak boleh ikut wajib diisi',
    );
});

test('field identitas yang tersembunyi tidak pernah wajib diisi', () => {
    const window = warehousePage();
    const row = rows(window)[0];

    choose(window, row.querySelector('select'), '3');
    choose(window, row.querySelector('select'), '1');

    for (const field of window.document.querySelectorAll('[data-identity]')) {
        if (field.hidden) {
            assert.equal(
                field.querySelector('input').required,
                false,
                `${field.dataset.identity} tersembunyi tapi masih required`,
            );
        }
    }
});

test('baris item Surat Jalan dimulai tanpa material terpilih dan tanpa kotak identitas', () => {
    const window = warehousePage();
    const row = rows(window)[0];

    assert.equal(row.querySelector('select').value, '', 'material tidak boleh terpilih diam-diam');
    assert.equal(identity(row, 'serial_number').hidden, true);
    assert.equal(identity(row, 'drum_id').hidden, true);
});

test('markup baris item Surat Jalan menyediakan opsi placeholder Pilih Material', () => {
    const blade = readFileSync(bladePath, 'utf8');
    const row = blade.match(/data-transfer-row>([\s\S]*?)data-remove-item/);

    assert.ok(row, 'baris item Surat Jalan tidak ditemukan');
    assert.match(row[1], /<option value="">Pilih Material<\/option>/);
});

/** Menunggu penjaga UI, tanpa mengikat test ke durasi pemulihan yang persis. */
const waitFor = async (predicate, message) => {
    for (let attempt = 0; attempt < 200; attempt += 1) {
        if (predicate()) {
            return;
        }

        await new Promise((resolve) => setTimeout(resolve, 25));
    }

    assert.fail(message);
};

test('tombol Terbitkan Surat Jalan bisa dipakai lagi setelah Surat Jalan terbit di tab baru', async () => {
    const window = warehousePage();
    const form = window.document.querySelector('[data-transfer-form]');
    const submit = form.querySelector('button[type="submit"]');

    form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));

    assert.equal(submit.disabled, true, 'tombol harus terkunci selama Surat Jalan dikirim');
    assert.equal(submit.textContent, 'Ops…');

    await waitFor(
        () => submit.disabled === false,
        'halaman tidak pernah berpindah karena Surat Jalan terbit di tab baru, jadi tombol terkunci selamanya',
    );

    assert.equal(submit.textContent, 'Terbitkan Surat Jalan', 'label semula harus dikembalikan');
});

test('form yang berpindah halaman tetap terkunci setelah dikirim', async () => {
    const window = warehousePage();
    const form = window.document.querySelector('[data-submit-loading]:not([data-transfer-form])');
    const submit = form.querySelector('button[type="submit"]');

    form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));

    assert.equal(submit.disabled, true);

    await new Promise((resolve) => setTimeout(resolve, 1200));

    assert.equal(
        submit.disabled,
        true,
        'halaman sedang berpindah, jadi memulihkan tombol hanya membuka celah kirim ganda',
    );
});

test('memilih material ber-SN pada form Penerimaan membuka kotak Serial Number', () => {
    const window = warehousePage();
    const form = stockForm(window, 'receive');

    choose(window, form.querySelector('[data-material-select]'), '2');

    assert.equal(identity(form, 'serial_number').hidden, false);
    assert.equal(identity(form, 'serial_number').querySelector('input').required, true);
    assert.equal(identity(form, 'drum_id').hidden, true);
});

test('identitas form Penerimaan tidak bocor ke form Pengeluaran', () => {
    const window = warehousePage();
    const receive = stockForm(window, 'receive');
    const issue = stockForm(window, 'issue');

    choose(window, receive.querySelector('[data-material-select]'), '3');

    assert.equal(identity(receive, 'drum_id').hidden, false);
    assert.equal(identity(issue, 'drum_id').hidden, true, 'form lain harus tetap tertutup');
    assert.equal(
        identity(issue, 'drum_id').querySelector('input').required,
        false,
        'form lain tidak boleh ikut wajib diisi',
    );
});

test('tombol Hapus item membuang baris item Surat Jalan tambahan', () => {
    const window = warehousePage();
    window.document.querySelector('[data-add-item]').click();
    assert.equal(rows(window).length, 2, 'prasyarat: ada baris kedua untuk dibuang');

    rows(window)[1].querySelector('[data-remove-item]').click();

    assert.equal(rows(window).length, 1);
    assert.equal(
        rows(window)[0].querySelector('[data-remove-item]').hidden,
        true,
        'baris pertama tidak boleh bisa dibuang, Surat Jalan wajib punya minimal satu item',
    );
});

test('baris item Surat Jalan baru tidak mewarisi isian baris sebelumnya', () => {
    const window = warehousePage();
    const first = rows(window)[0];
    choose(window, first.querySelector('select'), '2');
    first.querySelector('[name="items[0][qty]"]').value = '7';
    first.querySelector('[name="items[0][serial_number]"]').value = 'SN-LAMA';

    window.document.querySelector('[data-add-item]').click();
    const clone = rows(window)[1];

    assert.equal(clone.querySelector('select').value, '', 'material harus kembali ke placeholder');
    assert.equal(clone.querySelector('[name="items[1][qty]"]').value, '');
    assert.equal(clone.querySelector('[name="items[1][serial_number]"]').value, '');
    assert.equal(identity(clone, 'serial_number').hidden, true, 'kotak identitas warisan harus tertutup');
});
