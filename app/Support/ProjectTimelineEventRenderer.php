<?php

namespace App\Support;

use App\Models\ProjectTimeline;

final class ProjectTimelineEventRenderer
{
    public function render(ProjectTimeline $entry): string
    {
        if ($entry->event_key === 'surat_jalan_deviation') {
            return $this->renderDeviation($entry->metadata);
        }

        return $this->rawEventLabel($entry->event_key);
    }

    /** @param array<string, mixed>|null $metadata */
    private function renderDeviation(?array $metadata): string
    {
        $parts = [];

        foreach ([
            'material_asing' => 'Material di luar request',
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

    private function rawEventLabel(?string $eventKey): string
    {
        if ($eventKey === null || $eventKey === '') {
            return 'Aktivitas sistem';
        }

        return ucwords(str_replace('_', ' ', $eventKey));
    }
}
