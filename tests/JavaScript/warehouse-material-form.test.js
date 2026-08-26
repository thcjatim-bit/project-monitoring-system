import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test, { describe, it } from 'node:test';
import { Window } from 'happy-dom';

import { RESTORE_SUBMIT_AFTER_MS } from '../../resources/js/submit-loading.js';
import { initializeWarehouseMaterialForm } from '../../resources/js/warehouse-material-form.js';

const bladePath = fileURLToPath(
    new URL('../../resources/views/warehouse/index.blade.php', import.meta.url),
);

const tolerancePath = fileURLToPath(
    new URL('../../app/Support/QtyTolerance.php', import.meta.url),
);

const blade = () => readFileSync(bladePath, 'utf8');

/**
 * Ambang klasifikasi dibaca dari konstanta aplikasinya, bukan diketik ulang di sini: fixture
 * payload di bawah harus membawa angka yang sama persis dengan yang dipakai server.
 */
const qtyTolerance = () => {
    const match = readFileSync(tolerancePath, 'utf8').match(/const VALUE = ([\d.]+);/);
    assert.ok(match, 'konstanta QtyTolerance::VALUE tidak ditemukan');

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

/** Catatan ikut di setiap baris fixture karena Blade merendernya di setiap baris. */
const catatanField = (prefix) => `
    <label>Catatan<input name="${prefix}[catatan]" maxlength="1000" data-catatan-input></label>
`;

/**
 * Satu perakit baris untuk seluruh fixture, dijaga tetap sejajar dengan Blade oleh test
 * "fixture baris item membawa setiap field yang dirender Blade" di bawah. Markup baris yang
 * disalin per fixture adalah cara sebelumnya, dan salinan yang tertinggallah yang dulu kehilangan
 * hidden input sumber baris begitu Blade mulai merendernya.
 */
const barisSuratJalan = ({
    index = 0,
    sumber = 'operator',
    pilihan = materialOptions,
    dikunci = null,
    qty = '',
    bisaDihapus = false,
} = {}) => `
    <div class="ui-list__item" data-transfer-row data-row-sumber="${sumber}">
        <label>Material<select name="items[${index}][material_id]" data-material-select required${dikunci === null ? '' : ' disabled'}>
            <option value="">Pilih Material</option>${pilihan}
        </select>${dikunci === null ? '' : `<input type="hidden" name="items[${index}][material_id]" value="${dikunci}">`}</label>
        <label>Qty<input type="number" name="items[${index}][qty]" required value="${qty}"></label>
        ${identityFields(`items[${index}]`)}
        ${catatanField(`items[${index}]`)}
        <input type="hidden" name="items[${index}][sumber]" value="${sumber}" data-row-sumber-input>
        <button type="button" data-remove-item${bisaDihapus ? '' : ' hidden'}>Hapus item</button>
    </div>
`;

/**
 * Kolam Window happy-dom, dipinjam per test dan dikembalikan lewat `t.after`.
 *
 * `new Window()` memanggil `VM.createContext()` di dalam happy-dom, jadi setiap Window membuat
 * satu konteks V8 baru. Konteks itu ditahan Node di `CppgcWrapperList` milik Environment-nya dan
 * tidak pernah dilepas — `window.close()` tidak menyentuhnya. Akibatnya seluruh DOM setiap
 * fixture ikut tertahan sampai proses mati: berkas ini membangun 82 Window dan berakhir memakai
 * 2414 MB heap, di atas plafon heap `pms-dev` yang 2096 MB. Lihat #176 untuk angka pengukurannya.
 *
 * Karena itu Window dipakai ulang, bukan dibuat ulang. Setiap peminjaman mendapat Window yang
 * body-nya kosong; test yang berjalan berbarengan tetap memegang Window masing-masing karena
 * yang dikembalikan ke kolam hanya Window yang testnya sudah selesai.
 */
const windowPool = [];

/** Dihitung supaya penjaga di akhir berkas bisa membuktikan kolamnya memang dipakai ulang. */
const windowStats = { dibangun: 0, dipinjam: 0 };

const leaseWindow = (t, url) => {
    windowStats.dipinjam += 1;

    if (windowPool.length === 0) {
        windowStats.dibangun += 1;
        windowPool.push(new Window({ url }));
    }

    const window = windowPool.pop();
    window.happyDOM.setURL(url);

    t.after(async () => {
        try {
            // Timer yang masih menggantung milik test ini, supaya tidak menyentuh penyewa berikutnya.
            await window.happyDOM.abort();
        } finally {
            // Dikembalikan apa pun yang terjadi: Window yang bocor dari kolam menaikkan jumlah
            // konteks V8 diam-diam, lalu muncul jauh kemudian sebagai penjaga yang gagal.
            window.document.body.innerHTML = '';
            windowPool.push(window);
        }
    });

    return window;
};

/**
 * Halaman dibuka lewat seam yang sama dengan produksi: markup dipasang, lalu modulnya dipanggil.
 * Penanda `data-warehouse-page` ikut dipasang karena Blade memasangnya juga — tanpa penanda itu
 * modulnya memang tidak bekerja, persis seperti di halaman lain.
 */
const openPage = (t, body) => {
    const window = leaseWindow(t, 'https://example.test/warehouse');
    window.document.body.innerHTML = `<main class="ui-page" data-warehouse-page>${body}</main>`;
    initializeWarehouseMaterialForm(window.document);

    return window;
};

const warehousePage = (t) => openPage(t, `
    <form data-submit-loading action="/warehouse/stock/receive">
        <label>Material<select name="material_id" data-material-select required>
            <option value="">Pilih Material</option>${materialOptions}
        </select></label>
        <input type="number" name="qty" min="1" step="1">
        ${identityFields('receive')}
        <button type="submit">Catat penerimaan</button>
    </form>
    <form data-submit-loading action="/warehouse/stock/issue">
        <label>Material<select name="material_id" data-material-select required>
            <option value="">Pilih Material</option>${materialOptions}
        </select></label>
        <input type="number" name="qty" min="1" step="1">
        ${identityFields('issue')}
        <button type="submit">Catat pengeluaran</button>
    </form>
    <form data-submit-loading action="/warehouse/stock/drum-split">
        <label>Drum<select name="drum_id" required><option value="DRM-1">DRM-1</option></select></label>
        <input type="number" name="qty" data-quantity-kind="drum_kabel" min="1" step="1">
        <button type="submit">Catat split drum</button>
    </form>
    <form data-submit-loading data-transfer-form target="_blank" rel="noopener noreferrer">
        <div data-transfer-items>${barisSuratJalan()}</div>
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

const drumSplitForm = (window) => window.document.querySelector('form[action="/warehouse/stock/drum-split"]');

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

/**
 * Penjaga penyimpangan fixture. Fixture merakit markup barisnya sendiri, jadi ia bisa tertinggal
 * saat Blade menambah field — persis yang terjadi pada hidden input sumber baris. Yang
 * dibandingkan bukan markupnya kata per kata (Blade punya direktif dan indeks dari old()),
 * melainkan daftar field dan atribut data yang jadi pegangan modulnya.
 */
const bladeBarisMarkup = () => {
    const match = blade().match(/<div class="ui-list__item" data-transfer-row[\s\S]*?data-remove-item[\s\S]*?<\/button><\/div>/);
    assert.ok(match, 'markup baris item Surat Jalan tidak ditemukan di Blade');

    return match[0];
};

const namaField = (markup) => new Set(
    [...markup.matchAll(/name="items\[[^\]]*\]\[(\w+)\]"/g)].map((match) => match[1]),
);

const atributData = (markup) => new Set(
    [...markup.matchAll(/\sdata-([a-z-]+)/g)]
        .map((match) => match[1])
        .filter((attribute) => ['material-select', 'row-sumber-input', 'catatan-input', 'transfer-row', 'row-sumber', 'remove-item'].includes(attribute)),
);

test('fixture baris item membawa setiap field dan atribut yang dirender Blade', () => {
    const bladeRow = bladeBarisMarkup();
    const fixtureRow = barisSuratJalan();

    for (const nama of namaField(bladeRow)) {
        assert.ok(
            namaField(fixtureRow).has(nama),
            `Blade merender items[N][${nama}] tapi fixture tidak — fixture menyimpang dari halaman aslinya`,
        );
    }

    for (const atribut of atributData(bladeRow)) {
        assert.ok(
            atributData(fixtureRow).has(atribut),
            `Blade merender data-${atribut} tapi fixture tidak — modulnya berpegang pada atribut yang hilang di fixture`,
        );
    }
});

/**
 * Markup form untuk seluruh fixture request-driven. Sebelumnya form ini disalin lima kali, dan
 * salinan yang tertinggal itulah cara fixture menyimpang dari halaman aslinya — persis yang
 * terjadi pada baris item. Dijaga tetap sejajar dengan Blade oleh test di bawah.
 */
const opsi = (daftar) => daftar
    .map(({ value, label, selected = false }) => `<option value="${value}"${selected ? ' selected' : ''}>${label}</option>`)
    .join('');

const formSuratJalan = ({
    gudangAsal = [{ value: 10, label: 'WH-THC' }, { value: 20, label: 'WH-MITRA' }],
    gudangTujuan = [{ value: 20, label: 'WH-MITRA' }, { value: 30, label: 'WH-THC-2' }, { value: 40, label: 'WH-MITRA-2' }],
    requests = [],
    projects = [],
    projectMati = false,
    kunciProject = null,
    baris = barisSuratJalan(),
    payload = transferFormData,
} = {}) => `
    <form data-submit-loading data-transfer-form target="_blank" rel="noopener noreferrer">
        <select name="warehouse_asal_id" data-asal-select required>${opsi(gudangAsal)}</select>
        <select name="warehouse_tujuan_id" data-tujuan-select required>${opsi(gudangTujuan)}</select>
        <select name="material_request_id" data-request-select data-empty-label="${KOSONG_REQUEST}">${opsi([{ value: '', label: KOSONG_REQUEST }, ...requests])}</select>
        <select name="project_id" data-project-select data-empty-label="${KOSONG_PROJECT}" data-locked-label="${TERKUNCI_PROJECT}"${projectMati ? ' disabled' : ''}>${opsi([{ value: '', label: projectMati ? TERKUNCI_PROJECT : KOSONG_PROJECT }, ...projects])}</select>
        ${kunciProject === null ? '' : `<input type="hidden" name="project_id" value="${kunciProject}" data-project-lock>`}
        <div data-transfer-items>${baris}</div>
        <button type="button" data-add-item>Tambah item</button>
        <button type="submit">Terbitkan Surat Jalan</button>
    </form>
    <script type="application/json" data-transfer-form-data>${JSON.stringify(payload)}</script>
`;

/** Markup form Terbitkan Surat Jalan yang dirender Blade, sampai sebelum daftar barisnya. */
const bladeFormMarkup = () => {
    const match = blade().match(/<form[^>]*data-transfer-form[\s\S]*?<div data-transfer-items>/);
    assert.ok(match, 'markup form Terbitkan Surat Jalan tidak ditemukan di Blade');

    return match[0];
};

test('fixture form Surat Jalan membawa setiap atribut yang dirender Blade', () => {
    // Kunci Project hanya dirender Blade saat request membawanya, jadi fixture pembandingnya
    // dibangun dengan kunci itu terpasang.
    const fixtureForm = formSuratJalan({ kunciProject: 55 });

    for (const atribut of atributData(bladeFormMarkup())) {
        assert.ok(
            atributData(fixtureForm).has(atribut),
            `Blade merender data-${atribut} tapi fixture form tidak — fixture menyimpang dari halaman aslinya`,
        );
    }
});

/**
 * Penanda halaman adalah satu-satunya hal yang membuat modulnya bekerja di produksi, dan fixture
 * memasangnya sendiri. Tanpa test ini, menghapus atribut itu dari Blade akan mematikan seluruh
 * form sementara suite JS tetap hijau — saluran penyimpangan baru, tepat di tempat yang sama.
 */
test('Blade menandai halaman Warehouse supaya modulnya ikut bekerja di produksi', () => {
    assert.match(
        blade(),
        /<main[^>]*\sdata-warehouse-page[\s>]/,
        'tanpa penanda ini modulnya diam dan form Surat Jalan mati di produksi',
    );
});

test('tanpa penanda halaman Warehouse modulnya tidak menyentuh apa pun', async (t) => {
    const window = leaseWindow(t, 'https://example.test/proyek');
    window.document.body.innerHTML = `<main class="ui-page">${formSuratJalan()}</main>`;

    initializeWarehouseMaterialForm(window.document);
    window.document.querySelector('[data-add-item]').click();

    assert.equal(
        window.document.querySelectorAll('[data-transfer-row]').length,
        1,
        'view lain memakai atribut yang sama; modulnya tidak boleh ikut hidup di sana',
    );
});


test('markup baris item Surat Jalan menyediakan opsi placeholder Pilih Material', () => {
    // Indeks baris dirender Blade dari old(), jadi markup dicocokkan tanpa memaku angkanya.
    const select = blade().match(/<select name="items\[[^"]*\]\[material_id\]"[^>]*>([\s\S]*?)<\/select>/);

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

test('form stok dan baris Surat Jalan memakai step satu untuk material berqty utuh', (t) => {
    const window = warehousePage(t);

    for (const action of ['receive', 'issue']) {
        const form = stockForm(window, action);
        assert.equal(form.querySelector('input[name="qty"]').getAttribute('step'), '1');
        assert.equal(form.querySelector('input[name="qty"]').getAttribute('min'), '1');
        choose(window, form.querySelector('[data-material-select]'), '3');

        assert.equal(form.querySelector('input[name="qty"]').getAttribute('step'), '1');
        assert.equal(form.querySelector('input[name="qty"]').getAttribute('min'), '1');
    }

    const row = rows(window)[0];
    assert.equal(row.querySelector('input[name="items[0][qty]"]').getAttribute('step'), '1');
    assert.equal(row.querySelector('input[name="items[0][qty]"]').getAttribute('min'), '1');
    choose(window, row.querySelector('select'), '3');

    assert.equal(row.querySelector('input[name="items[0][qty]"]').getAttribute('step'), '1');
    assert.equal(row.querySelector('input[name="items[0][qty]"]').getAttribute('min'), '1');
});

test('form Split Drum memakai step satu meski identitasnya memakai Drum ID', (t) => {
    const window = warehousePage(t);
    const quantity = drumSplitForm(window).querySelector('input[name="qty"]');

    assert.equal(quantity.getAttribute('step'), '1');
    assert.equal(quantity.getAttribute('min'), '1');
});

test('Blade form Split Drum mengiklankan qty meter utuh', (t) => {
    const source = blade();
    const splitFormStart = source.indexOf("action=\"{{ route('warehouse.stock.drum-split') }}\"");
    const splitForm = source.slice(splitFormStart, source.indexOf('</form>', splitFormStart));

    assert.notEqual(splitFormStart, -1, 'form Split Drum tidak ditemukan');
    assert.match(splitForm, /data-quantity-kind="drum_kabel"/);
    assert.match(splitForm, /name="qty"[^>]*min="1"[^>]*step="1"/);
});

test('Blade Warehouse tidak lagi merender atribut qty pecahan', () => {
    assert.doesNotMatch(blade(), /0\.001/);
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
        const deadline = Date.now() + RESTORE_SUBMIT_AFTER_MS * 3;

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
        setTimeout(resolve, RESTORE_SUBMIT_AFTER_MS + 200);
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
    warehouse_mitra: { 10: null, 20: 7, 30: null, 40: 7 },
    // Gudang awal dan Mitra efektifnya dilayani server; skrip membacanya, tidak menghitungnya ulang.
    initial_asal_id: 10,
    initial_tujuan_id: 20,
    initial_mitra_id: 7,
    qty_tolerance: qtyTolerance(),
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
            {
                id: 93,
                mitra_id: 7,
                project_id: 55,
                tanggal: '2026-08-18',
                status: 'disetujui',
                label: '#93 — 18 Aug 2026 · 1 item, 1 belum lengkap',
                items: [{ material_id: 2, jenis: 'ber_sn', diminta: 2.5, terkirim: 0, sisa: 2.5 }],
            },
            {
                id: 94,
                mitra_id: 7,
                project_id: 55,
                tanggal: '2026-08-17',
                status: 'disetujui',
                label: '#94 — 17 Aug 2026 · 1 item, 1 belum lengkap',
                items: [{ material_id: 2, jenis: 'ber_sn', diminta: 0.5, terkirim: 0, sisa: 0.5 }],
            },
            {
                id: 95,
                mitra_id: 7,
                project_id: 55,
                tanggal: '2026-08-16',
                status: 'disetujui',
                label: '#95 — 16 Aug 2026 · 1 item, 1 belum lengkap',
                items: [{ material_id: 2, jenis: 'ber_sn', diminta: 1250.5, terkirim: 0, sisa: 1250.5 }],
            },
        ],
        30: [],
        40: [
            {
                id: 96,
                mitra_id: 7,
                project_id: 56,
                tanggal: '2026-08-15',
                status: 'disetujui',
                label: '#96 — 15 Aug 2026 · 1 item, 1 belum lengkap',
                items: [{ material_id: 1, jenis: 'biasa', diminta: 4, terkirim: 0, sisa: 4 }],
            },
            {
                id: 97,
                mitra_id: 7,
                project_id: null,
                tanggal: '2026-08-14',
                status: 'disetujui',
                label: '#97 — 14 Aug 2026 · 1 item, 1 belum lengkap',
                items: [{ material_id: 1, jenis: 'biasa', diminta: 1, terkirim: 0, sisa: 1 }],
            },
        ],
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
const requestDrivenPage = (t, payload = transferFormData) => openPage(t, formSuratJalan({
    requests: [
        { value: 91, label: transferFormData.requests[20][0].label },
        { value: 92, label: transferFormData.requests[20][1].label },
        { value: 93, label: transferFormData.requests[20][2].label },
        { value: 94, label: transferFormData.requests[20][3].label },
        { value: 95, label: transferFormData.requests[20][4].label },
    ],
    projects: [
        { value: 55, label: 'PRJ-2608-0001 — Alpha' },
        { value: 56, label: 'PRJ-2608-0002 — Beta' },
    ],
    payload,
}));

const field = (window, selector) => window.document.querySelector(selector);

const prefillRows = (window) => rows(window).filter((row) => row.dataset.rowSumber === 'prefill');

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
    assert.equal(row.querySelector('[data-row-sumber-input]').value, 'prefill');
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

/** Baris yang aktif dan wajib diisi adalah yang benar-benar ikut terkirim ke server. */
const wajibDiisiAktif = (window) => [...window.document.querySelectorAll('[data-transfer-row] [required]')]
    .filter((field) => field.closest('[data-identity]') === null)
    .filter((field) => ! field.disabled);

const buangSemuaBarisPrefill = (window) => prefillRows(window)
    .forEach((row) => row.querySelector('[data-remove-item]').click());

test('membuang semua baris prefill memulihkan baris pertama supaya form bisa disubmit', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');

    buangSemuaBarisPrefill(window);

    assert.equal(rows(window).length, 1, 'baris pertama tidak boleh ikut hilang bersama baris prefill');
    const first = rows(window)[0];
    assert.equal(first.hidden, false, 'operator harus punya baris yang terlihat untuk diketik');
    assert.equal(first.querySelector('select').disabled, false);
    assert.equal(first.querySelector('input[type="number"]').disabled, false);
    assert.equal(first.querySelector('select').value, '', 'baris pertama kembali kosong, siap diketik');
    assert.equal(first.querySelector('input[type="number"]').value, '');

    choose(window, first.querySelector('select'), '1');
    first.querySelector('input[type="number"]').value = '4';

    assert.deepEqual(
        wajibDiisiAktif(window).map((f) => f.value),
        ['1', '4'],
        'Material dan Qty baris pertama harus aktif dan terisi supaya form terkirim',
    );
});

test('membuang baris prefill terakhir menutup peringatan pecahan yang menceritakannya', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '93');
    assert.ok(field(window, '[data-fraction-notice]'), 'prasyarat: request ber-sisa pecahan memberi peringatan');

    buangSemuaBarisPrefill(window);

    assert.equal(field(window, '[data-fraction-notice]'), null, 'peringatan tidak boleh menceritakan baris yang sudah tidak ada');
});

test('membuang baris ketikan operator tidak menutup peringatan pecahan yang berdiri sendiri', (t) => {
    const window = requestDrivenPage(t);
    // Sisa 0,5 pcs ber-SN tidak melahirkan baris prefill sama sekali; peringatannya tetap berlaku.
    choose(window, field(window, '[data-request-select]'), '94');
    window.document.querySelector('[data-add-item]').click();

    rows(window).at(-1).querySelector('[data-remove-item]').click();

    assert.ok(field(window, '[data-fraction-notice]'), 'peringatan pecahan bisa hidup tanpa baris prefill');
    assert.equal(rows(window).length, 1);
});

test('membuang semua baris prefill tidak menyentuh baris pertama yang sudah diketik operator', (t) => {
    const window = requestDrivenPage(t);
    const first = rows(window)[0];
    choose(window, first.querySelector('select'), '3');
    first.querySelector('input[type="number"]').value = '7';
    first.querySelector('[data-catatan-input]').value = 'kirim susulan';
    choose(window, field(window, '[data-request-select]'), '91');

    buangSemuaBarisPrefill(window);

    assert.equal(rows(window).length, 1);
    assert.equal(first.hidden, false);
    assert.equal(first.querySelector('select').disabled, false);
    assert.equal(first.querySelector('select').value, '3', 'material ketikan operator tidak boleh dikosongkan');
    assert.equal(first.querySelector('input[type="number"]').value, '7');
    assert.equal(first.querySelector('[data-catatan-input]').value, 'kirim susulan');
    assert.equal(identity(first, 'drum_id').hidden, false, 'kotak identitas ketikan operator tetap terbuka');
});

test('baris tambahan setelah prefill tidak mewarisi keadaan baris pertama yang dinonaktifkan', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');

    window.document.querySelector('[data-add-item]').click();

    const tambahan = rows(window).at(-1);
    assert.equal(tambahan.hidden, false);
    assert.equal(tambahan.querySelector('select').disabled, false);
    assert.equal(tambahan.dataset.rowSumber, 'operator');
});

test('ganti gudang tujuan membuang baris prefill tapi menyisakan baris ketikan operator', (t) => {
    const window = requestDrivenPage(t);
    window.document.querySelector('[data-add-item]').click();
    choose(window, field(window, '[data-request-select]'), '91');
    prefillRows(window)[0].querySelector('input[type="number"]').value = '99';

    choose(window, field(window, '[data-tujuan-select]'), '30');

    assert.equal(prefillRows(window).length, 0, 'baris prefill yang sudah diedit pun ikut dibuang');
    assert.equal(rows(window).length, 2, 'baris template dan baris ketikan operator harus bertahan');
    assert.equal(field(window, '[data-request-select]').value, '');
});

test('gudang tujuan THC dengan asal THC mematikan Project dengan opsi penjelas', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-tujuan-select]'), '30');

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

/**
 * Project yang terkunci request adalah pinjaman, bukan pilihan operator. Membatalkan request
 * harus mengembalikan Project ke keadaan sebelum dikunci — bukan sekadar melepas kuncinya dan
 * meninggalkan nilai request yang sudah pergi tampak seperti isian operator sendiri.
 */
test('membatalkan request mengembalikan Project ke keadaan sebelum request dipilih', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');
    assert.equal(field(window, '[data-project-select]').value, '55', 'prasyarat: request mengunci Project');

    choose(window, field(window, '[data-request-select]'), '');

    const project = field(window, '[data-project-select]');
    assert.equal(project.value, '', 'Project harus pulih ke keadaan semula, bukan menyisakan nilai request');
    assert.equal(project.disabled, false);
    assert.equal(field(window, '[data-project-lock]'), null);
});

/** Pulih berarti kembali ke pilihan operator, jadi isian yang ia buat sendiri tidak boleh ikut terhapus. */
test('membatalkan request tidak menghapus Project yang diisi operator sendiri', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-project-select]'), '55');
    choose(window, field(window, '[data-request-select]'), '91');
    assert.equal(field(window, '[data-project-lock]').value, '55', 'prasyarat: request mengunci Project');

    choose(window, field(window, '[data-request-select]'), '');

    assert.equal(field(window, '[data-project-select]').value, '55', 'pilihan Project operator harus utuh');
    assert.equal(field(window, '[data-project-select]').disabled, false);
});

/** Berganti ke request tanpa Project pun melepas pinjaman itu, bukan mewariskannya diam-diam. */
test('berpindah ke request tanpa Project melepas Project pinjaman request sebelumnya', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');

    choose(window, field(window, '[data-request-select]'), '92');

    const project = field(window, '[data-project-select]');
    assert.equal(project.value, '', 'request tanpa Project tidak boleh mewarisi Project request sebelumnya');
    assert.equal(field(window, '[data-project-lock]'), null);
});

/**
 * Nilai pra-kunci terikat pada daftar Project milik satu Mitra. Ganti Mitra menulis ulang daftar
 * itu, jadi nilai simpanan ikut gugur — memulihkannya berarti menghidupkan Project Mitra lama.
 */
test('ganti Mitra membuang nilai Project pra-kunci milik Mitra lama', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-project-select]'), '55');
    choose(window, field(window, '[data-request-select]'), '91');

    choose(window, field(window, '[data-tujuan-select]'), '30');
    choose(window, field(window, '[data-tujuan-select]'), '20');

    const project = field(window, '[data-project-select]');
    assert.equal(project.value, '', 'Project Mitra lama tidak boleh hidup kembali setelah Mitra berganti');
    assert.equal(project.disabled, false);
    assert.equal(field(window, '[data-project-lock]'), null);
});

/**
 * Penyaring daftar request membaca Project yang sedang terpilih. Selama Project masih pinjaman
 * request lama, daftar yang tersusun lebih sempit daripada yang berhak dilihat operator — jadi
 * kunci harus lepas sebelum daftar disusun ulang, bukan sesudahnya.
 */
test('ganti gudang tujuan menyusun daftar request tanpa disempitkan Project pinjaman', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');
    assert.equal(field(window, '[data-project-select]').value, '55', 'prasyarat: request mengunci Project');

    choose(window, field(window, '[data-tujuan-select]'), '40');

    assert.equal(field(window, '[data-project-select]').value, '', 'Project pinjaman harus lepas saat request di-reset');
    assert.deepEqual(
        optionValues(field(window, '[data-request-select]')),
        ['', '96', '97'],
        'daftar request gudang baru tidak boleh disempitkan Project request yang sudah pergi',
    );
});

/**
 * Setelah POST ditolak, Blade merender ulang kunci Project dari `old()`. Yang terkirim hanyalah
 * nilai terkunci — pilihan operator sebelum request dipilih tidak pernah ikut ke server, jadi
 * tidak ada yang bisa dipulihkan. Membatalkan request mengosongkan Project: menyisakan nilai
 * request yang sudah pergi justru mengulang bug yang diperbaiki di sini.
 */
const halamanDenganKunciServer = (t) => openPage(t, formSuratJalan({
    gudangAsal: [{ value: 10, label: 'WH-THC' }],
    gudangTujuan: [{ value: 20, label: 'WH-MITRA', selected: true }],
    requests: [{ value: 91, label: transferFormData.requests[20][0].label, selected: true }],
    projects: [
        { value: 55, label: 'PRJ-2608-0001 — Alpha', selected: true },
        { value: 56, label: 'PRJ-2608-0002 — Beta' },
    ],
    projectMati: true,
    kunciProject: 55,
}));

const requestProjectLain = {
    id: 98,
    mitra_id: 7,
    project_id: 56,
    tanggal: '2026-08-13',
    status: 'disetujui',
    label: '#98 — 13 Aug 2026 · 1 item, 1 belum lengkap',
    items: [{ material_id: 1, jenis: 'biasa', diminta: 7, terkirim: 0, sisa: 7 }],
};

const halamanDenganProjectDipulihkan = (t) => openPage(t, formSuratJalan({
    requests: [...transferFormData.requests[20], requestProjectLain].map((request) => ({
        value: request.id,
        label: request.label,
    })),
    projects: [
        { value: 55, label: 'PRJ-2608-0001 — Alpha', selected: true },
        { value: 56, label: 'PRJ-2608-0002 — Beta' },
    ],
    payload: {
        ...transferFormData,
        requests: {
            ...transferFormData.requests,
            20: [...transferFormData.requests[20], requestProjectLain],
        },
    },
}));

test('Project yang dipulihkan menyaring Request sejak inisialisasi halaman', (t) => {
    const window = halamanDenganProjectDipulihkan(t);

    assert.equal(field(window, '[data-project-select]').value, '55');
    assert.deepEqual(
        optionValues(field(window, '[data-request-select]')),
        ['', '91', '92', '93', '94', '95'],
        'Request dari Project lain tidak boleh muncul setelah validasi mengembalikan Project lama',
    );
});

test('membatalkan request yang dikunci server melepas Project, bukan menyisakannya', (t) => {
    const window = halamanDenganKunciServer(t);
    assert.equal(field(window, '[data-project-lock]').value, '55', 'prasyarat: kunci dirender server dari old()');

    choose(window, field(window, '[data-request-select]'), '');

    const project = field(window, '[data-project-select]');
    assert.equal(project.value, '', 'Project terkunci server tidak boleh tertinggal sebagai pilihan operator');
    assert.equal(project.disabled, false);
    assert.equal(field(window, '[data-project-lock]'), null);
});

test('jalur tanpa Request Material tidak menambah baris apa pun', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '91');

    choose(window, field(window, '[data-request-select]'), '');

    assert.equal(rows(window).length, 1);
    assert.equal(field(window, '[data-project-select]').disabled, false);
});

/**
 * Penandaan baris menyimpang (ADR-0024 "Peringatan, bukan penghalang"). Klasifikasinya meniru
 * `SuratJalanService::classifyRequestDeviations()`: diukur per material atas seluruh baris,
 * bukan per baris, supaya yang dilihat operator sama dengan yang dinilai server saat terbit.
 *
 * Yang diuji di bawah ini adalah saluran penandaannya — kelas, catatan, panduan. Kasus
 * klasifikasi baru tidak ditulis di sini: tempatnya tests/fixtures/klasifikasi-penyimpangan.json
 * yang dibaca kedua sisi (ADR-0026), lihat "kontrak klasifikasi penyimpangan" di akhir berkas.
 */
const penyimpangan = (row) => row.dataset.deviation ?? null;

const catatanPenyimpangan = (row) => row.querySelector('[data-deviation-note]')?.textContent ?? null;

const barisManual = (window) => {
    window.document.querySelector('[data-add-item]').click();

    return rows(window).at(-1);
};

/** Operator mengetik: pilih material lalu isi qty, masing-masing memicu event aslinya. */
const ketik = (window, row, materialId, qty) => {
    choose(window, row.querySelector('select'), materialId);
    isiQty(window, row, qty);
};

const isiQty = (window, row, qty) => {
    const input = row.querySelector('input[type="number"]');
    input.value = qty;
    input.dispatchEvent(new window.Event('input', { bubbles: true }));
};

test('baris bermaterial di luar Request Material ditandai menyimpang', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');

    const manual = barisManual(window);
    ketik(window, manual, '3', '5');

    assert.equal(penyimpangan(manual), 'material_asing', 'request #92 hanya meminta material 1');
    assert.equal(manual.classList.contains('ui-list__item--deviating'), true);
    assert.match(catatanPenyimpangan(manual), /Request Material/);
});

test('qty melebihi sisa request ditandai menyimpang beserta sisanya', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');

    const [prefilled] = prefillRows(window);
    isiQty(window, prefilled, '5');

    assert.equal(penyimpangan(prefilled), 'qty_melebihi');
    assert.match(catatanPenyimpangan(prefilled), /2/, 'operator harus tahu sisanya berapa, bukan sekadar bahwa ia salah');
});

test('qty di bawah sisa bukan penyimpangan — itu kirim bertahap', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');

    const [prefilled] = prefillRows(window);
    isiQty(window, prefilled, '1');

    assert.equal(penyimpangan(prefilled), null);
    assert.equal(prefilled.classList.contains('ui-list__item--deviating'), false);
    assert.equal(catatanPenyimpangan(prefilled), null);
});

test('penandaan qty diukur per material atas seluruh baris, bukan per baris', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');
    const [prefilled] = prefillRows(window);

    const tambahan = barisManual(window);
    ketik(window, tambahan, '1', '1');

    assert.equal(penyimpangan(tambahan), 'qty_melebihi', 'sisa 2 sudah habis dipakai baris prefill');
    assert.equal(penyimpangan(prefilled), 'qty_melebihi', 'server menilai per material, jadi kedua baris sama-sama menyimpang');
});

test('tanpa Request Material tidak ada baris yang ditandai menyimpang', (t) => {
    const window = requestDrivenPage(t);

    ketik(window, rows(window)[0], '3', '999');

    assert.equal(penyimpangan(rows(window)[0]), null, 'tanpa request tidak ada yang bisa disimpangi');
});

test('penandaan hilang begitu qty dikoreksi kembali ke dalam sisa', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');
    const [prefilled] = prefillRows(window);
    isiQty(window, prefilled, '5');
    assert.equal(penyimpangan(prefilled), 'qty_melebihi', 'prasyarat: baris ditandai');

    isiQty(window, prefilled, '2');

    assert.equal(penyimpangan(prefilled), null);
    assert.equal(catatanPenyimpangan(prefilled), null, 'catatan yang tertinggal akan berbohong');
});

test('baris menyimpang tetap boleh dikirim — penandaan adalah peringatan, bukan penghalang', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');

    const manual = barisManual(window);
    ketik(window, manual, '3', '5');

    assert.equal(penyimpangan(manual), 'material_asing', 'prasyarat: baris ditandai');
    assert.equal(manual.hidden, false);
    assert.equal(manual.querySelector('select').disabled, false);
    assert.equal(manual.querySelector('input[type="number"]').disabled, false);
    assert.equal(
        field(window, '[data-transfer-form] button[type="submit"]').disabled,
        false,
        'penyimpangan adalah alur kerja yang sah; form tidak boleh dikunci',
    );
});

test('request ber-SN dengan sisa pecahan tidak melahirkan pcs yang tidak ada', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '93');

    const prefilled = prefillRows(window);
    assert.equal(prefilled.length, 2, 'sisa 2,5 pcs ber-SN: membulatkan ke atas berarti mengarang satu pcs');
    assert.deepEqual(prefilled.map(qtyOf), ['1', '1']);
    assert.deepEqual(prefilled.map(penyimpangan), [null, null], 'prefill tidak pernah menyimpang dari requestnya sendiri');
});

/** Peringatan sisa pecahan punya salurannya sendiri, terpisah dari penandaan Menyimpang (ADR-0025). */
const peringatanPecahan = (window) => window.document.querySelector('[data-fraction-notice]')?.textContent ?? null;

test('sisa pecahan ber-SN yang tidak ter-prefill diberitahukan, bukan dibuang diam-diam', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '93');

    const peringatan = peringatanPecahan(window);
    assert.ok(peringatan, 'sisa 0,5 yang hilang dari prefill harus terbaca operator');
    assert.match(peringatan, /#93/, 'operator harus tahu request mana yang perlu dibetulkan');
    assert.match(peringatan, /2,5/, 'angka yang tercatat mitra disebut apa adanya');
    assert.match(peringatan, /0,5/, 'sisa yang tidak dapat dikirim disebut angkanya');
    assert.match(peringatan, /ONT/, 'materialnya disebut, bukan sekadar "ada yang pecahan"');
    assert.match(peringatan, /Request Material/, 'operator harus tahu siapa yang membetulkan');
});

test('angka peringatan memakai gaya yang sama dengan formatter server, termasuk pemisah ribuan', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '95');

    const peringatan = peringatanPecahan(window);
    assert.match(peringatan, /1\.250,5/, 'QuantityDisplayFormatter::format() memisah ribuan dengan titik; layar tidak boleh beda gaya');
    assert.doesNotMatch(peringatan, /1250,5/, 'angka tanpa pemisah ribuan adalah gaya ketiga');
});

test('sisa ber-SN di bawah satu pcs tidak melahirkan baris, tapi tetap memberi tahu operator', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '94');

    assert.equal(prefillRows(window).length, 0, 'sisa 0,5 pcs bukan satu pcs yang benar-benar ada');
    const peringatan = peringatanPecahan(window);
    assert.ok(peringatan, 'tanpa baris maupun peringatan, request itu lenyap dari layar');
    assert.match(peringatan, /0,5/);
});

test('sisa pecahan yang tidak melahirkan baris menyisakan baris pertama untuk diketik operator', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '94');

    const [first] = rows(window);
    assert.equal(first.hidden, false, 'tidak ada baris prefill, jadi baris ketikan operator tetap dibutuhkan');
    assert.equal(first.querySelector('select').disabled, false);
});

test('sisa ber-SN bulat tidak memunculkan peringatan pecahan sama sekali', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '91');

    assert.equal(peringatanPecahan(window), null, 'request yang sehat tidak boleh diberi peringatan palsu');
});

test('peringatan pecahan bukan penandaan menyimpang', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '93');

    const prefilled = prefillRows(window);
    assert.deepEqual(prefilled.map(penyimpangan), [null, null], 'mengirim 2 dari sisa 2,5 adalah kirim bertahap');
    assert.deepEqual(prefilled.map((row) => row.classList.contains('ui-list__item--deviating')), [false, false]);
    assert.deepEqual(prefilled.map(catatanPenyimpangan), [null, null], 'saluran penyimpangan harus tetap bersih');
});

test('membatalkan pilihan request menghapus peringatan pecahan', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '93');
    assert.ok(peringatanPecahan(window), 'prasyarat: peringatan tampil');

    choose(window, field(window, '[data-request-select]'), '');

    assert.equal(peringatanPecahan(window), null, 'peringatan yang tertinggal akan berbohong');
});

/**
 * Catatan per baris (ADR-0024). Penyimpangan diputuskan server saat terbit, jadi field ini
 * ada di setiap baris — bukan hanya di baris yang klien kebetulan sudah menandai.
 */
const catatanInput = (row) => row.querySelector('[data-catatan-input]');

test('markup baris item Surat Jalan menyediakan field catatan', () => {
    const row = blade().match(/<div class="ui-list__item" data-transfer-row[^>]*>([\s\S]*?)<button class="ui-button ui-button--muted" type="button" data-remove-item/);

    assert.ok(row, 'baris item Surat Jalan tidak ditemukan di markup');
    assert.match(row[1], /name="items\[[^"]*\]\[catatan\]"/, 'baris menyimpang tidak bisa terbit tanpa field catatan');
});

test('baris item Surat Jalan hasil klon punya field catatan sendiri yang kosong', (t) => {
    const window = warehousePage(t);
    rows(window)[0].querySelector('[data-catatan-input]').value = 'Catatan baris pertama';

    window.document.querySelector('[data-add-item]').click();

    const clone = rows(window)[1];
    assert.equal(catatanInput(clone).name, 'items[1][catatan]', 'catatan klon harus punya indeksnya sendiri');
    assert.equal(catatanInput(clone).value, '', 'catatan baris lain bukan warisan');
});

test('baris prefill punya field catatan yang siap diisi operator', (t) => {
    const window = requestDrivenPage(t);

    choose(window, field(window, '[data-request-select]'), '92');

    const [prefilled] = prefillRows(window);
    assert.ok(catatanInput(prefilled), 'baris prefill juga bisa jadi menyimpang begitu qty-nya dinaikkan');
    assert.equal(catatanInput(prefilled).value, '');
    assert.match(catatanInput(prefilled).name, /^items\[\d+\]\[catatan\]$/);
});

test('field catatan tidak pernah wajib secara HTML', () => {
    const input = blade().match(/<input name="items\[[^"]*\]\[catatan\]"[^>]*>/);

    assert.ok(input, 'field catatan baris item Surat Jalan tidak ditemukan');
    assert.doesNotMatch(
        input[0],
        /\brequired\b/,
        'penyimpangan diputuskan server; required di klien akan salah menuduh baris patuh',
    );
});

test('baris yang ditandai menyimpang tetap tidak mewajibkan catatan lewat HTML', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');

    const manual = barisManual(window);
    ketik(window, manual, '3', '5');

    assert.equal(penyimpangan(manual), 'material_asing', 'prasyarat: baris ditandai');
    assert.equal(catatanInput(manual).required, false, 'penandaan klien memandu, bukan menghakimi');
});

test('baris menyimpang memandu operator ke field catatannya', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');

    const manual = barisManual(window);
    ketik(window, manual, '3', '5');

    const note = manual.querySelector('[data-deviation-note]');
    assert.ok(note.id, 'panduan baru tersambung ke field-nya kalau catatan penandaan punya id');
    assert.equal(catatanInput(manual).getAttribute('aria-describedby'), note.id);
    assert.match(catatanInput(manual).placeholder, /[Ww]ajib/, 'panduan harus terbaca tanpa pembaca layar juga');
    assert.match(catatanPenyimpangan(manual), /[Cc]atatan/, 'operator harus tahu apa yang diminta darinya');
});

test('panduan catatan hilang begitu qty dikoreksi kembali ke dalam sisa', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');
    const [prefilled] = prefillRows(window);
    isiQty(window, prefilled, '5');
    assert.ok(catatanInput(prefilled).getAttribute('aria-describedby'), 'prasyarat: baris dipandu');

    isiQty(window, prefilled, '2');

    assert.equal(catatanInput(prefilled).getAttribute('aria-describedby'), null);
    assert.equal(catatanInput(prefilled).placeholder, '', 'panduan yang tertinggal akan berbohong');
});

test('panduan tidak menghapus catatan yang sudah diketik operator', (t) => {
    const window = requestDrivenPage(t);
    choose(window, field(window, '[data-request-select]'), '92');
    const [prefilled] = prefillRows(window);
    catatanInput(prefilled).value = 'Titipan tambahan atas permintaan lapangan';

    isiQty(window, prefilled, '5');
    isiQty(window, prefilled, '2');

    assert.equal(catatanInput(prefilled).value, 'Titipan tambahan atas permintaan lapangan');
});

/**
 * Setelah POST ditolak, Blade merender ulang setiap baris dari `old()`. Fixture ini meniru
 * hasil render itu: request tetap terpilih, dan baris kedua membawa material di luar request.
 */
const halamanSetelahDitolak = (t) => openPage(t, formSuratJalan({
    gudangTujuan: [{ value: 20, label: 'WH-MITRA', selected: true }, { value: 30, label: 'WH-THC-2' }],
    requests: [{ value: 92, label: transferFormData.requests[20][1].label, selected: true }],
    baris: barisSuratJalan({
        sumber: 'prefill',
        pilihan: '<option value="1" data-kind="biasa" selected>MAT-1 — Kabel Patch</option>',
        dikunci: 1,
        qty: 2,
        bisaDihapus: true,
    }) + barisSuratJalan({
        index: 1,
        pilihan: '<option value="3" data-kind="drum_kabel" selected>MAT-3 — Drum Kabel</option>',
        qty: 5,
        bisaDihapus: true,
    }),
}));

test('baris tambahan melanjutkan indeks baris yang sudah dirender server', (t) => {
    const window = halamanSetelahDitolak(t);

    window.document.querySelector('[data-add-item]').click();

    const baru = rows(window).at(-1);
    assert.equal(catatanInput(baru).name, 'items[2][catatan]', 'indeks yang bertabrakan akan menimpa baris yang dipulihkan');
    assert.equal(baru.querySelector('input[type="number"]').name, 'items[2][qty]');
});

test('baris tambahan memakai indeks terbesar yang ada di DOM setelah baris dihapus', (t) => {
    const window = openPage(t, formSuratJalan({
        baris: barisSuratJalan({ index: 4, qty: 2, bisaDihapus: true })
            + barisSuratJalan({ index: 9, pilihan: '<option value="3" data-kind="drum_kabel" selected>MAT-3 — Drum Kabel</option>', qty: 5, bisaDihapus: true }),
    }));

    window.document.querySelector('[data-add-item]').click();

    const baru = rows(window).at(-1);
    assert.equal(catatanInput(baru).name, 'items[10][catatan]');
    assert.equal(baru.querySelector('input[type="number"]').name, 'items[10][qty]');
});

test('baris yang dipulihkan old() sudah ditandai dan dipandu tanpa menunggu operator mengetik', (t) => {
    const window = halamanSetelahDitolak(t);

    const [patuh, menyimpang] = rows(window);
    assert.equal(penyimpangan(patuh), null, 'baris sesuai sisa request tidak boleh ikut tertuduh');
    assert.equal(penyimpangan(menyimpang), 'material_asing');
    assert.ok(
        catatanInput(menyimpang).getAttribute('aria-describedby'),
        'baris yang membuat POST ditolak justru yang paling perlu dipandu',
    );
});

test('baris tambahan tidak mewarisi kunci material baris prefill yang dipulihkan', (t) => {
    const window = halamanSetelahDitolak(t);

    window.document.querySelector('[data-add-item]').click();

    const baru = rows(window).at(-1);
    assert.equal(baru.querySelector('select').disabled, false, 'baris ketikan baru harus bisa dipilih materialnya');
    assert.equal(
        baru.querySelector('input[type="hidden"][name$="[material_id]"]'),
        null,
        'kunci material milik baris prefill, bukan warisan untuk baris berikutnya',
    );
});

test('membuang baris prefill yang dipulihkan old() menyisakan baris ketikan operator apa adanya', (t) => {
    const window = halamanSetelahDitolak(t);

    rows(window)[0].querySelector('[data-remove-item]').click();

    const tersisa = rows(window);
    assert.equal(tersisa.length, 1, 'baris ketikan operator sudah cukup; tidak perlu baris tambahan');
    assert.equal(tersisa[0].dataset.rowSumber, 'operator');
    assert.equal(tersisa[0].querySelector('input[type="number"]').value, '5', 'ketikan operator tidak boleh tersentuh');
});

test('membuang setiap baris yang dipulihkan old() tetap menyisakan satu baris siap diketik', (t) => {
    const window = halamanSetelahDitolak(t);

    rows(window).forEach((row) => row.querySelector('[data-remove-item]').click());

    const tersisa = rows(window);
    assert.equal(tersisa.length, 1, 'baris pertama hasil old() bisa berupa baris prefill; penggantinya harus ada');
    assert.equal(tersisa[0].hidden, false);
    const material = tersisa[0].querySelector('select');
    assert.equal(material.disabled, false, 'baris pengganti tidak boleh mewarisi kunci material baris prefill');
    assert.equal(material.value, '');
    assert.equal(tersisa[0].querySelector('input[type="number"]').value, '');
    assert.equal(tersisa[0].querySelector('input[type="hidden"][name$="[material_id]"]'), null);
    assert.equal(tersisa[0].dataset.rowSumber, 'operator');
});

/**
 * Sisi JS dari kontrak lintas bahasa yang dijanjikan ADR-0026. Kasusnya tidak ditulis di sini
 * melainkan dibaca dari berkas yang sama yang dibaca tests/Feature/SuratJalanDeviationContractTest.php
 * atas `SuratJalanService::classifyRequestDeviations()`. Klasifikator klien memang kembar dengan
 * yang di server dan itu disengaja; berkas itu yang membuat keduanya tidak bisa menyimpang diam-diam.
 */
const kontrak = JSON.parse(readFileSync(
    fileURLToPath(new URL('../fixtures/klasifikasi-penyimpangan.json', import.meta.url)),
    'utf8',
));

const sisaKontrak = (kasus, materialId) => {
    const line = kasus.request.find((candidate) => candidate.material_id === materialId);
    assert.ok(line, 'kasus batas toleransi hanya berlaku pada material yang ada di request');

    return Math.max(line.diminta - line.terkirim, 0);
};

/**
 * Kasus batas menyebut toleransi sebagai faktor, bukan angka: tiap sisi mengalikannya dengan
 * ambang aplikasinya sendiri — di sini ambang itu diambil dari payload, sama seperti skrip halaman.
 */
const qtyKontrak = (kasus, baris) => ('qty' in baris
    ? baris.qty
    : sisaKontrak(kasus, baris.material_id) + baris.qty_sisa_plus_toleransi * transferFormData.qty_tolerance);

/** Request kontrak menempati gudang tujuan 20; sisanya diturunkan dari kasus, bukan diketik. */
const payloadKontrak = (kasus) => ({
    ...transferFormData,
    requests: {
        20: [{
            id: 91,
            mitra_id: 7,
            project_id: null,
            tanggal: '2026-08-20',
            status: 'disetujui',
            label: KONTRAK_LABEL,
            items: kasus.request.map((line) => ({
                material_id: line.material_id,
                jenis: 'biasa',
                diminta: line.diminta,
                terkirim: line.terkirim,
                sisa: Math.max(line.diminta - line.terkirim, 0),
            })),
        }],
    },
});

const KONTRAK_LABEL = '#91 — kontrak klasifikasi';

const halamanKontrak = (t, kasus) => openPage(t, formSuratJalan({
    gudangAsal: [{ value: 10, label: 'WH-THC' }],
    gudangTujuan: [{ value: 20, label: 'WH-MITRA' }],
    requests: [{ value: 91, label: KONTRAK_LABEL }],
    payload: payloadKontrak(kasus),
}));

/**
 * Baris kasus diketik operator satu per satu, bukan dipasang lewat atribut: yang dijamin kontrak
 * ini adalah kesimpulan klasifikasinya, dan jalur ketikan itulah yang benar-benar dilewati operator.
 */
const ketikBarisKontrak = (window, kasus) => kasus.baris.map((baris, index) => {
    const row = index === 0 ? rows(window)[0] : barisManual(window);
    ketik(window, row, String(baris.material_id), String(qtyKontrak(kasus, baris)));

    return row;
});

describe('kontrak klasifikasi penyimpangan', { concurrency: true }, () => {
    kontrak.kasus.forEach((kasus) => {
        it(kasus.nama, (t) => {
            const window = halamanKontrak(t, kasus);
            // Request kontrak dipilih lebih dulu; prefillnya sengaja dibuang supaya yang dinilai
            // hanyalah baris yang disebut fixture.
            choose(window, field(window, '[data-request-select]'), '91');
            rows(window)
                .filter((row) => row.dataset.rowSumber === 'prefill')
                .forEach((row) => row.querySelector('[data-remove-item]').click());

            const baris = ketikBarisKontrak(window, kasus);

            assert.deepEqual(
                baris.map(penyimpangan),
                kasus.baris.map((row) => kasus.klasifikasi[String(row.material_id)] ?? null),
            );
        });
    });

    it('fixture kontrak menyebut setiap jenis penyimpangan', () => {
        const jenis = kontrak.kasus.flatMap((kasus) => Object.values(kasus.klasifikasi));

        [null, 'material_asing', 'qty_melebihi'].forEach((harus) => {
            assert.ok(jenis.includes(harus), `fixture kontrak harus memuat kasus ${harus ?? 'patuh'}`);
        });
    });

    /**
     * Sisi klien dari janji "Mitra efektif awal dilayani satu sumber": skrip tidak menghitung
     * nilai awalnya sendiri. Dijaga di sini, bukan di test PHP, supaya aturannya tidak perlu
     * diketik ulang di test hanya untuk membuktikan ia tidak diketik ulang di produksi.
     */
    it('mitra efektif awal diambil skrip dari payload, tidak dihitung ulang', (t) => {
        const window = openPage(t, formSuratJalan({
            gudangAsal: [{ value: 10, label: 'WH-THC' }],
            gudangTujuan: [{ value: 20, label: 'WH-MITRA' }],
            payload: {
                ...transferFormData,
                // Gudang asal 10 milik THC dan tujuan 20 milik Mitra 7, tapi payload menyebut Mitra 9:
                // klien yang menghitung sendiri akan menjawab 7 dan menyusun daftar Project yang salah.
                initial_mitra_id: 9,
                projects: [
                    { id: 55, id_project: 'PRJ-2608-0001', nama: 'Alpha', mitra_id: 7, label: 'PRJ-2608-0001 — Alpha' },
                    { id: 57, id_project: 'PRJ-2608-0003', nama: 'Gamma', mitra_id: 9, label: 'PRJ-2608-0003 — Gamma' },
                ],
            },
        }));

        // Ganti gudang tujuan ke nilai yang sudah terpilih: Mitra efektifnya tidak berubah, jadi
        // daftar Project hanya tersusun ulang bila skrip berangkat dari Mitra yang berbeda.
        choose(window, field(window, '[data-tujuan-select]'), '20');

        assert.deepEqual(
            optionValues(field(window, '[data-project-select]')),
            ['', '55'],
            'skrip yang memakai initial_mitra_id dari payload akan menganggap Mitra berubah 9 → 7',
        );
    });

    /**
     * Ambang yang diketik ulang di klien adalah ambang kedua yang bisa menyimpang dari server.
     * Dibuktikan lewat perilaku, bukan lewat pembacaan teks sumber: payload dengan ambang yang
     * sengaja tidak masuk akal harus tetap dipatuhi, dan klien yang memakai angkanya sendiri
     * menjawab lain untuk qty yang sama.
     */
    const denganToleransi = (toleransi) => ({ ...transferFormData, qty_tolerance: toleransi });

    it('ambang klasifikasi diambil dari payload, bukan angka milik klien sendiri', (t) => {
        // Material 1 bersisa 6 pada request #91; qty 10 melebihinya sebanyak 4.
        const window = requestDrivenPage(t, denganToleransi(5));
        choose(window, field(window, '[data-request-select]'), '91');
        buangSemuaBarisPrefill(window);

        ketik(window, rows(window)[0], '1', '10');

        assert.equal(
            penyimpangan(rows(window)[0]),
            null,
            'ambang 5 menampung kelebihan 4; klien yang memakai ambangnya sendiri akan menandai baris ini',
        );
    });

    it('ambang payload yang rapat tetap menandai kelebihan sekecil apa pun', (t) => {
        const window = requestDrivenPage(t, denganToleransi(0));
        choose(window, field(window, '[data-request-select]'), '91');
        buangSemuaBarisPrefill(window);

        ketik(window, rows(window)[0], '1', '6.5');

        assert.equal(penyimpangan(rows(window)[0]), 'qty_melebihi');
    });
});

/**
 * Penjaga kolam Window, sengaja dideklarasikan paling akhir supaya ia berjalan sesudah seluruh
 * test lain selesai meminjam.
 *
 * Yang dijaga bukan angka memori — itu bergantung host dan akan flaky — melainkan sebabnya:
 * berapa banyak konteks V8 yang dibangun berkas ini. Setiap `new Window()` menyisakan satu
 * konteks yang tidak pernah dilepas Node, jadi jumlah Window yang dibangun harus tetap sekecil
 * jumlah test yang benar-benar berjalan berbarengan, bukan tumbuh mengikuti jumlah test.
 * Kembali membangun satu Window per test akan membuat `dibangun` menyamai `dipinjam` di sini.
 */
test('fixture memakai ulang Window happy-dom, bukan membangun satu per test', () => {
    assert.ok(
        windowStats.dipinjam >= 50,
        `prasyarat: penjaga ini hanya bermakna kalau banyak test sudah meminjam (dipinjam=${windowStats.dipinjam})`,
    );

    // Ambangnya nisbi, bukan angka mati: berapa Window yang dibangun sama dengan berapa test yang
    // sempat berjalan berbarengan, dan itu ditentukan penjadwalan host. Yang harus mustahil adalah
    // pertumbuhan yang mengikuti jumlah test — kembali ke satu Window per test membuat kedua angka
    // ini sama besar.
    assert.ok(
        windowStats.dibangun * 3 < windowStats.dipinjam,
        `berkas ini membangun ${windowStats.dibangun} Window untuk ${windowStats.dipinjam} peminjaman; `
        + 'setiap Window menyisakan satu konteks V8 yang tidak pernah dilepas Node, jadi kolamnya harus dipakai ulang (#176)',
    );
});
