<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\MitraHargaJasa;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use App\Models\Project;
use App\Models\ProjectRabJasa;
use App\Models\User;
use App\Queries\ProjectCurveQuery;
use App\Services\ProjectPlanningService;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectCurveTest extends TestCase
{
    use RefreshDatabase;

    public function test_curve_separates_verified_and_pending_and_calculates_spi_threshold(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $rab] = $this->rabFixture($mitra);
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_project', 'report_project_progress');
        $thc = $this->userWithPermissions(null, 'read_project', 'verify_project_progress');
        $this->asThc(fn () => app(ProjectPlanningService::class)->savePlan($project, $thc, '2026-08-30', [
            ['date' => '2026-08-10', 'percent' => 50],
            ['date' => '2026-08-30', 'percent' => 100],
        ]));

        $this->actingAs($mitraUser)->post(route('projects.progress.store', $project), [
            'project_rab_jasa_id' => $rab->id,
            'actual_date' => '2026-08-10',
            'qty' => '40',
        ])->assertRedirect();
        $verifiedId = $this->asThc(fn (): int => (int) DB::table('project_progresses')->orderBy('id')->value('id'));
        $this->actingAs($thc)->patch(route('projects.progress.verify', [$project, $verifiedId]))->assertRedirect();
        $this->actingAs($mitraUser)->post(route('projects.progress.store', $project), [
            'project_rab_jasa_id' => $rab->id,
            'actual_date' => '2026-08-12',
            'qty' => '20',
        ])->assertRedirect();

        $curve = $this->asThc(fn (): array => app(ProjectCurveQuery::class)->calculate($project->fresh(), CarbonImmutable::parse('2026-08-15')));

        $this->assertSame(1000000.0, $curve['grand_total_rab_jasa']);
        $this->assertSame(40.0, $curve['verified_percent']);
        $this->assertSame(20.0, $curve['pending_percent']);
        $this->assertSame(50.0, $curve['plan_percent']);
        $this->assertSame(0.8, $curve['spi']);
        $this->assertSame('red', $curve['spi_status']);
        $this->assertSame('2026-08-10', $curve['verified_series'][0]['date']);
        $this->assertSame('2026-08-12', $curve['pending_series'][0]['date']);
    }

    public function test_revised_baseline_is_used_for_spi_while_original_remains_visible(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');
        $planning = app(ProjectPlanningService::class);

        $this->asThc(fn () => $planning->savePlan($project, $thc, '2026-08-20', [
            ['date' => '2026-08-10', 'percent' => 70],
            ['date' => '2026-08-20', 'percent' => 100],
        ]));
        $this->asThc(fn () => $planning->savePlan($project, $thc, '2026-08-30', [
            ['date' => '2026-08-15', 'percent' => 30],
            ['date' => '2026-08-30', 'percent' => 100],
        ]));

        $curve = $this->asThc(fn (): array => app(ProjectCurveQuery::class)->calculate($project->fresh(), CarbonImmutable::parse('2026-08-15')));

        $this->assertSame(30.0, $curve['plan_percent']);
        $this->assertSame('original', $curve['original_baseline']['kind']);
        $this->assertSame('revised', $curve['revised_baseline']['kind']);
    }

    public function test_overdue_project_without_revision_flattens_plan_and_extends_axis(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->asThc(fn () => app(ProjectPlanningService::class)->savePlan($project, $thc, '2026-08-10', [
            ['date' => '2026-08-01', 'percent' => 40],
            ['date' => '2026-08-10', 'percent' => 100],
        ]));

        $curve = $this->asThc(fn (): array => app(ProjectCurveQuery::class)->calculate($project->fresh(), CarbonImmutable::parse('2026-08-15')));

        $this->assertTrue($curve['overdue']);
        $this->assertTrue($curve['baseline_flat_after_toc']);
        $this->assertSame(100.0, $curve['plan_percent']);
        $this->assertSame('2026-08-15', $curve['x_axis_end']);
    }

    /** @return array{Project, ProjectRabJasa} */
    private function rabFixture(Mitra $mitra): array
    {
        $project = $this->projectFor($mitra);
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create([
            'kode' => 'JASA-'.fake()->unique()->numerify('###'),
            'nama' => 'Pekerjaan Kurva S',
            'aktif' => true,
        ]));
        $pks = $this->asThc(fn (): Pks => Pks::create([
            'mitra_id' => $mitra->id,
            'nomor' => 'PKS-'.fake()->unique()->numerify('###'),
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]));
        $price = $this->asThc(fn (): MitraHargaJasa => MitraHargaJasa::create([
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '10000.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-01-01',
        ]));
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');
        $this->actingAs($thc)->post(route('projects.rab-jasa.store', $project), [
            'harga_jasa_id' => $price->id,
            'qty' => '100',
        ])->assertRedirect();

        return [$project, ProjectRabJasa::query()->firstOrFail()];
    }

    private function projectFor(Mitra $mitra): Project
    {
        return $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Kurva S',
            'mitra_id' => $mitra->id,
        ]));
    }

    private function userWithPermissions(?int $mitraId, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::query()->firstOrCreate(
                ['kode' => $permission],
                ['nama' => $permission],
            )->id,
        )->all());

        return User::factory()->create(['mitra_id' => $mitraId, 'grup_id' => $group->id]);
    }

    private function asThc(\Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }
}
