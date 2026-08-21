@props(['status'])

@php
    $label = [
        'diajukan' => 'Diajukan',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
        'terpenuhi_sebagian' => 'Sebagian dipenuhi',
        'selesai' => 'Dipenuhi',
        'ditutup' => 'Ditutup',
        'dibatalkan' => 'Dibatalkan',
    ][$status] ?? $status;
    $tone = match ($status) {
        'diajukan' => 'info',
        'disetujui', 'selesai' => 'done',
        'terpenuhi_sebagian' => 'warning',
        'ditolak', 'dibatalkan' => 'cancelled',
        default => 'neutral',
    };
@endphp

<x-ui.badge {{ $attributes }} :tone="$tone" :label="$label" />
