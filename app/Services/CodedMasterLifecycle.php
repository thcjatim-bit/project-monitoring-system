<?php

namespace App\Services;

use App\Enums\MasterKind;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\PekerjaanJasa;
use App\Models\Pop;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CodedMasterLifecycle
{
    public function __construct(private MasterCodeGenerator $codes) {}

    public function create(User $actor, MasterKind $kind, array $attributes): Model
    {
        $this->assertAuthorized($actor, $kind);

        try {
            return DB::transaction(function () use ($kind, $attributes): Model {
                $data = $this->validated($kind, $attributes);
                $data['kode'] = $this->codes->normalize($data['kode'] ?? null);
                $this->assertManualCodeIsAllowed($kind, $data['kode']);
                $this->assertCodeIsUnique($kind, $data['kode']);
                $data['kode'] ??= $this->codes->generate($kind->value, CarbonImmutable::now('Asia/Jakarta'));
                $data['aktif'] = true;
                $model = $this->model($kind);

                return $model::query()->create($data);
            });
        } catch (QueryException $exception) {
            $this->translateUniqueViolation($exception);
        }
    }

    public function update(User $actor, Model $record, array $attributes): Model
    {
        $kind = $this->kindFor($record);
        $this->assertAuthorized($actor, $kind);

        return DB::transaction(function () use ($kind, $record, $attributes): Model {
            $model = $this->model($kind);
            $locked = $model::query()->lockForUpdate()->findOrFail($record->getKey());
            $data = $this->validated($kind, $attributes, $locked);
            $data['kode'] = $this->codes->normalize($data['kode'] ?? null);
            if ($data['kode'] === null) {
                throw ValidationException::withMessages(['kode' => 'Kode wajib diisi.']);
            }

            $original = $this->codes->normalize((string) $locked->getAttribute('kode'));
            if ($data['kode'] !== $original && $original !== null && $this->codes->wasIssued($kind->value, $original)) {
                throw ValidationException::withMessages(['kode' => 'Kode otomatis tidak dapat diubah setelah diterbitkan.']);
            }
            $this->assertManualCodeIsAllowed($kind, $data['kode'], $original);
            $this->assertCodeIsUnique($kind, $data['kode'], (int) $locked->getKey());

            try {
                $locked->update($data);
            } catch (QueryException $exception) {
                $this->translateUniqueViolation($exception);
            }

            return $locked->fresh();
        });
    }

    public function deactivate(User $actor, Model $record): void
    {
        $kind = $this->kindFor($record);
        $this->assertAuthorized($actor, $kind);

        DB::transaction(function () use ($kind, $record): void {
            $model = $this->model($kind);
            $model::query()->lockForUpdate()->findOrFail($record->getKey())->update(['aktif' => false]);
        });
    }

    private function validated(MasterKind $kind, array $attributes, ?Model $record = null): array
    {
        $rules = [
            'kode' => ['nullable', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
        ];
        if ($kind === MasterKind::Material) {
            $rules += [
                'unit_id' => ['required', 'integer'],
                'jenis' => ['required', Rule::in(['biasa', 'ber_sn', 'drum_kabel'])],
                'ambang_minimum' => ['nullable', 'numeric', 'min:0'],
            ];
        } elseif ($kind === MasterKind::Warehouse) {
            $rules['mitra_id'] = ['nullable', 'integer'];
        }

        $data = Validator::make($attributes, $rules)->validate();

        if ($kind === MasterKind::Material) {
            $unitId = (int) $data['unit_id'];
            $mayKeepHistoricalUnit = $record instanceof Material && (int) $record->unit_id === $unitId;
            $unit = Unit::query()->lockForUpdate()->find($unitId);
            if ($unit === null || (! $mayKeepHistoricalUnit && ! $unit->aktif)) {
                throw ValidationException::withMessages(['unit_id' => 'Unit aktif tidak ditemukan.']);
            }
        }
        if ($kind === MasterKind::Warehouse && isset($data['mitra_id'])) {
            $mitra = Mitra::query()->lockForUpdate()->find($data['mitra_id']);
            if ($mitra === null || ! $mitra->aktif) {
                throw ValidationException::withMessages(['mitra_id' => 'Mitra aktif tidak ditemukan.']);
            }
        }

        return $data;
    }

    private function assertAuthorized(User $actor, MasterKind $kind): void
    {
        $permission = match ($kind) {
            MasterKind::Material => 'manage_materials',
            MasterKind::Warehouse => 'manage_warehouses',
            default => 'manage_master_data',
        };

        abort_unless($actor->mitra_id === null && $actor->hasIzin($permission), 403);
    }

    private function assertManualCodeIsAllowed(MasterKind $kind, ?string $code, ?string $original = null): void
    {
        if ($code !== null && $code !== $original && $this->codes->isAutomaticCode($kind->value, $code)) {
            throw ValidationException::withMessages(['kode' => 'Kode dengan pola otomatis hanya boleh diterbitkan generator.']);
        }
    }

    private function assertCodeIsUnique(MasterKind $kind, ?string $code, ?int $ignoreId = null): void
    {
        if ($code === null) {
            return;
        }

        $model = $this->model($kind);
        $query = $model::query()->where('kode', $code);
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['kode' => 'Kode sudah digunakan.']);
        }
    }

    /** @return class-string<Model> */
    private function model(MasterKind $kind): string
    {
        return $this->registry()[$kind->value];
    }

    private function kindFor(Model $record): MasterKind
    {
        $kind = array_search($record::class, $this->registry(), true);
        if ($kind === false) {
            throw new \InvalidArgumentException('Record bukan master berkode yang didukung.');
        }

        return MasterKind::from($kind);
    }

    /** @return array<string, class-string<Model>> */
    private function registry(): array
    {
        return [
            MasterKind::Material->value => Material::class,
            MasterKind::Unit->value => Unit::class,
            MasterKind::Pop->value => Pop::class,
            MasterKind::PekerjaanJasa->value => PekerjaanJasa::class,
            MasterKind::Warehouse->value => Warehouse::class,
        ];
    }

    private function translateUniqueViolation(QueryException $exception): never
    {
        if (str_contains(strtolower($exception->getMessage()), 'unique')) {
            throw ValidationException::withMessages(['kode' => 'Kode sudah digunakan.']);
        }

        throw $exception;
    }
}
