<x-layouts.app>
    <main>
        <h1>Akses Mitra belum tersedia</h1>
        <p>Akun Anda sudah aktif, tetapi belum memiliki izin untuk membuka ringkasan Mitra. Hubungi admin THC untuk melanjutkan.</p>
        <p><strong>Data Anda tetap aman.</strong> Tidak ada data THC yang ditampilkan pada halaman ini.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </main>
</x-layouts.app>
