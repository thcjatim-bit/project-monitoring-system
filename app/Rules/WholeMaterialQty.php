<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Qty material adalah bilangan bulat (ADR-0025). Aturannya menyeberang tabel — ia bergantung pada
 * `materials.jenis` — jadi ia hidup di lapisan aplikasi, bukan sebagai CHECK di database.
 */
final class WholeMaterialQty implements DataAwareRule, ValidationRule
{
    public function __construct(
        private readonly ?string $fixedJenis = null,
        private readonly string $sourceField = 'material_id',
        private readonly string $sourceTable = 'materials',
        private readonly string $sourceColumn = 'id',
    ) {}

    /** @var array<string, mixed> */
    private array $data = [];

    public static function forSuratJalanItem(): self
    {
        return new self(null, 'surat_jalan_item_id', 'surat_jalan_items');
    }

    public static function forProjectRekonItem(): self
    {
        return new self(null, 'id', 'project_rekon_items');
    }

    public static function forDrumCode(): self
    {
        return new self(null, 'drum_id', 'drums', 'drum_id');
    }

    public static function forJenis(?string $jenis): self
    {
        return new self($jenis);
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $jenis = $this->fixedJenis ?? $this->resolveJenis($attribute);
        if ($jenis === null) {
            return;
        }

        $whole = $this->isWhole($value);
        $positiveDrumQty = $jenis === 'drum_kabel' && (float) $value !== 0.0 && abs((float) $value) < 1;

        if (! $whole || $positiveDrumQty) {
            $fail($this->message($jenis));
        }
    }

    private function resolveJenis(string $attribute): ?string
    {
        $row = str_contains($attribute, '.') ? strrchr($attribute, '.') : false;
        $rowPath = $row === false ? '' : substr($attribute, 0, -strlen($row));
        $sourcePath = $rowPath === '' ? $this->sourceField : $rowPath.'.'.$this->sourceField;
        $sourceValue = Arr::get($this->data, $sourcePath);

        if ($sourceValue === null || $sourceValue === '') {
            return null;
        }

        if ($this->sourceTable === 'materials') {
            return DB::table('materials')->where('id', $sourceValue)->value('jenis');
        }

        return DB::table($this->sourceTable)
            ->join('materials', $this->sourceTable.'.material_id', '=', 'materials.id')
            ->where($this->sourceTable.'.'.$this->sourceColumn, $sourceValue)
            ->value('materials.jenis');
    }

    private function message(string $jenis): string
    {
        return match ($jenis) {
            'ber_sn' => 'Qty material ber-Serial Number harus bilangan bulat: satu Serial Number adalah tepat satu pcs.',
            'drum_kabel' => 'Qty material kabel harus bilangan bulat dengan minimum 1 meter per transaksi.',
            default => 'Qty material biasa harus bilangan bulat: satu unit adalah satu unit utuh.',
        };
    }

    private function isWhole(mixed $value): bool
    {
        $text = trim((string) $value);
        if (preg_match('/^[+-]?\d+$/', $text) === 1) {
            return true;
        }

        if (preg_match('/^[+-]?\d+\.(\d+)$/', $text, $matches) === 1) {
            return str_replace('0', '', $matches[1]) === '';
        }

        return is_float($value) && is_finite($value) && floor($value) === $value;
    }
}
