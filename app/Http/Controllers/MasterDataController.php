<?php

namespace App\Http\Controllers;

use App\Models\PekerjaanJasa;
use App\Models\Pop;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, string $entity): RedirectResponse
    {
        [$model] = $this->config($entity);
        $data = $request->validate($this->rules($model));
        $model::create($data);

        return back()->with('status', 'Data master berhasil dibuat.');
    }

    public function update(Request $request, string $entity, int $id): RedirectResponse
    {
        [$model] = $this->config($entity);
        $record = $model::query()->findOrFail($id);
        $data = $request->validate($this->rules($model, $record->id));
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

    private function rules(string $model, ?int $ignoreId = null): array
    {
        $table = (new $model)->getTable();

        return [
            'kode' => ['required', 'string', 'max:255', Rule::unique($table, 'kode')->ignore($ignoreId)],
            'nama' => ['required', 'string', 'max:255'],
        ];
    }
}
