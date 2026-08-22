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
 *
 * Penegakannya sengaja masih menyempit ke `ber_sn`: satu Serial Number adalah tepat satu pcs, dan
 * hanya jenis itulah yang sudah punya jaring pengaman di hilir. Meluaskannya ke `biasa` dan
 * `drum_kabel` beserta jalur qty lain dikerjakan terpisah (#133).
 */
final class WholeMaterialQty implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || (float) $value === floor((float) $value)) {
            return;
        }

        // Qty dan material_id adalah sepasang field pada baris yang sama; jenisnya dibaca dari pasangannya.
        $materialId = Arr::get($this->data, preg_replace('/\.qty$/', '.material_id', $attribute));

        if (DB::table('materials')->where('id', $materialId)->where('jenis', 'ber_sn')->exists()) {
            $fail('Qty material ber-Serial Number harus bilangan bulat: satu Serial Number adalah tepat satu pcs.');
        }
    }
}
