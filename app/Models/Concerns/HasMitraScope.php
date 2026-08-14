<?php

namespace App\Models\Concerns;

use App\Support\TenantDatabaseContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasMitraScope
{
    public static function bootHasMitraScope(): void
    {
        static::addGlobalScope('mitra', function (Builder $builder): void {
            $context = app(TenantDatabaseContext::class);

            if ($context->isThc()) {
                return;
            }

            if ($context->mitraId() === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->qualifyColumn('mitra_id'), $context->mitraId());
        });

        static::creating(function (Model $model): void {
            $context = app(TenantDatabaseContext::class);

            if (! $context->isThc() && $context->mitraId() !== null) {
                $model->setAttribute('mitra_id', $context->mitraId());
            }
        });
    }
}
