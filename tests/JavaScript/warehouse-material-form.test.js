import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test, { describe, it } from 'node:test';
import { Window } from 'happy-dom';

const bladePath = fileURLToPath(
    new URL('../../resources/views/warehouse/index.blade.php', import.meta.url),
);

const blade = () => readFileSync(bladePath, 'utf8');

/** Skrip inline halaman Warehouse adalah artefak yang benar-benar dikirim ke browser. */
const inlineScript = () => {
    const blocks = [...blade().matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]);
    assert.ok(blocks.length > 0, 'blok <script> halaman Warehouse tidak ditemukan');

    return blocks.join('\n');
};

/** Tenggat test diturunkan dari konstanta aplikasi, bukan angka yang disalin tangan. */
const restoreSubmitAfterMs = () => {
    const match = inlineScript().match(/RESTORE_SUBMIT_AFTER_MS = (\d+)/);
    assert.ok(match, 'konstanta RESTORE_SUBMIT_AFTER_MS tidak ditemukan');

    return Number(match[1]);
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

/** Fixture happy-dom ditutup lewat `t.after`, seperti tests/JavaScript/searchable-select.test.js. */
const openPage = (t, body) => {
    const window = new Window({ url: 'https://example.test/warehouse' });
    window.document.body.innerHTML = body;
    window.eval(inlineScript());
    t.after(async () => {
        await window.close();
    });

    return window;
};

const warehousePage = (t) => openPage(t, `
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
`);

/**
 * Blade belum punya form ber-target selain `_blank`. Fixture ini sengaja sintetis:
 * ia mengunci aturannya supaya menambah target lain nanti tidak diam-diam
 * membuka celah kirim ganda — atau mengunci tombol selamanya.
 */
const pageWithTargetedForms = (t, targets) => openPage(t, targets.map((target) => `
    <form data-submit-loading target="${target}" action="/warehouse/stock/receive" data-target="${target}">
        <button type="submit">Catat penerimaan</button>
    </form>
`).join(''));

const targetedForm = (window, target) => window.document.querySelector(`[data-target="${target}"]`);

const rows = (window) => [...window.document.querySelectorAll('[data-transfer-row]')];

const identity = (row, name) => row.querySelector(`[data-identity="${name}"]`);

/** Form Penerimaan/Pengeluaran memakai satu select tanpa baris item. */
const stockForm = (window, action) => window.document.querySelector(`form[action="/warehouse/stock/${action}"]`);

const choose = (window, select, value) => {
    select.value = value;
    select.dispatchEvent(new window.Event('change'));
};

/** Kotak identitas hanya boleh terbuka di scope yang materialnya dipilih. */
const assertIdentitasTidakBocor = (terpilih, lain, label) => {
    assert.equal(identity(terpilih, 'drum_id').hidden, false);
    assert.equal(identity(lain, 'drum_id').hidden, true, `${label} harus tetap tertutup`);
    assert.equal(
        identity(lain, 'drum_id').querySelector('input').required,
        false,
        `${label} tidak boleh ikut wajib diisi`,
    );
};

test('tombol Tambah item menambah baris item Surat Jalan', (t) => {
    const window = warehousePage(t);

    window.document.querySelector('[data-add-item]').click();

    assert.equal(rows(window).length, 2);
    assert.equal(
        rows(window)[1].querySelector('select').name,
        'items[1][material_id]',
        'baris baru harus memakai indeks berikutnya',
    );
});

test('memilih material ber-SN membuka kotak Serial Number pada baris Surat Jalan', (t) => {
    const window = warehousePage(t);
    const row = rows(window)[0];

    choose(window, row.querySelector('select'), '2');

    assert.equal(identity(row, 'serial_number').hidden, false);
    assert.equal(identity(row, 'serial_number').querySelector('input').required, true);
    assert.equal(identity(row, 'drum_id').hidden, true);
});

test('identitas satu baris tidak bocor ke baris Surat Jalan lain', (t) => {
    const window = warehousePage(t);
    window.document.querySelector('[data-add-item]').click();
    const [first, second] = rows(window);

    choose(window, first.querySelector('select'), '3');

    assertIdentitasTidakBocor(first, second, 'baris lain');
});

test('field identitas yang tersembunyi tidak pernah wajib diisi', (t) => {
    const window = warehousePage(t);
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

test('baris item Surat Jalan dimulai tanpa material terpilih dan tanpa kotak identitas', (t) => {
    const window = warehousePage(t);
    const row = rows(window)[0];

    assert.equal(row.querySelector('select').value, '', 'material tidak boleh terpilih diam-diam');
    assert.equal(identity(row, 'serial_number').hidden, true);
    assert.equal(identity(row, 'drum_id').hidden, true);
});

test('markup baris item Surat Jalan menyediakan opsi placeholder Pilih Material', () => {
    const select = blade().match(/<select name="items\[0\]\[material_id\]"[^>]*>([\s\S]*?)<\/select>/);

    assert.ok(select, 'select Material baris item Surat Jalan tidak ditemukan');
    assert.match(
        select[1].trimStart(),
        /^<option value="">Pilih Material<\/option>/,
        'placeholder harus jadi opsi pertama',
    );
});

test('memilih material ber-SN pada form Penerimaan membuka kotak Serial Number', (t) => {
    const window = warehousePage(t);
    const form = stockForm(window, 'receive');

    choose(window, form.querySelector('[data-material-select]'), '2');

    assert.equal(identity(form, 'serial_number').hidden, false);
    assert.equal(identity(form, 'serial_number').querySelector('input').required, true);
    assert.equal(identity(form, 'drum_id').hidden, true);
});

test('identitas form Penerimaan tidak bocor ke form Pengeluaran', (t) => {
    const window = warehousePage(t);
    const receive = stockForm(window, 'receive');

    choose(window, receive.querySelector('[data-material-select]'), '3');

    assertIdentitasTidakBocor(receive, stockForm(window, 'issue'), 'form lain');
});

test('tombol Hapus item membuang baris item Surat Jalan tambahan', (t) => {
    const window = warehousePage(t);
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

test('baris item Surat Jalan baru tidak mewarisi isian baris sebelumnya', (t) => {
    const window = warehousePage(t);
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

test('memilih material pada baris item hasil klon membuka kotak identitasnya sendiri', (t) => {
    const window = warehousePage(t);
    window.document.querySelector('[data-add-item]').click();
    const [first, clone] = rows(window);

    choose(window, clone.querySelector('select'), '3');

    assertIdentitasTidakBocor(clone, first, 'baris asal');
});

/** Semuanya menunggu timer sungguhan, jadi dijalankan berbarengan agar suite tidak melar. */
describe('daur hidup tombol submit', { concurrency: true }, () => {
    /** Menunggu penjaga UI dengan tenggat yang diturunkan dari konstanta aplikasi. */
    const waitFor = async (predicate, message) => {
        const deadline = Date.now() + restoreSubmitAfterMs() * 3;

        while (Date.now() < deadline) {
            if (predicate()) {
                return;
            }

            await new Promise((resolve) => setTimeout(resolve, 25));
        }

        assert.fail(message);
    };

    /** Lewati tenggat pemulihan, supaya tombol yang tetap terkunci bisa dibuktikan. */
    const waitOutRestore = () => new Promise((resolve) => {
        setTimeout(resolve, restoreSubmitAfterMs() + 200);
    });

    it('tombol Terbitkan Surat Jalan bisa dipakai lagi setelah Surat Jalan terbit di tab baru', async (t) => {
        const window = warehousePage(t);
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

    it('form tanpa target tetap terkunci setelah dikirim', async (t) => {
        const window = warehousePage(t);
        const form = window.document.querySelector('[data-submit-loading]:not([data-transfer-form])');
        const submit = form.querySelector('button[type="submit"]');

        form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.equal(submit.disabled, true, 'prasyarat: tombol terkunci saat dikirim');

        await waitOutRestore();

        assert.equal(
            submit.disabled,
            true,
            'halaman sedang berpindah, jadi memulihkan tombol hanya membuka celah kirim ganda',
        );
    });

    it('setiap target yang memindahkan halaman ini membiarkan tombolnya terkunci', async (t) => {
        const targets = ['_self', '_parent', '_top'];
        const window = pageWithTargetedForms(t, targets);
        const submits = targets.map((target) => {
            const form = targetedForm(window, target);
            const submit = form.querySelector('button[type="submit"]');
            form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
            assert.equal(submit.disabled, true, `prasyarat: tombol ${target} terkunci saat dikirim`);

            return submit;
        });

        await waitOutRestore();

        targets.forEach((target, index) => {
            assert.equal(
                submits[index].disabled,
                true,
                `${target} memindahkan halaman ini, jadi memulihkan tombol hanya membuka celah kirim ganda`,
            );
        });
    });

    it('target yang memindahkan halaman dikenali walau ditulis huruf besar', async (t) => {
        const targets = ['_SELF', '_Parent', '_TOP'];
        const window = pageWithTargetedForms(t, targets);
        const submits = targets.map((target) => {
            const form = targetedForm(window, target);
            const submit = form.querySelector('button[type="submit"]');
            form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
            assert.equal(submit.disabled, true, `prasyarat: tombol ${target} terkunci saat dikirim`);

            return submit;
        });

        await waitOutRestore();

        targets.forEach((target, index) => {
            assert.equal(
                submits[index].disabled,
                true,
                `kata kunci target di HTML tidak peka huruf besar-kecil, jadi ${target} tetap memindahkan halaman ini`,
            );
        });
    });

    it('form bertarget bernama dipulihkan karena halaman ini tidak berpindah', async (t) => {
        const window = pageWithTargetedForms(t, ['cetak']);
        const form = targetedForm(window, 'cetak');
        const submit = form.querySelector('button[type="submit"]');

        form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        assert.equal(submit.disabled, true, 'prasyarat: tombol terkunci saat dikirim');

        await waitFor(
            () => submit.disabled === false,
            'target bernama membuka konteks lain seperti _blank, jadi tombolnya harus pulih sendiri',
        );

        assert.equal(submit.textContent, 'Catat penerimaan', 'label semula harus dikembalikan');
    });
});

/**
 * Payload form request-driven. Bentuknya mengikuti kontrak App\Queries\SuratJalanFormQuery;
 * yang diuji di sini adalah apa yang klien lakukan terhadapnya, bukan cara server merakitnya.
 */
const transferFormData = {
    warehouse_mitra: { 10: null, 20: 7, 30: null },
    requests: {
        10: [],
        20: [
            {
                id: 91,
                mitra_id: 7,
                project_id: 55,
                tanggal: '2026-08-20',
                status: 'disetujui',
                label: '#91 — 20 Aug 2026 · 3 item, 2 belum lengkap',
                items: [
                    { material_id: 1, jenis: 'biasa', diminta: 10, terkirim: 4, sisa: 6 },
                    { material_id: 2, jenis: 'ber_sn', diminta: 3, terkirim: 0, sisa: 3 },
                    { material_id: 3, jenis: 'drum_kabel', diminta: 250, terkirim: 250, sisa: 0 },
                ],
            },
            {
                id: 92,
                mitra_id: 7,
                project_id: null,
                tanggal: '2026-08-19',
                status: 'disetujui',
                label: '#92 — 19 Aug 2026 · 1 item, 1 belum lengkap',
                items: [{ material_id: 1, jenis: 'biasa', diminta: 2, terkirim: 0, sisa: 2 }],
            },
        ],
        30: [],
    },
    projects: [
        { id: 55, id_project: 'PRJ-2608-0001', nama: 'Alpha', mitra_id: 7, label: 'PRJ-2608-0001 — Alpha' },
        { id: 56, id_project: 'PRJ-2608-0002', nama: 'Beta', mitra_id: 7, label: 'PRJ-2608-0002 — Beta' },
    ],
    identities: {},
};

const KOSONG_REQUEST = '— Tanpa Request Material —';
const KOSONG_PROJECT = '— Tanpa Project —';
const TERKUNCI_PROJECT = 'Gudang THC ke gudang THC — tanpa Project';

/** Render awal fixture meniru Blade: gudang tujuan pertama milik Mitra 7. */
const requestDrivenPage = (t) => openPage(t, `
    <form data-submit-loading data-transfer-form target="_blank">
        <select name="warehouse_asal_id" data-origin-select required>
            <option value="10">WH-THC</option>
            <option value="20">WH-MITRA</option>
        </select>
        <select name="warehouse_tujuan_id" data-destination-select required>
            <option value="20">WH-MITRA</option>
            <option value="30">WH-THC-2</option>
        </select>
        <select name="material_request_id" data-request-select data-empty-label="${KOSONG_REQUEST}">
            <option value="">${KOSONG_REQUEST}</option>
            <option value="91">${transferFormData.requests[20][0].label}</option>
            <option value="92">${transferFormData.requests[20][1].label}</option>
        </select>
        <select name="project_id" data-project-select data-empty-label="${KOSONG_PROJECT}" data-locked-label="${TERKUNCI_PROJECT}">
            <option value="">${KOSONG_PROJECT}</option>
            <option value="55">PRJ-2608-0001 — Alpha</option>
            <option value="56">PRJ-2608-0002 — Beta</option>
        </select>
        <div data-transfer-items>
            <div class="ui-list__item" data-transfer-row>
                <label>Material<select name="items[0][material_id]" required>
                    <option value="">Pilih Material</option>${materialOptions}
                </select></label>
                <label>Qty<input type="number" name="items[0][qty]" required></label>
                ${identityFields('items[0]')}
                <input type="hidden" name="items[0][asal]" value="manual" data-row-origin>
                <button type="button" data-remove-item hidden>Hapus item</button>
            </div>
        </div>
        <button type="button" data-add-item>Tambah item</button>
        <button type="submit">Terbitkan Surat Jalan</button>
    </form>
    <script type="application/json" data-transfer-form-data>${JSON.stringify(transferFormData)}</script>
`);

const field = (window, selector) => window.document.querySelector(selector);

const prefillRows = (window) => rows(window).filter((row) => row.dataset.rowAsal === 'request');

const optionValues = (select) => [...select.querySelectorAll('option')].map((option) => option.value);

const qtyOf = (row) => row.querySelector('input[type="number"]').value;

test('memilih Request Material memprefill baris dengan qty sisa', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '91');

    const prefilled = prefillRows(window);
    assert.equal(prefilled.length, 4, 'material bersisa 0 tidak boleh muncul, ber-SN dipecah per pcs');
    assert.equal(qtyOf(prefilled[0]), '6', 'qty prefill adalah sisa, bukan qty yang diminta');
    assert.deepEqual(prefilled.slice(1).map(qtyOf), ['1', '1', '1'], 'baris ber-SN dipecah satu baris per pcs');
});

test('baris prefill mengunci material lewat hidden input, bukan lewat select', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '92');

    const [row] = prefillRows(window);
    const select = row.querySelector('select');
    assert.equal(select.disabled, true, 'material baris prefill tidak boleh diubah operator');
    assert.equal(select.value, '1');
    const hidden = row.querySelector('input[type="hidden"][name$="[material_id]"]');
    assert.ok(hidden, 'select yang disabled tidak terkirim, jadi material wajib dibawa hidden input');
    assert.equal(hidden.value, '1');
    assert.equal(row.querySelector('[data-row-origin]').value, 'request');
});

test('baris prefill ber-SN tetap meminta Serial Number diisi operator', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '91');

    const berSn = prefillRows(window)[1];
    assert.equal(identity(berSn, 'serial_number').hidden, false);
    assert.equal(identity(berSn, 'serial_number').querySelector('input').value, '', 'prefill tidak pernah mengisi identitas');
});

test('request ber-Project mengunci Project dan tetap mengirimkannya', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '91');

    const project = field(window, '[data-project-select]');
    assert.equal(project.value, '55');
    assert.equal(project.disabled, true);
    assert.equal(field(window, '[data-project-lock]').value, '55');
});

test('request tanpa Project membiarkan Project diisi operator', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '92');

    const project = field(window, '[data-project-select]');
    assert.equal(project.disabled, false);
    assert.equal(field(window, '[data-project-lock]'), null);
});

