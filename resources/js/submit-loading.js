/**
 * Daur hidup tombol submit: mengunci tombol saat form dikirim, dan memulihkannya hanya bila
 * halaman ini tidak berpindah. Form yang membuka konteks lain (`_blank`, target bernama) tidak
 * pernah melenyapkan halaman ini, jadi tombolnya harus pulih sendiri; form yang memindahkan
 * halaman ini sengaja dibiarkan terkunci karena memulihkannya hanya membuka celah kirim ganda.
 *
 * Atribut `[data-submit-loading]` dipakai belasan view, tapi penangannya sampai sekarang hanya
 * hidup di halaman Warehouse. Modul ini menjaga keadaan itu apa adanya: ia dipanggil dari modul
 * halaman Warehouse, bukan dari app.js, sehingga view lain tetap tidak berpenangan seperti
 * sebelumnya. Menyalakannya untuk seluruh aplikasi adalah keputusan tersendiri, bukan refactor.
 */
export function initializeSubmitLoading(document = globalThis.document) {
    const window = document.defaultView ?? globalThis;
    const NAVIGATES_THIS_PAGE = ['', '_self', '_parent', '_top'];

    document.querySelectorAll('[data-submit-loading]').forEach((form) => form.addEventListener('submit', () => {
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        buttons.forEach((button) => { button.disabled = true; button.dataset.originalLabel = button.textContent; button.textContent = 'Ops…'; });
        // Halaman ini akan berpindah, jadi tombol sengaja dibiarkan terkunci sampai halaman lenyap.
        if (NAVIGATES_THIS_PAGE.includes(form.target.toLowerCase())) return;
        window.setTimeout(() => buttons.forEach((button) => { button.disabled = false; button.textContent = button.dataset.originalLabel ?? button.textContent; }), RESTORE_SUBMIT_AFTER_MS);
    }));
}

/**
 * Tenggat pemulihan tombol submit. Diekspor karena test menunggunya; angka yang sama tidak
 * boleh hidup di dua tempat.
 */
export const RESTORE_SUBMIT_AFTER_MS = 1000;
