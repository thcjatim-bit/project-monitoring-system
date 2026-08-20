<?php

namespace App\Http\Controllers;

use App\Models\PekerjaanJasa;
use App\Models\Pop;
use App\Models\Unit;
use App\Services\MasterCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    private const CONFIG = [
        'units' => [Unit::class, 'Unit'],
        'pops' => [Pop::class, 'PoP'],
        'pekerjaan-jasa' => [PekerjaanJasa::class, 'Pekerjaan Jasa'],
    ];

    public function index(string $entity): View
    {
        [$model, $label] = $this->config($entity);

        return view('admin/master-data', [
            'entity' => $entity,
            'label' => $label,
            'records' => $model::query()->latest()->get(),
        ]);
    }

    public function store(Request $request, string $entity, MasterCodeGenerator $codes): RedirectResponse
    {
        [$model] = $this->config($entity);
        $data = $request->validate($this->rules($model));
        $data['kode'] = $codes->normalize($data['kode'] ?? null);
        $this->assertManualCodeIsAllowed($codes, $entity, $data['kode']);
        $this->assertCodeIsUnique($model, $data['kode']);

        DB::transaction(function () use ($model, $entity, $data, $codes): void {
            $data['kode'] ??= $codes->generate($this->codeEntity($entity), CarbonImmutable::now('Asia/Jakarta'));
            $model::create($data);
        });

        return back()->with('status', 'Data master berhasil dibuat.');
    }

    public function update(Request $request, string $entity, int $id, MasterCodeGenerator $codes): RedirectResponse
    {
        [$model] = $this->config($entity);
        $record = $model::query()->findOrFail($id);
        $data = $request->validate($this->rules());
        $data['kode'] = $codes->normalize($data['kode']);
        $original = $codes->normalize($record->kode);
        if ($data['kode'] !== $original && $original !== null && $codes->wasIssued($this->codeEntity($entity), $original)) {
            throw ValidationException::withMessages(['kode' => 'Kode otomatis tidak dapat diubah setelah diterbitkan.']);
        }
        $this->assertManualCodeIsAllowed($codes, $entity, $data['kode'], $original);
        $this->assertCodeIsUnique($model, $data['kode'], $record->id);
        $record->update($data);

        return back()->with('status', 'Data master berhasil diperbarui.');
    }

    public function deactivate(string $entity, int $id): RedirectResponse
    {
        [$model] = $this->config($entity);
        $model::query()->findOrFail($id)->update(['aktif' => false]);

        return back()->with('status', 'Data master dinonaktifkan.');
    }

    /** @return array{0: class-string, 1: string} */
    private function config(string $entity): array
    {
        abort_unless(isset(self::CONFIG[$entity]), 404);

        return self::CONFIG[$entity];
    }

    private function rules(): array
    {
        return [
            'kode' => ['nullable', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
        ];
    }

    private function codeEntity(string $entity): string
    {
        return match ($entity) {
            'units' => 'unit',
            'pops' => 'pop',
            'pekerjaan-jasa' => 'pekerjaan_jasa',
            default => throw new \InvalidArgumentException("Entitas master tidak dikenal: {$entity}."),
        };
    }

    private function assertManualCodeIsAllowed(MasterCodeGenerator $codes, string $entity, ?string $code, ?string $original = null): void
    {
        if ($code !== null && $code !== $original && $codes->isAutomaticCode($this->codeEntity($entity), $code)) {
            throw ValidationException::withMessages(['kode' => 'Kode dengan pola otomatis hanya boleh diterbitkan generator.']);
        }
    }

    private function assertCodeIsUnique(string $model, ?string $code, ?int $ignoreId = null): void
    {
        if ($code === null) {
            return;
        }

        $query = $model::query()->where('kode', $code);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['kode' => 'Kode sudah digunakan.']);
        }
    }
}
