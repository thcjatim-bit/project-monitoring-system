<x-layouts.app>
    <h1>{{ !empty($rotation) ? 'API Key baru hasil rotasi' : 'API Key dibuat' }}</h1>
    <p>Salin nilai ini sekarang. Nilai plaintext tidak akan ditampilkan lagi.</p>
    <p><code data-api-key-plaintext>{{ $plaintext }}</code></p>
    <p>{{ $apiKey->name }} — {{ $apiKey->mitra?->kode ?? 'THC' }} — berlaku sampai {{ $apiKey->expires_at?->toDateString() }}</p>
    <a href="{{ route('admin.api-keys.index') }}">Kembali ke manajemen API Key</a>
</x-layouts.app>
