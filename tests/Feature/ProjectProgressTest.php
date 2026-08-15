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
use App\Support\TenantDatabaseContext;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_submits_pending_progress_and_thc_can_verify_or_reject_it(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $rab] = $this->rabFixture($mitra);
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_project', 'report_project_progress');
        $thc = $this->userWithPermissions(null, 'read_project', 'verify_project_progress');

        $this->actingAs($mitraUser)
            ->post(route('projects.progress.store', $project), [
                'project_rab_jasa_id' => $rab->id,
                'actual_date' => '2026-08-15',
                'qty' => '4',
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->asThc(function (): void {
            $this->assertDatabaseHas('project_progresses', [
                'status' => 'pending',
                'qty' => '4.000',
            ]);
            $this->assertDatabaseHas('project_timelines', ['event_key' => 'progress_submitted']);
        });

        $progressId = $this->asThc(fn (): int => (int) DB::table('project_progresses')->value('id'));

        $this->actingAs($thc)
            ->patch(route('projects.progress.verify', [$project, $progressId]))
            ->assertRedirect(route('projects.show', $project));

        $this->asThc(function (): void {
            $this->assertDatabaseHas('project_progresses', ['status' => 'verified']);
            $this->assertDatabaseHas('project_timelines', ['event_key' => 'progress_verified']);
        });
    }

    public function test_progress_cannot_exceed_remaining_rab_quantity(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $rab] = $this->rabFixture($mitra);
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_project', 'report_project_progress');

        $this->actingAs($mitraUser)
            ->from(route('projects.show', $project))
            ->post(route('projects.progress.store', $project), [
                'project_rab_jasa_id' => $rab->id,
                'actual_date' => '2026-08-15',
                'qty' => '11',
            ])
            ->assertSessionHasErrors('qty');
    }

    public function test_mitra_cannot_submit_progress_to_another_mitras_project(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        [$projectB, $rabB] = $this->rabFixture($mitraB);
        $mitraUserA = $this->userWithPermissions($mitraA->id, 'read_project', 'report_project_progress');

        $this->actingAs($mitraUserA)
            ->post(route('projects.progress.store', $projectB), [
                'project_rab_jasa_id' => $rabB->id,
                'actual_date' => '2026-08-15',
                'qty' => '1',
            ])
            ->assertNotFound();
    }

    /** @return array{Project, ProjectRabJasa} */
    private function rabFixture(Mitra $mitra): array
    {
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Progress',
            'mitra_id' => $mitra->id,
        ]));
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create([
            'kode' => 'JASA-'.fake()->unique()->numerify('###'),
            'nama' => 'Pekerjaan Progress',
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
            'harga' => '100000.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-01-01',
        ]));
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');

        $this->actingAs($thc)->post(route('projects.rab-jasa.store', $project), [
            'harga_jasa_id' => $price->id,
            'qty' => '10',
        ])->assertRedirect();

        return [$project, ProjectRabJasa::query()->firstOrFail()];
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
