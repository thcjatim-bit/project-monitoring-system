<?php

namespace App\Http\Controllers;

use App\Exceptions\ProjectCodeAlreadyIssuedException;
use App\Models\Mitra;
use App\Models\Project;
use App\Queries\ProjectControlRoomQuery;
use App\Services\ProjectCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('projects.index', [
            'projects' => Project::query()->latest()->get(),
            'user' => request()->user(),
        ]);
    }

    public function create(): View
    {
        return view('projects.create', [
            'user' => request()->user(),
            'mitras' => Mitra::query()->where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function show(Project $project, ProjectControlRoomQuery $query): View
    {
        try {
            return view('projects.show', $query->for($project, request()->query('as_of'), request()->user()));
        } catch (Throwable $exception) {
            report($exception);

            return view('projects.show', $query->errorState($project));
        }
    }

    public function store(Request $request, ProjectCodeGenerator $codes): RedirectResponse
    {
        $data = $request->validate([
            'id_project' => [
                'nullable',
                'string',
                'max:255',
                'unique:projects,id_project',
                function (string $attribute, mixed $value, \Closure $fail) use ($codes): void {
                    $value = trim((string) $value);
                    if ($value !== '' && str_starts_with(strtoupper($value), 'PRJ-') && ! $codes->isAutomaticCode($value)) {
                        $fail('ID Project baru dengan awalan PRJ- harus berformat PRJ-YYMM-NNNN.');
                    }
                },
            ],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => ['required', 'integer', Rule::exists('mitras', 'id')->where('aktif', true)],
        ]);
        $data['id_project'] = trim((string) ($data['id_project'] ?? '')) ?: null;

        try {
            if ($data['id_project'] === null) {
                $data['id_project'] = $codes->generate(now()->format('ym'));
            } else {
                $codes->reserveManual($data['id_project']);
            }

            $project = DB::transaction(fn (): Project => Project::create($data));
        } catch (ProjectCodeAlreadyIssuedException|\OverflowException $exception) {
            return back()->withErrors(['id_project' => $exception->getMessage()])->withInput();
        } catch (QueryException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw $exception;
            }

            return back()->withErrors(['id_project' => 'ID Project sudah digunakan.'])->withInput();
        }

        $destination = $request->user()->hasIzin('read_project') ? 'projects.index' : 'projects.create';

        return redirect()->route($destination)->with('status', "Project {$project->id_project} dibuat.");
    }

    public function update(Request $request, int $projectId): RedirectResponse
    {
        $project = Project::query()->findOrFail($projectId);
        $project->update($request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]));

        return redirect()->route('projects.index')->with('status', "Project {$project->id_project} diperbarui.");
    }

    public function destroy(int $projectId): RedirectResponse
    {
        $project = Project::query()->findOrFail($projectId);
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project dihapus.');
    }
}