test('baris pertama yang hampa tidak menahan submit setelah prefill', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '91');

    const first = rows(window)[0];
    assert.equal(first.hidden, true, 'baris hampa tidak perlu dilihat operator');
    assert.equal(
        first.querySelector('select').disabled,
        true,
        'field required yang disabled tidak ikut divalidasi browser maupun terkirim sebagai item kosong',
    );
    assert.equal(first.querySelector('input[type="number"]').disabled, true);
    // Identitas SN/Drum sengaja dibiarkan kosong-tapi-wajib: itu memang kerja operator. Yang tidak
    // boleh tersisa kosong adalah Material dan Qty, karena prefill mengaku sudah mengisinya.
    const wajibTerisi = [...window.document.querySelectorAll('[data-transfer-row] [required]')]
        .filter((f) => f.closest('[data-identity]') === null);
    assert.ok(
        wajibTerisi.every((f) => f.disabled || f.value !== ''),
        'Material dan Qty tidak boleh tersisa kosong dan aktif setelah prefill',
    );
});

test('baris pertama yang sudah diketik operator bertahan meski prefill mengisi form', (t) => {
    const window = requestDrivenPage(t);
    const first = rows(window)[0];
    choose(window, first.querySelector('select'), '1');
    first.querySelector('input[type="number"]').value = '3';

    choose(window, field(window, '[data-request-select]'), '91');

    assert.equal(first.hidden, false, 'baris ketikan operator tidak boleh disembunyikan');
    assert.equal(first.querySelector('select').disabled, false);
    assert.equal(first.querySelector('input[type="number"]').value, '3');
});

