<x-layouts.app>
<main>
    <h1>Tambah Project</h1>

    <form method="POST" action="{{ route('projects.store') }}">
        @csrf
        @if ($errors->any())
            <div role="alert">
                <p>Project belum dapat disimpan. Periksa kembali isian berikut:</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <label>ID Project (kosongkan untuk otomatis)
            <input name="id_project" value="{{ old('id_project') }}" maxlength="255">
        </label>
        <label>Nama
            <input name="nama" value="{{ old('nama') }}" required maxlength="255">
        </label>
        <label for="mitra-search">Cari Mitra (kode atau nama)</label>
        <input id="mitra-search" type="search" data-mitra-search placeholder="Ketik kode atau nama Mitra" autocomplete="off">
        <label for="mitra_id">Mitra</label>
        <select id="mitra_id" name="mitra_id" required size="5">
            <option value="">Pilih Mitra aktif</option>
            @foreach ($mitras as $mitra)
                <option value="{{ $mitra->id }}" data-search-text="{{ $mitra->kode }} {{ $mitra->nama }}" @selected((string) old('mitra_id') === (string) $mitra->id)>
                    {{ $mitra->kode }} - {{ $mitra->nama }}
                </option>
            @endforeach
        </select>
        <button type="submit">Simpan</button>
    </form>
</main>
<script>
    (() => {
        const search = document.querySelector('[data-mitra-search]');
        const select = document.querySelector('#mitra_id');
        if (!search || !select) return;
        search.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            for (const option of select.options) {
                option.hidden = option.value !== '' && !option.dataset.searchText.toLowerCase().includes(query);
            }
        });
    })();
</script>
</x-layouts.app>
