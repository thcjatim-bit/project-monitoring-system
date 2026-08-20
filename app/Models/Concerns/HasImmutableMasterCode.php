<?php

namespace App\Models\Concerns;

use App\Services\MasterCodeGenerator;
use Illuminate\Database\Eloquent\Model;

trait HasImmutableMasterCode
{
    protected static function bootHasImmutableMasterCode(): void
    {
        static::creating(function (Model $model): void {
            $generator = app(MasterCodeGenerator::class);
            $code = (string) $model->getAttribute('kode');

            if ($code !== ''
                && $generator->isAutomaticCode($model->masterCodeEntity(), $code)
                && ! $generator->wasIssued($model->masterCodeEntity(), $code)) {
                throw new \LogicException('Kode dengan pola otomatis hanya boleh diterbitkan generator.');
            }
        });

        static::updating(function (Model $model): void {
            if (! $model->isDirty('kode')) {
                return;
            }

            $generator = app(MasterCodeGenerator::class);
            $entity = $model->masterCodeEntity();
            $original = $generator->normalize((string) $model->getRawOriginal('kode'));
            $next = $generator->normalize((string) $model->getAttribute('kode'));
            $model->setAttribute('kode', $next);

            if ($original !== null && $generator->wasIssued($entity, $original)) {
                throw new \LogicException('Kode otomatis tidak dapat diubah setelah diterbitkan.');
            }
            if ($next !== null && $next !== $original && $generator->isAutomaticCode($entity, $next)) {
                throw new \LogicException('Kode dengan pola otomatis hanya boleh diterbitkan generator.');
            }
        });
    }

    abstract public function masterCodeEntity(): string;
}
