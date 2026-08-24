<?php

namespace App\Support;

use App\Models\ProjectTimeline;

final class ProjectTimelineEventRenderer
{
    private const EVENT_LABELS = [
        'step_changed' => 'Step Project diperbarui',
    ];

    public function render(ProjectTimeline $entry): string
    {
        if ($entry->event_key === 'surat_jalan_deviation') {
            return $this->renderDeviation($entry->metadata);
        }

        return $this->formatSystemEventLabel($entry->event_key);
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
            : 'Penyimpangan Surat Jalan';
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
        if ($eventKey !== null && isset(self::EVENT_LABELS[$eventKey])) {
            return self::EVENT_LABELS[$eventKey];
        }

        return ucwords(str_replace('_', ' ', $eventKey ?? 'Aktivitas sistem'));
    }
}
