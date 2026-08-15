<x-layouts.app>
    <h1>Material dalam Transit</h1>
    <p>Material di bawah masih berada dalam perjalanan dan belum dihitung sebagai stok Warehouse tujuan.</p>
    <table>
        <thead><tr><th>Surat Jalan</th><th>Material</th><th>Warehouse asal</th><th>Qty Transit</th></tr></thead>
        <tbody>
        @forelse($stocks as $stock)
            <tr>
                <td><a href="{{ route('warehouse.transfers.print', $stock->lokasi_id) }}">SJ #{{ $stock->lokasi_id }}</a></td>
                <td>{{ $stock->material->kode }} — {{ $stock->material->nama }}</td>
                <td>{{ $stock->warehouse->kode }} — {{ $stock->warehouse->nama }}</td>
                <td>{{ $stock->qty }} {{ $stock->material->unit?->nama }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Tidak ada Material dalam Transit.</td></tr>
        @endforelse
        </tbody>
    </table>
</x-layouts.app>
