<x-layouts.app>
    <h1>Manajemen Mitra</h1>
    <p>Daftar Mitra dan konteks admin-mitra pertama. Halaman ini read-only untuk sumber Command Center.</p>

    <ul>
        @forelse($mitras as $mitra)
            <li id="mitra-{{ $mitra->id }}">
                <strong>{{ $mitra->nama }}</strong> — {{ $mitra->kode }}
                <div>Dibuat {{ $mitra->created_at->format('d M Y H:i') }}</div>
                <div>Admin-mitra pertama:
                    @if ($mitra->adminMitraPertama)
                        {{ $mitra->adminMitraPertama->name }} · {{ $mitra->adminMitraPertama->email }}
                    @else
                        Belum tersedia
                    @endif
                </div>
            </li>
        @empty
            <li>Belum ada Mitra.</li>
        @endforelse
    </ul>
</x-layouts.app>
