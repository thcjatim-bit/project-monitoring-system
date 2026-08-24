<?php

namespace App\Support;

use App\Models\ProjectTimeline;

final class ProjectTimelineEventRenderer
{
    private const EVENT_LABELS = [
        'surat_jalan_issued' => 'Surat Jalan diterbitkan',
        'surat_jalan_received' => 'Surat Jalan diterima',
        'surat_jalan_resolved' => 'Transit diselesaikan',
        'surat_jalan_returned' => 'Retur Surat Jalan diterbitkan',
        'surat_jalan_cancelled' => 'Surat Jalan dibatalkan',
        'step_changed' => 'Step Project diperbarui',
        'toc_changed' => 'TOC Project diperbarui',
        'baseline_proposal_submitted' => 'Usulan Baseline diajukan',
        'baseline_proposal_approved' => 'Usulan Baseline disetujui',
        'variation_order_created' => 'Variation Order dibuat',
        'variation_order_approved' => 'Variation Order disetujui',
        'progress_submitted' => 'Progres jasa diajukan',
        'progress_verified' => 'Progres jasa diverifikasi',
        'progress_rejected' => 'Progres jasa ditolak',
        'photo_uploaded' => 'Foto Pekerjaan ditambahkan',
        'rab_material_added' => 'RAB Material ditambahkan',
        'material_usage_submitted' => 'Pemakaian Material diajukan',
        'material_usage_cancelled' => 'Pemakaian Material dibatalkan',
        'material_usage_approved' => 'Pemakaian Material disetujui',
        'material_usage_rejected' => 'Pemakaian Material ditolak',
        'material_installed' => 'Material Terpasang dicatat',
        'material_rekon_opened' => 'Rekon Material dibuka',
        'material_rekon_updated' => 'Rekon Material diperbarui',
        'material_rekon_approved' => 'Rekon Material disetujui',
        'material_rekon_rejected' => 'Rekon Material ditolak',
    ];

    public function render(ProjectTimeline $entry): string
    {
        if ($entry->event_key === 'surat_jalan_deviation') {
            return $this->renderDeviation($entry->metadata);
        }

        return $this->formatSystemEventLabel($entry->event_key);
    }

    public static function labelFor(?string $eventKey): ?string
    {
        return $eventKey === null ? null : self::EVENT_LABELS[$eventKey] ?? null;
    }

    /** @param array<string, mixed>|null $metadata */
    private function renderDeviation(?array $metadata): string
    {
        $parts = [];

        foreach ([
            'material_asing' => 'Material di luar Request Material',
            'qty_melebihi' => 'Qty melebihi sisa',
        ] as $key => $label) {
            $names = $this->metadataNames($metadata[$key] ?? null);

            if ($names !== []) {
                $parts[] = $label.': '.implode(', ', $names);
            }
        }

        return $parts !== []
            ? implode('; ', $parts)
            : 'Baris Menyimpang pada Surat Jalan';
    }

    /** @return list<string> */
    private function metadataNames(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $names = [];
        foreach ($value as $name) {
            if (! is_scalar($name)) {
                continue;
            }

            $name = trim((string) $name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function formatSystemEventLabel(?string $eventKey): string
    {
        if ($eventKey === null || $eventKey === '') {
            return 'Aktivitas sistem';
        }

        return self::labelFor($eventKey) ?? $this->formatLegacyEventLabel($eventKey);
    }

    private function formatLegacyEventLabel(string $eventKey): string
    {
        return ucwords(str_replace('_', ' ', $eventKey));
    }
}
