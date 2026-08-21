<?php

namespace App\Http\Controllers;

use App\Enums\MasterKind;
use App\Models\PekerjaanJasa;
use App\Models\Pop;
use App\Models\Unit;
use App\Services\CodedMasterLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    private const CONFIG = [
        'units' => [Unit::class, 'Unit', MasterKind::Unit],
        'pops' => [Pop::class, 'PoP', MasterKind::Pop],
        'pekerjaan-jasa' => [PekerjaanJasa::class, 'Pekerjaan Jasa', MasterKind::PekerjaanJasa],
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

    public function store(Request $request, string $entity, CodedMasterLifecycle $lifecycle): RedirectResponse
    {
        [, , $kind] = $this->config($entity);
        $lifecycle->create($request->user(), $kind, $request->validate($this->rules()));

        return back()->with('status', 'Data master berhasil dibuat.');
    }

    public function update(Request $request, string $entity, int $id, CodedMasterLifecycle $lifecycle): RedirectResponse
    {
        [$model] = $this->config($entity);
        $record = $model::query()->findOrFail($id);
        $lifecycle->update($request->user(), $record, $request->validate($this->rules()));

        return back()->with('status', 'Data master berhasil diperbarui.');
    }

    public function deactivate(Request $request, string $entity, int $id, CodedMasterLifecycle $lifecycle): RedirectResponse
    {
        [$model] = $this->config($entity);
        $lifecycle->deactivate($request->user(), $model::query()->findOrFail($id));

        return back()->with('status', 'Data master dinonaktifkan.');
    }

    /** @return array{0: class-string, 1: string, 2: MasterKind} */
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
}
