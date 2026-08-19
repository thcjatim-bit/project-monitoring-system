<x-layouts.app>
    <main>
        <h1>Akses belum tersedia</h1>
        <p>Akun Anda belum memiliki izin menu yang dapat dibuka. Hubungi admin THC untuk melanjutkan.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </main>
</x-layouts.app>
