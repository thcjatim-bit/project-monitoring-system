<x-layouts.app>
    <h1>API Key</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    <p>Plaintext hanya ditampilkan sekali saat key dibuat atau dirotasi.</p>
    <form method="POST" action="{{ route('admin.api-keys.store') }}">
        @csrf
        <input name="name" placeholder="Nama konsumen" required>
        <select name="mitra_id">
            <option value="">Cakupan THC</option>
            @foreach ($mitras as $mitra)
                <option value="{{ $mitra->id }}">{{ $mitra->kode }} — {{ $mitra->nama }}</option>
            @endforeach
        </select>
        <input name="expires_in_days" type="number" min="1" max="365" value="90" required>
        <button>Buat API Key</button>
    </form>
    <h2>Key aktif dan historis</h2>
    <ul>
        @foreach ($apiKeys as $apiKey)
            <li>
                <strong>{{ $apiKey->name }}</strong>
                — {{ $apiKey->mitra?->kode ?? 'THC' }}
                — berlaku sampai {{ $apiKey->expires_at?->toDateString() }}
                — {{ $apiKey->revoked_at ? 'Dicabut' : 'Aktif' }}
                @if (! $apiKey->revoked_at && $apiKey->expires_at?->isFuture())
                    <form method="POST" action="{{ route('admin.api-keys.rotate', $apiKey) }}" style="display:inline">
                        @csrf
                        <button>Rotasi</button>
                    </form>
                    <form method="POST" action="{{ route('admin.api-keys.revoke', $apiKey) }}" style="display:inline">
                        @csrf @method('PATCH')
                        <button>Cabut</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
</x-layouts.app>
