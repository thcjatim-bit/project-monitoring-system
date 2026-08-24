import { initializeSubmitLoading } from './submit-loading.js';
import {
    initializeSearchableSelects,
    refreshSearchableSelectOptions,
    setSearchableSelectValue,
} from './searchable-select.js';

/**
 * Logika klien halaman Warehouse: kotak identitas, siklus hidup baris Surat Jalan, dan form
 * request-driven (prefill, kunci Project, penandaan penyimpangan). Daur hidup tombol submit
 * punya modulnya sendiri dan dinyalakan dari sini, karena sampai sekarang hanya halaman ini
 * yang memilikinya.
 *
 * Antarmukanya satu fungsi. Blade hanya menyediakan payload (`[data-transfer-form-data]`) dan
 * markup; tidak ada aturan aplikasi yang tinggal di view. Semua perilaku dilewati lewat DOM, jadi
 * seam ini pula yang dipakai test — tidak ada jalan tembus ke dalam.
 *
 * Halaman lain tidak punya penanda `[data-warehouse-page]` dan pemanggilan di sana tidak
 * melakukan apa pun. Penjaganya ada di sini, bukan di app.js, supaya `data-submit-loading`
 * di belasan view lain tetap tidak berpenangan persis seperti sebelumnya.
 */
export function initializeWarehouseMaterialForm(document = globalThis.document) {
    if (document?.querySelector('[data-warehouse-page]') == null) {
        return;
    }

    // Event dan timer diambil dari window pemilik dokumen, bukan dari global: fixture test hidup
    // di realm-nya sendiri, dan Event di sana bukan Event yang sama dengan milik realm ini.
    const window = document.defaultView ?? globalThis;

    let refreshTransferIdentityState = () => {};
    const wholeQuantityKinds = new Set(['biasa', 'ber_sn', 'drum_kabel']);
    const updateQuantityAttributes = (quantity, kind) => {
        const whole = wholeQuantityKinds.has(kind);
        quantity.step = whole ? '1' : '0.001';
        quantity.min = whole ? '1' : '0.001';
    };
    const identityScope = (select) => select.closest('[data-transfer-row]') ?? select.closest('form');
    const toggleIdentity = (select, clearUnavailable = false) => {
        const kind = select.options[select.selectedIndex]?.dataset.kind ?? '';
        const scope = identityScope(select);
        if (clearUnavailable) {
            scope?.querySelectorAll('[data-identity-unavailable]').forEach((field) => {
                field.removeAttribute('data-identity-unavailable');
                field.querySelector('[data-identity-unavailable-note]')?.remove();
            });
        }
        scope?.querySelectorAll('[data-identity]').forEach((field) => {
            const visible = (field.dataset.identity === 'serial_number' && kind === 'ber_sn') || (field.dataset.identity === 'drum_id' && kind === 'drum_kabel');
            field.hidden = !visible;
            const nativeSelect = field.querySelector('[data-ui-select-native]');
            if (nativeSelect) {
                nativeSelect.required = visible;
            } else {
                field.querySelectorAll('input:not([type="hidden"]):not([type="search"])').forEach((input) => input.toggleAttribute('required', visible));
            }
        });
        const quantity = scope?.querySelector('input[type="number"]');
        if (quantity) {
            updateQuantityAttributes(quantity, kind);
        }
        if (quantity && kind === 'ber_sn') {
            quantity.value = '1';
            quantity.readOnly = true;
            quantity.setAttribute('aria-readonly', 'true');
        } else if (quantity) {
            quantity.readOnly = false;
            quantity.removeAttribute('aria-readonly');
        }
        refreshTransferIdentityState();
    };
    const bindIdentity = (select) => { select.addEventListener('change', () => toggleIdentity(select, true)); toggleIdentity(select); };
    document.querySelectorAll('input[type="number"][data-quantity-kind]').forEach((quantity) => {
        updateQuantityAttributes(quantity, quantity.dataset.quantityKind);
    });
    // Satu selector untuk halaman maupun baris klon, supaya identity select tidak ikut dianggap
    // sebagai material select setelah komponen searchable-select masuk ke dalam baris.
    const bindSelects = (root) => root.querySelectorAll('[data-material-select]').forEach(bindIdentity);
    bindSelects(document);
    // Indeks klon melanjutkan indeks terbesar yang dirender server — old() bisa memulihkan baris
    // dengan indeks renggang setelah operator menghapus baris di tengah.
    const items = document.querySelector('[data-transfer-items]');
    const itemIndex = (name) => name?.match(/^items\[(\d+)\]/)?.[1];
    const nextItemIndex = () => {
        let largest = -1;
        items?.querySelectorAll('[name]').forEach((field) => {
            const index = Number.parseInt(itemIndex(field.name) ?? '', 10);
            if (Number.isInteger(index)) largest = Math.max(largest, index);
        });

        return largest + 1;
    };
    let nextTransferIndex = nextItemIndex();
    // Klon diambil dari salinan bersih baris pertama, bukan dari baris pertama yang hidup: baris itu
    // boleh dinonaktifkan saat prefill mengambil alih, dan klon tidak boleh ikut mewarisi keadaannya.
    const rowTemplate = items?.querySelector('[data-transfer-row]')?.cloneNode(true) ?? null;
    // Semua baris baru adalah klon baris pertama, jadi indeks dan binding-nya tidak pernah dirakit tangan.
    const buildRow = (asal) => {
        const source = rowTemplate; const index = nextTransferIndex++; const row = source.cloneNode(true);
        row.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/items\[\d+\]/g, `items[${index}]`); input.value = ''; });
        row.querySelectorAll('[id], [aria-controls]').forEach((element) => {
            ['id', 'aria-controls'].forEach((attribute) => {
                const value = element.getAttribute(attribute);
                if (value) element.setAttribute(attribute, value.replace(/transfer-item-\d+-/g, `transfer-item-${index}-`));
            });
        });
        // Baris pertama bisa saja baris prefill yang dipulihkan old(), lengkap dengan material terkunci.
        // Klon selalu baris ketikan baru, jadi kunci itu dilepas alih-alih diwariskan.
        row.querySelector('input[type="hidden"][name$="[material_id]"]')?.remove();
        row.querySelectorAll('[data-identity-unavailable]').forEach((field) => field.removeAttribute('data-identity-unavailable'));
        row.querySelectorAll('[data-identity-unavailable-note]').forEach((note) => note.remove());
        row.querySelectorAll('select, input').forEach((field) => { field.disabled = false; });
        row.querySelectorAll('[data-ui-select]').forEach((root) => {
            root.removeAttribute('data-ui-select-bound');
            root.dataset.open = 'false';
            root.hidden = true;
            root.querySelector('[data-ui-select-native]')?.removeAttribute('hidden');
            root.querySelector('[data-ui-select-native]')?.removeAttribute('disabled');
            root.querySelector('[data-ui-select-value]')?.setAttribute('disabled', 'disabled');
            root.querySelectorAll('[data-ui-select-option]').forEach((option) => option.removeAttribute('data-ui-select-option-bound'));
        });
        // Asal-usul baris dibawa hidden input, bukan sekadar atribut DOM, supaya bertahan melewati repopulasi old().
        const asalInput = row.querySelector('[data-row-origin]'); if (asalInput) asalInput.value = asal;
        row.dataset.rowAsal = asal;
        row.querySelector('[data-remove-item]').hidden = false; items.append(row); bindSelects(row);
        initializeSearchableSelects(document);
        row.querySelectorAll('[data-ui-select]').forEach((root) => setSearchableSelectValue(root, ''));
        return { row, index };
    };
    document.querySelector('[data-add-item]')?.addEventListener('click', () => { buildRow('manual'); refreshTransferIdentityState(); });
    items?.addEventListener('click', (event) => { if (event.target.matches('[data-remove-item]')) event.target.closest('[data-transfer-row]').remove(); });
    // Form request-driven: penyaringan dan prefill berjalan murni di klien di atas payload yang diserialisasi Blade.
    const transferForm = document.querySelector('[data-transfer-form]');
    const formDataScript = document.querySelector('[data-transfer-form-data]');
    const formData = formDataScript ? JSON.parse(formDataScript.textContent) : null;
    if (formData && transferForm) {
        const originSelect = transferForm.querySelector('[data-origin-select]');
        const destinationSelect = transferForm.querySelector('[data-destination-select]');
        const requestSelect = transferForm.querySelector('[data-request-select]');
        const projectSelect = transferForm.querySelector('[data-project-select]');
        const option = (value, label) => { const created = document.createElement('option'); created.value = String(value); created.textContent = label; return created; };
        // Mitra Surat Jalan ditentukan gudang asal, dan jatuh ke gudang tujuan bila asal milik THC.
        const effectiveMitra = () => formData.warehouse_mitra[originSelect.value] ?? formData.warehouse_mitra[destinationSelect.value] ?? null;
        // Aturannya tetap hidup di sini karena ganti gudang harus menilainya ulang; yang tidak
        // dihitung ulang adalah nilai AWALNYA. Server sudah merakitnya dan merender dropdown Project
        // di atasnya, jadi mengambilnya dari payload membuat keduanya mustahil berbeda saat muat.
        let currentMitra = formData.initial_mitra_id;
        // Gudang tujuan adalah filter wajib; Project mempersempit daftar tapi request tanpa Project tetap muncul.
        const availableRequests = () => (formData.requests[destinationSelect.value] ?? []).filter((request) =>
            projectSelect.value === '' || request.project_id === null || String(request.project_id) === projectSelect.value);
        // Project yang dibawa request adalah pinjaman: nilainya milik request, bukan pilihan operator.
        // Nilai pra-kunci disimpan saat mengunci, bukan ditebak saat melepas — hanya itu yang bisa
        // membedakan "operator belum memilih apa-apa" dari "operator memilih Project ini sendiri".
        // Kunci hasil render Blade (old()) tidak punya nilai pra-kunci, jadi jatuh ke kosong.
        let projectSebelumKunci = null;
        const renderProjects = () => {
            const mitraId = effectiveMitra();
            // Daftar Project ditulis ulang karena Mitra berganti, jadi nilai pra-kunci yang
            // tersimpan menunjuk Mitra lama dan tidak boleh dipulihkan ke daftar yang baru.
            projectSebelumKunci = null;
            projectSelect.replaceChildren();
            projectSelect.disabled = mitraId === null;
            if (mitraId === null) { projectSelect.append(option('', projectSelect.dataset.lockedLabel)); return; }
            projectSelect.append(option('', projectSelect.dataset.emptyLabel));
            formData.projects.filter((project) => project.mitra_id === mitraId).forEach((project) => projectSelect.append(option(project.id, project.label)));
        };
        const renderRequests = (preserveUnavailable = false) => {
            const selectedValue = requestSelect.value;
            const selectedOption = requestSelect.options[requestSelect.selectedIndex];
            const selectedLabel = selectedOption?.textContent ?? '';
            const requests = availableRequests();
            requestSelect.replaceChildren(option('', requestSelect.dataset.emptyLabel));
            requests.forEach((request) => requestSelect.append(option(request.id, request.label)));
            if (preserveUnavailable && selectedValue !== ''
                && ! requests.some((request) => String(request.id) === selectedValue)) {
                requestSelect.append(option(selectedValue, selectedLabel));
            }
            if (selectedValue !== '' && requestSelect.querySelector('option[value="' + selectedValue + '"]') !== null) {
                requestSelect.value = selectedValue;
            }
        };
        // Baris pertama selalu ada sebagai baris ketikan operator, dan field-nya wajib diisi. Begitu
        // prefill mengisi form, baris itu tinggal hampa tapi tetap menahan submit; dinonaktifkan
        // supaya lepas dari validasi HTML dan tidak ikut terkirim sebagai item kosong.
        // Baris pertama dicari ulang setiap kali, bukan disimpan sekali: baris pertama hasil old()
        // bisa saja baris prefill yang ikut terbuang, dan penggantinya yang harus dilayani.
        const firstRow = () => items.querySelector('[data-transfer-row]');
        const firstRowIsEmpty = () => [...firstRow().querySelectorAll('select, input:not([type="hidden"])')]
            .every((field) => field.value === '');
        const idleFirstRow = (idle) => {
            const row = firstRow();
            row.hidden = idle;
            row.querySelectorAll('select, input').forEach((field) => { field.disabled = idle; });
        };
        // Form selalu menyisakan satu baris untuk diketik operator; tanpa itu tidak ada yang bisa
        // diisi dan Surat Jalan tidak bisa terbit. Baris pertama hasil old() bisa berupa baris
        // prefill, jadi baris itu pun bisa ikut terbuang.
        const ensureTypingRow = () => { if (firstRow() === null) buildRow('manual'); };
        // Membuang baris prefill selalu berarti baris pertama dibutuhkan kembali, jadi keduanya satu langkah.
        const dropPrefillRows = () => {
            items.querySelectorAll('[data-row-asal="request"]').forEach((row) => row.remove());
            transferForm.querySelector('[data-fraction-notice]')?.remove();
            ensureTypingRow();
            idleFirstRow(false);
        };
        // Baris prefill juga pergi satu per satu lewat tombolnya sendiri, bukan hanya lewat ganti
        // pilihan. Baris prefill terakhir yang dibuang berarti prefill habis, dan itu ditempuh lewat
        // jalur yang sama supaya "prefill habis" tidak pernah berarti dua hal berbeda. Membuang baris
        // ketikan operator bukan itu — peringatan pecahan bisa hidup tanpa satu pun baris prefill —
        // jadi yang dijaga hanya barisnya. Terdaftar sebelum penandaan menyimpang, supaya baris
        // pertama sudah pulih saat penandaan menghitung ulang.
        items.addEventListener('click', (event) => {
            if (! event.target.matches('[data-remove-item]')) return;
            const prefillHabis = event.target.closest('[data-transfer-row]')?.dataset.rowAsal === 'request'
                && items.querySelector('[data-row-asal="request"]') === null;
            if (prefillHabis) { dropPrefillRows(); refreshTransferIdentityState(); return; }
            ensureTypingRow();
            refreshTransferIdentityState();
        });
        const unlockProject = () => {
            const lock = transferForm.querySelector('[data-project-lock]');
            if (lock !== null) {
                lock.remove();
                projectSelect.value = projectSebelumKunci ?? '';
                projectSebelumKunci = null;
            }
            projectSelect.disabled = effectiveMitra() === null;
        };
        const lockProject = (projectId) => {
            projectSebelumKunci = projectSelect.value;
            projectSelect.value = String(projectId); projectSelect.disabled = true;
            // Select yang disabled tidak ikut terkirim, jadi nilai terkuncinya dibawa hidden input.
            const lock = document.createElement('input');
            lock.type = 'hidden'; lock.name = 'project_id'; lock.value = String(projectId); lock.setAttribute('data-project-lock', '');
            projectSelect.after(lock);
        };
        const prefillRow = (item, qty) => {
            const { row, index } = buildRow('request');
            const materialSelect = row.querySelector('select');
            materialSelect.value = String(item.material_id); materialSelect.disabled = true;
            // Material baris prefill dikunci; select disabled tidak terkirim, hidden input yang membawanya.
            const locked = document.createElement('input');
            locked.type = 'hidden'; locked.name = 'items[' + index + '][material_id]'; locked.value = String(item.material_id);
            materialSelect.after(locked);
            row.querySelector('input[type="number"]').value = String(qty);
            materialSelect.dispatchEvent(new window.Event('change'));
        };
        // Sisa pecahan pada material ber-SN adalah data cacat dari hulu (ADR-0025), bukan penyimpangan
        // Surat Jalan: mengirim 2 dari sisa 2,5 tetap kirim bertahap, dan server tidak menandainya apa
        // pun saat terbit. Karena itu peringatannya punya salurannya sendiri di tingkat form, terpisah
        // dari markRow — memakai ulang saluran penyimpangan akan membuat layar dan server beda kesimpulan.
        // Pasangan klien dari QuantityDisplayFormatter::format(): pemisah ribuan titik, koma desimal,
        // tiga angka di belakang, nol di ekor dibuang — supaya angka yang sama tidak tampil dua gaya
        // di halaman yang sama.
        const angka = (nilai) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(nilai);
        // Nama materialnya diambil dari daftar opsi yang sudah dirender Blade; payload request hanya membawa id.
        const materialLabel = (materialId) => rowTemplate?.querySelector('option[value="' + materialId + '"]')?.textContent ?? 'material #' + materialId;
        const reportFractions = (request, pecahan) => {
            transferForm.querySelector('[data-fraction-notice]')?.remove();
            if (pecahan.length === 0) return;
            const notice = document.createElement('div');
            notice.className = 'ui-state ui-state--warning';
            notice.setAttribute('data-fraction-notice', ''); notice.setAttribute('role', 'status');
            // Angkanya, sebabnya, dan siapa yang membetulkan — operator Surat Jalan bukan pihak yang salah.
            notice.replaceChildren(...pecahan.map(({ item, barisan }) => {
                const kalimat = document.createElement('p');
                kalimat.textContent = 'Request #' + request.id + ' mencatat ' + angka(item.sisa) + ' ' + materialLabel(item.material_id)
                    + ' — material ber-Serial Number tidak bisa pecahan. '
                    + (barisan === 0 ? 'Tidak ada pcs yang dapat ter-prefill' : barisan + ' pcs ter-prefill')
                    + '; sisa ' + angka(item.sisa - barisan) + ' tidak dapat dikirim dan perlu dibetulkan pada Request Material.';
                return kalimat;
            }));
            items.before(notice);
        };
        // Penyimpangan ditandai, tidak diblokir (ADR-0024). Klasifikasinya sengaja meniru
        // SuratJalanService::classifyRequestDeviations(): diukur per material atas seluruh baris
        // dengan toleransi yang sama, supaya peringatan di layar dan penilaian server saat terbit
        // tidak mungkin berbeda kesimpulan. Ambangnya pun tidak diketik ulang di sini: ia dibawa
        // payload dari App\Support\QtyTolerance, konstanta yang sama yang dibaca server.
        const QTY_TOLERANCE = formData.qty_tolerance;
        const DEVIATING_CLASS = 'ui-list__item--deviating';
        // Material baris prefill dibawa hidden input karena selectnya dikunci disabled.
        const rowMaterial = (row) => row.querySelector('input[type="hidden"][name$="[material_id]"]')?.value
            ?? row.querySelector('select')?.value ?? '';
        const rowQty = (row) => Number.parseFloat(row.querySelector('input[type="number"]')?.value ?? '') || 0;
        const identityRoot = (row, identity) => row.querySelector(`[data-identity="${identity}"] [data-identity-select]`);
        const identityValue = (root) => root?.querySelector('[data-ui-select-value]')?.value
            ?? root?.querySelector('[data-ui-select-native]')?.value
            ?? '';
        const identityType = (identity) => identity === 'serial_number' ? 'sn' : 'drum';
        const identitySource = (warehouseId, materialId, type) => formData.identities?.[warehouseId]?.[materialId]
            ?.filter((identity) => identity.type === type) ?? [];
        const identityKey = (materialId, type, value) => `${materialId}|${type}|${value}`;
        const lastIdentityValues = new WeakMap();
        let identityStateInitialized = false;
        const updateIdentityQuantity = (row, kind) => {
            const quantity = row.querySelector('input[type="number"]');
            if (!quantity) return;

            const serialRoot = identityRoot(row, 'serial_number');
            const drumRoot = identityRoot(row, 'drum_id');
            const selectedRoot = kind === 'ber_sn' ? serialRoot : kind === 'drum_kabel' ? drumRoot : null;
            const selectedValue = identityValue(selectedRoot);
            const previousValue = lastIdentityValues.get(row);

            if (kind === 'ber_sn') {
                quantity.value = '1';
                quantity.readOnly = true;
                quantity.setAttribute('aria-readonly', 'true');
            } else if (kind === 'drum_kabel') {
                quantity.readOnly = false;
                quantity.removeAttribute('aria-readonly');
                if (selectedValue !== '' && selectedValue !== previousValue) {
                    const selected = identitySource(originSelect.value, rowMaterial(row), 'drum')
                        .find((identity) => identity.value === selectedValue);
                    if (selected) quantity.value = String(selected.sisa);
                }
            } else {
                quantity.readOnly = false;
                quantity.removeAttribute('aria-readonly');
            }

            lastIdentityValues.set(row, selectedValue);
        };
        refreshTransferIdentityState = () => {
            const selectedByIdentity = new Map();
            const transferRows = [...items.querySelectorAll('[data-transfer-row]')];

            if (!identityStateInitialized) {
                transferRows.forEach((row) => {
                    const materialId = rowMaterial(row);
                    const kind = row.querySelector('[data-material-select]')?.options[row.querySelector('[data-material-select]')?.selectedIndex]?.dataset.kind ?? '';
                    const selectedRoot = kind === 'ber_sn' ? identityRoot(row, 'serial_number') : kind === 'drum_kabel' ? identityRoot(row, 'drum_id') : null;
                    lastIdentityValues.set(row, identityValue(selectedRoot));
                });
                identityStateInitialized = true;
            }

            transferRows.forEach((row) => {
                const materialId = rowMaterial(row);
                for (const identity of ['serial_number', 'drum_id']) {
                    const value = identityValue(identityRoot(row, identity));
                    if (materialId !== '' && value !== '') {
                        const key = identityKey(materialId, identityType(identity), value);
                        if (!selectedByIdentity.has(key)) selectedByIdentity.set(key, new Set());
                        selectedByIdentity.get(key).add(row);
                    }
                }
            });

            transferRows.forEach((row) => {
                const materialId = rowMaterial(row);
                const materialSelect = row.querySelector('[data-material-select]');
                const kind = materialSelect?.options[materialSelect.selectedIndex]?.dataset.kind ?? '';

                for (const identity of ['serial_number', 'drum_id']) {
                    const root = identityRoot(row, identity);
                    if (!root) continue;

                    const visible = (identity === 'serial_number' && kind === 'ber_sn')
                        || (identity === 'drum_id' && kind === 'drum_kabel');
                    const currentValue = identityValue(root);
                    const entries = visible ? identitySource(originSelect.value, materialId, identityType(identity)) : [];
                    const options = entries.map((entry) => ({
                        value: entry.value,
                        label: entry.type === 'drum' ? `${entry.value} — sisa ${angka(entry.sisa)}` : entry.value,
                        searchText: entry.value,
                        disabled: selectedByIdentity.get(identityKey(materialId, entry.type, entry.value))?.size > 0
                            && !selectedByIdentity.get(identityKey(materialId, entry.type, entry.value)).has(row),
                    }));

                    refreshSearchableSelectOptions(root, options);
                    if (currentValue !== '' && !entries.some((entry) => entry.value === currentValue)) {
                        setSearchableSelectValue(root, '');
                    }
                }

                updateIdentityQuantity(row, kind);
            });
        };
        const clearUnavailableIdentity = (root) => {
            const field = root?.closest('[data-identity]');
            if (!field) return;
            field.removeAttribute('data-identity-unavailable');
            field.querySelector('[data-identity-unavailable-note]')?.remove();
        };
        items.addEventListener('change', (event) => {
            clearUnavailableIdentity(event.target.closest?.('[data-identity-select]'));
            refreshTransferIdentityState();
        });
        let nextNoteId = 0;
        const deviationNote = (row) => {
            const existing = row.querySelector('[data-deviation-note]');
            if (existing) return existing;
            const note = document.createElement('p');
            note.className = 'ui-help'; note.setAttribute('data-deviation-note', ''); note.setAttribute('role', 'status');
            // Catatan penandaan sekaligus jadi deskripsi field catatan, jadi ia butuh id sendiri.
            note.id = 'deviation-note-' + (nextNoteId += 1);
            row.append(note);
            return note;
        };
        // Panduan, bukan penghakiman: field catatan tidak pernah diberi `required`. Penyimpangan
        // diputuskan server saat terbit, dan baris yang klien kira patuh bisa saja tidak patuh.
        const CATATAN_PLACEHOLDER = 'Wajib: alasan penyimpangan baris ini';
        const guideCatatan = (row, note) => {
            const catatan = row.querySelector('[data-catatan-input]');
            if (!catatan) return;
            if (note === null) { catatan.removeAttribute('aria-describedby'); catatan.placeholder = ''; return; }
            catatan.setAttribute('aria-describedby', note.id); catatan.placeholder = CATATAN_PLACEHOLDER;
        };
        const markRow = (row, jenis, sisa) => {
            row.classList.toggle(DEVIATING_CLASS, jenis !== null);
            if (jenis === null) {
                delete row.dataset.deviation; row.querySelector('[data-deviation-note]')?.remove(); guideCatatan(row, null);
                return;
            }
            row.dataset.deviation = jenis;
            // Warna saja tidak cukup: alasannya harus terbaca, dan sisa harus disebut angkanya.
            const note = deviationNote(row);
            note.textContent = (jenis === 'material_asing'
                ? 'Menyimpang: material ini tidak ada di Request Material yang dipilih.'
                : 'Menyimpang: total qty material ini melebihi sisa Request Material (sisa ' + sisa + ').')
                + ' Isi Catatan baris ini — baris menyimpang tanpa catatan tidak bisa terbit.';
            guideCatatan(row, note);
        };
        const markDeviations = () => {
            const request = availableRequests().find((candidate) => String(candidate.id) === requestSelect.value);
            const sisa = new Map((request?.items ?? []).map((item) => [String(item.material_id), item.sisa]));
            const total = new Map();
            const baris = [...items.querySelectorAll('[data-transfer-row]')];
            // Baris yang disembunyikan tidak ikut terkirim, jadi ia tidak menambah total dan tidak pernah ditandai.
            baris.filter((row) => !row.hidden).forEach((row) => {
                const material = rowMaterial(row);
                if (material !== '') total.set(material, (total.get(material) ?? 0) + rowQty(row));
            });
            baris.forEach((row) => {
                const material = rowMaterial(row);
                if (request === undefined || row.hidden || material === '') { markRow(row, null); return; }
                if (!sisa.has(material)) { markRow(row, 'material_asing'); return; }
                markRow(row, total.get(material) > sisa.get(material) + QTY_TOLERANCE ? 'qty_melebihi' : null, sisa.get(material));
            });
        };
        items.addEventListener('input', markDeviations);
        items.addEventListener('change', markDeviations);
        // Tambah dan hapus baris tidak memicu input maupun change, jadi keduanya menandai ulang sendiri.
        // Keduanya terdaftar setelah penangan yang benar-benar mengubah baris, jadi selalu berjalan sesudahnya.
        document.querySelector('[data-add-item]')?.addEventListener('click', markDeviations);
        items.addEventListener('click', (event) => { if (event.target.matches('[data-remove-item]')) markDeviations(); });
        const applyRequest = () => {
            dropPrefillRows(); unlockProject();
            const request = availableRequests().find((candidate) => String(candidate.id) === requestSelect.value);
            if (!request) return;
            if (request.project_id !== null) lockProject(request.project_id);
            // Qty prefill adalah sisa, material bersisa 0 tidak muncul, dan baris ber-SN dipecah satu baris per pcs.
            const pecahan = [];
            request.items.filter((item) => item.sisa > 0).forEach((item) => {
                const berSn = item.jenis === 'ber_sn';
                // Floor, bukan round: sisa 2,5 pcs ber-SN berarti dua pcs yang benar-benar ada.
                // Membulatkan ke atas menaikkan total qty diam-diam dan justru melahirkan penyimpangan.
                const barisan = berSn ? Math.floor(item.sisa) : 1;
                // Yang dibuang floor tidak boleh lenyap tanpa jejak: ia dilaporkan di tingkat form.
                if (berSn && ! Number.isInteger(item.sisa)) pecahan.push({ item, barisan });
                for (let pcs = 0; pcs < barisan; pcs += 1) prefillRow(item, berSn ? 1 : item.sisa);
            });
            reportFractions(request, pecahan);
            if (items.querySelector('[data-row-asal="request"]') !== null && firstRowIsEmpty()) idleFirstRow(true);
            markDeviations();
        };
        // Melepas kunci lebih dulu, baru menyusun ulang daftar: penyaring request membaca
        // projectSelect, jadi selama Project masih pinjaman request lama daftar yang tersusun
        // lebih sempit daripada yang berhak dilihat operator.
        const resetRequest = () => { unlockProject(); renderRequests(); requestSelect.value = ''; dropPrefillRows(); markDeviations(); };
        destinationSelect.addEventListener('change', () => {
            // Ganti gudang tujuan me-reset Project hanya bila Mitra efektif berubah.
            if (effectiveMitra() !== currentMitra) { currentMitra = effectiveMitra(); renderProjects(); }
            resetRequest();
        });
        originSelect.addEventListener('change', () => {
            if (effectiveMitra() === currentMitra) { refreshTransferIdentityState(); return; }
            currentMitra = effectiveMitra(); renderProjects(); resetRequest();
        });
        projectSelect.addEventListener('change', () => {
            const previous = requestSelect.value;
            renderRequests();
            if (previous !== '' && requestSelect.querySelector('option[value="' + previous + '"]') !== null) { requestSelect.value = previous; return; }
            requestSelect.value = ''; dropPrefillRows(); markDeviations();
        });
        requestSelect.addEventListener('change', applyRequest);
        // Baris hasil old() sudah ada sebelum ada event apa pun; tanpa ini penolakan server
        // mengembalikan baris menyimpang tanpa penandaan maupun panduan catatannya.
        // Project yang dipulihkan dari old() juga harus menyaring dropdown Request sejak awal.
        // Request yang tidak lagi tersedia tetap dipertahankan sebagai pilihan oleh Blade agar
        // submit berikutnya bisa ditolak secara eksplisit, bukan diam-diam menjadi kiriman langsung.
        renderRequests(true);
        refreshTransferIdentityState();
        markDeviations();
    }
    initializeSubmitLoading(document);
}
