<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Project" title="Tambah Project" subtitle="Buat Project baru dan tetapkan tepat satu Mitra aktif.">
            <x-slot:actions><a class="ui-button ui-button--muted" href="{{ route('projects.index') }}">Kembali ke Project</a></x-slot:actions>
        </x-ui.page-header>

        <x-ui.panel>
            <x-form-errors />
            <form class="ui-form" method="POST" action="{{ route('projects.store') }}" data-submit-loading>
                @csrf
                <label>ID Project <span class="ui-help">Kosongkan untuk kode otomatis PRJ-YYMM-NNNN.</span><input name="id_project" value="{{ old('id_project') }}" maxlength="255"></label>
                <label>Nama<input name="nama" value="{{ old('nama') }}" required maxlength="255"></label>
                <label for="mitra_id">Mitra</label>
                <x-ui.searchable-select
                    name="mitra_id"
                    id="mitra_id"
                    :options="$mitraOptions"
                    :value="old('mitra_id')"
                    placeholder="Pilih Mitra aktif"
                    required
                />
                <button class="ui-button" type="submit">Simpan Project</button>
            </form>
        </x-ui.panel>
    </x-ui.page>
</x-layouts.app>