test('membatalkan pilihan request mengaktifkan kembali baris pertama', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');

    choose(window, field(window, '[data-request-select]'), '');

    const first = rows(window)[0];
    assert.equal(first.hidden, false);
    assert.equal(first.querySelector('select').disabled, false);
    assert.equal(first.querySelector('select').required, true, 'baris ketikan operator kembali wajib diisi');
});

test('baris tambahan setelah prefill tidak mewarisi keadaan baris pertama yang dinonaktifkan', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');

    window.document.querySelector('[data-add-item]').click();

    const tambahan = rows(window).at(-1);
    assert.equal(tambahan.hidden, false);
    assert.equal(tambahan.querySelector('select').disabled, false);
    assert.equal(tambahan.dataset.rowAsal, 'manual');
});

test('ganti gudang tujuan membuang baris prefill tapi menyisakan baris ketikan operator', (t) => {
    const window = requestDrivenPage(t);
    window.document.querySelector('[data-add-item]').click();
    choose(window, field(window, '[data-request-select]'), '91');
    prefillRows(window)[0].querySelector('input[type="number"]').value = '99';

    choose(window, field(window, '[data-destination-select]'), '30');

    assert.equal(prefillRows(window).length, 0, 'baris prefill yang sudah diedit pun ikut dibuang');
    assert.equal(rows(window).length, 2, 'baris template dan baris ketikan operator harus bertahan');
    assert.equal(field(window, '[data-request-select]').value, '');
});

test('gudang tujuan THC dengan asal THC mematikan Project dengan opsi penjelas', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-destination-select]'), '30');

    const project = field(window, '[data-project-select]');
    assert.equal(project.disabled, true);
    assert.deepEqual([...project.querySelectorAll('option')].map((option) => option.textContent), [TERKUNCI_PROJECT]);
    assert.deepEqual(optionValues(field(window, '[data-request-select]')), [''], 'gudang THC tidak punya Request Material');
});

test('memilih Project mempersempit daftar request tapi request tanpa Project tetap muncul', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-project-select]'), '56');

    assert.deepEqual(optionValues(field(window, '[data-request-select]')), ['', '92']);
});

test('jalur tanpa Request Material tidak menambah baris apa pun', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');

    choose(window, field(window, '[data-request-select]'), '');

    assert.equal(rows(window).length, 1);
    assert.equal(field(window, '[data-project-select]').disabled, false);
});
