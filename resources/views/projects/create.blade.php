<x-layouts.app>
<main>
    <h1>Tambah Project</h1>

    <form method="POST" action="{{ route('projects.store') }}">
        @csrf
        <label>ID Project <input name="id_project" required></label>
        <label>Nama <input name="nama" required></label>
        @if ($user->mitra_id === null)
            <label>ID Mitra <input name="mitra_id" type="number" required></label>
        @endif
        <button type="submit">Simpan</button>
    </form>
</main>
</x-layouts.app>
