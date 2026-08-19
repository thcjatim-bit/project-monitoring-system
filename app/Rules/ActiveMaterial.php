<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

final class ActiveMaterial implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = DB::table('materials')
            ->join('units', 'units.id', '=', 'materials.unit_id')
            ->where('materials.id', $value)
            ->where('materials.aktif', true)
            ->where('units.aktif', true)
            ->exists();

        if (! $valid) {
            $fail('Material harus aktif dan memiliki Unit/Satuan aktif.');
        }
    }
}
