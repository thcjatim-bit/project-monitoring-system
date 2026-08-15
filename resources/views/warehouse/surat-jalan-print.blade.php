@php
    $qrCode = (new \chillerlan\QRCode\QRCode)->render(route('warehouse.transfers.print', $suratJalan));
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $suratJalan->nomor }}</title>
    <style>
        @page { size: A4; margin: 16mm; }
        body { color: #111; font-family: Arial, sans-serif; font-size: 12px; margin: 0; }
        h1 { font-size: 22px; margin: 0; text-align: center; }
        .muted { color: #555; }
        .header { align-items: flex-start; display: flex; justify-content: space-between; margin-bottom: 18px; }
        .identity { text-align: right; }
        .identity strong { display: block; font-size: 16px; }
        .route { border: 1px solid #111; display: grid; grid-template-columns: 1fr 36px 1fr; margin: 14px 0; padding: 12px; }
        .route > div { padding: 0 10px; }
        .route .arrow { align-items: center; display: flex; justify-content: center; padding: 0; }
        table { border-collapse: collapse; margin-top: 14px; width: 100%; }
        th, td { border: 1px solid #111; padding: 7px; text-align: left; }
        th { background: #eee; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 48px; }
        .signature { border-top: 1px solid #111; min-height: 54px; padding-top: 6px; }
        .qr { margin-top: 18px; text-align: right; }
        .qr img { height: 115px; width: 115px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>SURAT JALAN</h1>
            <div class="muted">Dokumen perpindahan Material</div>
        </div>
        <div class="identity">
            <strong>{{ $suratJalan->nomor }}</strong>
            <span>Tanggal: {{ $suratJalan->tanggal->format('d-m-Y') }}</span><br>
            <span>Status: {{ ucfirst($suratJalan->status) }}</span>
        </div>
    </div>

    <div class="route">
        <div><strong>Dari Warehouse</strong><br>{{ $suratJalan->origin->kode }} — {{ $suratJalan->origin->nama }}</div>
        <div class="arrow">→</div>
        <div><strong>Ke Warehouse</strong><br>{{ $suratJalan->destination->kode }} — {{ $suratJalan->destination->nama }}</div>
    </div>

    <div><strong>Mitra:</strong> {{ $suratJalan->mitra?->nama ?? 'THC' }}</div>
    <div><strong>Pengirim:</strong> {{ $suratJalan->pengirim }} &nbsp; <strong>Sopir:</strong> {{ $suratJalan->sopir ?? '-' }} &nbsp; <strong>Plat:</strong> {{ $suratJalan->plat_nomor ?? '-' }}</div>

    <table>
        <thead>
        <tr><th>No.</th><th>Material</th><th>Identitas</th><th>Qty</th><th>Unit</th></tr>
        </thead>
        <tbody>
        @foreach($suratJalan->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->material->kode }} — {{ $item->material->nama }}</td>
                <td>{{ $item->serialNumber?->serial_number ?? $item->drum?->drum_id ?? '-' }}</td>
                <td>{{ $item->qty }}</td>
                <td>{{ $item->material->unit?->nama ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature">Pengirim<br><br>{{ $suratJalan->pengirim }}</div>
        <div class="signature">Penerima<br><br>{{ $suratJalan->receiver?->name ?? 'Tanda tangan penerima' }}</div>
    </div>

    <div class="qr"><img src="{{ $qrCode }}" alt="QR {{ $suratJalan->nomor }}"><br><span class="muted">Scan untuk membuka Surat Jalan</span></div>
    <button class="no-print" onclick="window.print()">Cetak</button>
</body>
</html>
