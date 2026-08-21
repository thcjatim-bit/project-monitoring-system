<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\MitraHargaJasa;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ProjectBaselineProposal;
use App\Models\ProjectRabJasa;
use App\Models\ProjectTimeline;
use App\Models\ProjectVariationOrder;
use App\Models\User;
use App\Services\ProjectPlanningService;
use App\Support\TenantDatabaseContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_service_rechecks_permission_before_mutating_a_project(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $actor = User::factory()->create(['mitra_id' => $mitra->id]);

        $this->expectException(HttpException::class);

        app(ProjectPlanningService::class)->createVariationOrder($project, $actor, 'Tidak berwenang', [[
            'quantity_delta' => '1',
            'harga_jasa_id' => 1,
        ]]);
    }

    public function test_thc_adds_rab_jasa_with_frozen_approved_mitra_price(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create([
            'kode' => 'JASA-001',
            'nama' => 'Penarikan Kabel',
            'aktif' => true,
        ]));
        $pks = $this->asThc(fn (): Pks => Pks::create([
            'mitra_id' => $mitra->id,
            'nomor' => 'PKS-001',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]));
        $price = $this->asThc(fn (): MitraHargaJasa => MitraHargaJasa::create([
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '125000.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-01-01',
        ]));
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->actingAs($thc)
            ->post(route('projects.rab-jasa.store', $project), [
                'harga_jasa_id' => $price->id,
                'qty' => '10',
            ])
            ->assertRedirect(route('projects.show', $project));

        $rab = ProjectRabJasa::query()->firstOrFail();
        $this->assertSame('125000.00', $rab->harga_satuan);
        $this->assertSame('1250000.00', $rab->total_nilai);

        $this->asThc(fn () => $price->update(['harga' => '200000.00']));

        $this->asThc(function () use ($rab): void {
            $this->assertDatabaseHas('project_rab_jasas', [
                'id' => $rab->id,
                'harga_satuan' => '125000.00',
                'total_nilai' => '1250000.00',
            ]);
        });
    }

    public function test_direct_rab_add_is_rejected_after_baseline_is_published(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $job, $price] = $this->rabFixtureWithoutRab($mitra);
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');
        $planning = app(ProjectPlanningService::class);

        $this->asThc(fn () => $planning->savePlan($project, $thc, '2026-09-30', [
            ['date' => '2026-09-30', 'percent' => '100'],
        ]));
        $this->asThc(fn () => $planning->savePlan($project, $thc, '2026-10-15', [
            ['date' => '2026-10-15', 'percent' => '100'],
        ]));
        $timelineCount = $this->asThc(fn (): int => ProjectTimeline::query()->where('project_id', $project->id)->count());

        try {
            $this->asThc(fn () => $planning->addRabJasa($project, $thc, $price->id, '10'));
            $this->fail('Direct RAB add should be rejected after a Baseline is published.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['RAB Jasa sudah dibekukan. Lakukan perubahan melalui Variation Order.'],
                $exception->errors()['rab_jasa'],
            );
        }

        $this->assertDatabaseCount('project_rab_jasas', 0);
        $this->asThc(function () use ($project, $timelineCount): void {
            $this->assertSame(2, ProjectBaseline::query()->where('project_id', $project->id)->count());
            $this->assertSame($timelineCount, ProjectTimeline::query()->where('project_id', $project->id)->count());
        });
    }

    public function test_rab_route_is_rejected_after_baseline_is_published(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $job, $price] = $this->rabFixtureWithoutRab($mitra);
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->asThc(fn () => app(ProjectPlanningService::class)->savePlan($project, $thc, '2026-09-30', [
            ['date' => '2026-09-30', 'percent' => '100'],
        ]));

        $this->actingAs($thc)
            ->post(route('projects.rab-jasa.store', $project), [
                'harga_jasa_id' => $price->id,
                'qty' => '10',
            ])
            ->assertSessionHasErrors('rab_jasa');

        $this->assertDatabaseCount('project_rab_jasas', 0);
    }

    public function test_pending_baseline_proposal_does_not_freeze_initial_rab(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $job, $price] = $this->rabFixtureWithoutRab($mitra);
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_project', 'manage_mitra_project');
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->asTenant($mitra->id, fn () => app(ProjectPlanningService::class)->savePlan(
            $project,
            $mitraUser,
            '2026-09-30',
            [['date' => '2026-09-30', 'percent' => '100']],
        ));

        $rab = $this->asThc(
            fn () => app(ProjectPlanningService::class)->addRabJasa($project, $thc, $price->id, '10'),
        );

        $this->assertSame($project->id, $rab->project_id);
    }

    public function test_baseline_proposal_cannot_be_approved_twice(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $mitraUser = $this->userWithPermissions($mitra->id, 'manage_mitra_project');
        $thc = $this->userWithPermissions(null, 'manage_project_plan');
        $planning = app(ProjectPlanningService::class);
        $proposal = $this->asTenant($mitra->id, fn (): ProjectBaselineProposal => $planning->savePlan(
            $project,
            $mitraUser,
            '2026-09-30',
            [['date' => '2026-09-30', 'percent' => '100']],
        ));
        $this->asThc(fn () => $planning->approveBaselineProposal($project, $proposal, $thc));

        try {
            $this->asThc(fn () => $planning->approveBaselineProposal($project, $proposal, $thc));
            $this->fail('Proposal Baseline yang sudah disetujui tidak boleh disetujui ulang.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->asThc(fn () => $this->assertSame(
            1,
            ProjectBaseline::query()->where('project_id', $project->id)->count(),
        ));
    }

    public function test_baseline_proposal_cannot_be_approved_for_a_different_tenants_project(): void
    {
        $firstMitra = Mitra::factory()->create();
        $secondMitra = Mitra::factory()->create();
        $firstProject = $this->projectFor($firstMitra);
        $secondProject = $this->projectFor($secondMitra);
        $secondMitraUser = $this->userWithPermissions($secondMitra->id, 'manage_mitra_project');
        $thc = $this->userWithPermissions(null, 'manage_project_plan');
        $planning = app(ProjectPlanningService::class);
        $proposal = $this->asTenant($secondMitra->id, fn (): ProjectBaselineProposal => $planning->savePlan(
            $secondProject,
            $secondMitraUser,
            '2026-09-30',
            [['date' => '2026-09-30', 'percent' => '100']],
        ));

        $rejected = false;
        try {
            $this->asThc(fn () => $planning->approveBaselineProposal($firstProject, $proposal, $thc));
            $this->fail('Proposal lintas Project dan tenant tidak boleh disetujui.');
        } catch (ModelNotFoundException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->asThc(function () use ($firstProject, $proposal): void {
            $this->assertSame(0, ProjectBaseline::query()->where('project_id', $firstProject->id)->count());
            $this->assertSame('diajukan', $proposal->fresh()->status);
        });
    }

    public function test_rab_rejects_an_approved_price_after_its_pks_has_ended(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create([
            'kode' => 'JASA-EXPIRED-PKS',
            'nama' => 'Pekerjaan PKS Berakhir',
            'aktif' => true,
        ]));
        $pks = $this->asThc(fn (): Pks => Pks::create([
            'mitra_id' => $mitra->id,
            'nomor' => 'PKS-EXPIRED',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-08-01',
        ]));
        $price = $this->asThc(fn (): MitraHargaJasa => MitraHargaJasa::create([
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '125000.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-01-01',
        ]));
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->actingAs($thc)
            ->post(route('projects.rab-jasa.store', $project), [
                'harga_jasa_id' => $price->id,
                'qty' => '10',
            ])
            ->assertSessionHasErrors('harga_jasa_id');

        $this->assertDatabaseCount('project_rab_jasas', 0);
    }

    public function test_changing_toc_preserves_original_and_creates_revised_baseline(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->actingAs($thc)
            ->put(route('projects.plan.update', $project), [
                'toc' => '2026-09-30',
                'plan' => [
                    ['date' => '2026-09-01', 'percent' => '25'],
                    ['date' => '2026-09-15', 'percent' => '70'],
                    ['date' => '2026-09-30', 'percent' => '100'],
                ],
            ])
            ->assertRedirect(route('projects.show', $project));

        $original = ProjectBaseline::query()->where('kind', 'original')->firstOrFail();
        $originalToc = $original->toc->toDateString();

        $this->actingAs($thc)
            ->put(route('projects.plan.update', $project), [
                'toc' => '2026-10-15',
                'plan' => [
                    ['date' => '2026-09-01', 'percent' => '20'],
                    ['date' => '2026-10-01', 'percent' => '80'],
                    ['date' => '2026-10-15', 'percent' => '100'],
                ],
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame(1, ProjectBaseline::query()->where('kind', 'original')->count());
        $this->assertSame(1, ProjectBaseline::query()->where('kind', 'revised')->count());
        $this->assertSame($originalToc, $original->fresh()->toc->toDateString());
        $this->assertSame('2026-10-15', $project->fresh()->toc->toDateString());
    }

    public function test_variation_order_adds_or_reduces_rab_without_mutating_base_history(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $job, $price, $rab] = $this->rabFixture($mitra);
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');
        $this->asThc(fn () => app(ProjectPlanningService::class)->savePlan($project, $thc, '2026-09-30', [
            ['date' => '2026-09-30', 'percent' => '100'],
        ]));

        $response = $this->actingAs($thc)->post(route('projects.variation-orders.store', $project), [
            'reason' => 'Penyesuaian lapangan',
            'items' => [[
                'rab_jasa_id' => $rab->id,
                'quantity_delta' => '-2',
            ]],
        ])->assertRedirect(route('projects.show', $project));

        $variation = ProjectVariationOrder::query()->firstOrFail();

        $this->actingAs($thc)
            ->patch(route('projects.variation-orders.approve', [$project, $variation]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_rab_jasas', [
            'id' => $rab->id,
            'qty' => '10.000',
            'harga_satuan' => '125000.00',
        ]);
        $this->assertDatabaseHas('project_variation_order_items', [
            'project_variation_order_id' => $variation->id,
            'quantity_delta' => '-2.000',
            'status' => 'applied',
        ]);
        $this->assertSame('approved', $variation->fresh()->status);
    }

    public function test_new_rab_line_created_by_variation_order_is_not_double_counted(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $job, $price] = $this->rabFixtureWithoutRab($mitra);
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->actingAs($thc)->post(route('projects.variation-orders.store', $project), [
            'reason' => 'Pekerjaan tambahan',
            'items' => [['harga_jasa_id' => $price->id, 'quantity_delta' => '3']],
        ])->assertRedirect();
        $variation = ProjectVariationOrder::query()->firstOrFail();
        $this->actingAs($thc)
            ->patch(route('projects.variation-orders.approve', [$project, $variation]))
            ->assertRedirect();

        $addedRab = ProjectRabJasa::query()->where('variation_order_id', $variation->id)->firstOrFail();
        $this->assertSame(3.0, app(ProjectPlanningService::class)->currentRabQuantity($addedRab));
    }

    public function test_project_planning_routes_require_thc_permission(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWithPermissions($mitra->id, 'read_project');

        $this->actingAs($user)
            ->put(route('projects.plan.update', $project), ['toc' => '2026-09-30', 'plan' => []])
            ->assertForbidden();
    }

    /** @return array{Project, PekerjaanJasa, MitraHargaJasa, ProjectRabJasa} */
    private function rabFixture(Mitra $mitra): array
    {
        $project = $this->projectFor($mitra);
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create([
            'kode' => 'JASA-002',
            'nama' => 'Terminasi Kabel',
            'aktif' => true,
        ]));
        $pks = $this->asThc(fn (): Pks => Pks::create([
            'mitra_id' => $mitra->id,
            'nomor' => 'PKS-002',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]));
        $price = $this->asThc(fn (): MitraHargaJasa => MitraHargaJasa::create([
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '125000.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-01-01',
        ]));
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->actingAs($thc)->post(route('projects.rab-jasa.store', $project), [
            'harga_jasa_id' => $price->id,
            'qty' => '10',
        ])->assertRedirect();

        return [$project, $job, $price, ProjectRabJasa::query()->firstOrFail()];
    }

    /** @return array{Project, PekerjaanJasa, MitraHargaJasa} */
    private function rabFixtureWithoutRab(Mitra $mitra): array
    {
        $project = $this->projectFor($mitra);
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create([
            'kode' => 'JASA-003',
            'nama' => 'Pekerjaan Tambahan',
            'aktif' => true,
        ]));
        $pks = $this->asThc(fn (): Pks => Pks::create([
            'mitra_id' => $mitra->id,
            'nomor' => 'PKS-003',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]));
        $price = $this->asThc(fn (): MitraHargaJasa => MitraHargaJasa::create([
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '125000.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-01-01',
        ]));

        return [$project, $job, $price];
    }

    private function projectFor(Mitra $mitra): Project
    {
        return $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Planning',
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

    private function asTenant(int $mitraId, \Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set($mitraId, false);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }
}
