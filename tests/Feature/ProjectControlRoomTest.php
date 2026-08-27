<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectTimeline;
use App\Models\User;
use App\Services\ProjectPlanningService;
use App\Support\TenantDatabaseContext;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectControlRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_read_project_can_open_control_room_from_project_list(): void
    {
        $mitra = Mitra::factory()->create(['nama' => 'Mitra Nusantara']);
        $user = $this->userWithPermissions($mitra->id, 'read_project');
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0040',
            'nama' => 'Instalasi Site Utama',
            'mitra_id' => $mitra->id,
            'status_project' => 'selesai',
            'toc' => '2026-09-30',
        ]));

        $this->actingAs($user)
            ->get('/projects')
            ->assertOk()
            ->assertSee(route('projects.show', $project), false);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Project Control Room')
            ->assertSee('PRJ-2608-0040')
            ->assertSee('Instalasi Site Utama')
            ->assertSee('Mitra Nusantara')
            ->assertSee('Selesai')
            ->assertSee('30 Sep 2026');

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertSee('href="'.route('projects.index').'"', false)
            ->assertDontSee('href="'.route('dashboard').'"', false)
            ->assertSee('data-control-room-state="loading"', false)
            ->assertSee('Memuat Control Room', false)
            ->assertSee('data-control-room-state="error"', false)
            ->assertSee('Gagal memuat Control Room', false);
    }

    public function test_user_without_read_project_cannot_open_control_room_directly(): void
    {
        $mitra = Mitra::factory()->create();
        $user = User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => Grup::factory()->create()->id]);
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0041',
            'nama' => 'Project Tertutup',
            'mitra_id' => $mitra->id,
        ]));

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_thc_with_read_project_can_open_control_room_from_project_list(): void
    {
        $thc = $this->userWithPermissions(null, 'read_project');
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0043',
            'nama' => 'Project THC',
            'mitra_id' => Mitra::factory()->create()->id,
        ]));

        $this->actingAs($thc)
            ->get('/projects')
            ->assertOk()
            ->assertSee(route('projects.show', $project), false)
            ->assertDontSee('href="'.route('dashboard').'"', false);

        $this->actingAs($thc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($project->id_project)
            ->assertSee($project->nama);
    }

    public function test_linimasa_gabungan_merender_baris_menyimpang_surat_jalan_sebagai_kalimat_terbaca(): void
    {
        [$project, $thc] = $this->timelineProject('PRJ-2608-0049', 'Project Baris Menyimpang');

        $this->recordTimelineEvent($project, 'surat_jalan_deviation', [
            'material_asing' => ['Kabel FO 12C'],
            'qty_melebihi' => ['Splitter 1:8'],
        ]);

        $this->actingAs($thc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Material di luar request')
            ->assertSee('Kabel FO 12C')
            ->assertSee('Qty melebihi sisa')
            ->assertSee('Splitter 1:8')
            ->assertDontSee('Surat Jalan Deviation');
    }

    public function test_linimasa_gabungan_mempertahankan_label_mentah_untuk_event_lain(): void
    {
        [$project, $thc] = $this->timelineProject('PRJ-2608-0050', 'Project Event Biasa');

        $this->recordTimelineEvent($project, 'step_changed', ['from' => 'design', 'to' => 'survey']);
        $this->recordTimelineEvent($project, 'toc_changed', ['from' => '2026-08-01', 'to' => '2026-08-15']);
        $this->recordTimelineEvent($project, 'surat_jalan_resolved', ['resolution' => 'kembali_ke_asal']);
        $this->recordTimelineEvent($project, 'surat_jalan_returned', ['retur_dari_id' => 42]);
        $this->recordTimelineEvent($project, 'event_tidak_dikenal');

        $this->actingAs($thc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Step Changed')
            ->assertSee('Toc Changed')
            ->assertSee('Surat Jalan Resolved')
            ->assertSee('Surat Jalan Returned')
            ->assertSee('Event Tidak Dikenal')
            ->assertDontSee('Material di luar request')
            ->assertDontSee('Qty melebihi sisa');
    }

    public function test_control_room_renders_photo_empty_state_and_photo_project_step_sync_context(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_project', 'upload_project_photo');
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0048',
            'nama' => 'Project Foto Lapangan',
            'mitra_id' => $mitra->id,
        ]));

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('data-photo-state="empty"', false)
            ->assertSee('Belum ada Foto Pekerjaan untuk Project ini.');

        $step = $project->steps()->where('step', 'survey')->firstOrFail();
        $photo = $this->asThc(fn (): ProjectPhoto => ProjectPhoto::create([
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'project_step_id' => $step->id,
            'uploaded_by' => $user->id,
            'original_name' => 'survey-lapangan.jpg',
            'stored_path' => 'project-photos/PRJ-2608-0048/survey/2026-08-17/survey-lapangan.jpg',
            'mime_type' => 'image/jpeg',
            'original_size' => 1234,
            'width' => 1920,
            'height' => 1080,
            'capture_date' => '2026-08-17',
            'sync_status' => 'failed',
            'sync_error' => 'Drive belum tersedia',
        ]));

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($project->id_project)
            ->assertSee('data-photo-project="PRJ-2608-0048"', false)
            ->assertSee('data-photo-step="survey"', false)
            ->assertSee('data-photo-sync-status="failed"', false)
            ->assertSee('survey-lapangan.jpg')
            ->assertSee('Survey')
            ->assertSee('Sync: failed')
            ->assertSee('Drive belum tersedia')
            ->assertSee(route('projects.photos.show', [$project, $photo->id]), false);
    }

    public function test_control_room_renders_contextual_error_without_leaking_other_projects(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $userA = $this->userWithPermissions($mitraA->id, 'read_project');
        $projectA = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0044',
            'nama' => 'Project Dengan Error',
            'mitra_id' => $mitraA->id,
        ]));
        $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0045',
            'nama' => 'Project Tenant Lain',
            'mitra_id' => $mitraB->id,
        ]));

        $this->actingAs($userA)
            ->get(route('projects.show', $projectA).'?as_of=not-a-date')
            ->assertOk()
            ->assertSee($projectA->id_project)
            ->assertSee('Gagal memuat Control Room', false)
            ->assertSee('data-control-room-state="error"', false)
            ->assertDontSee('Project Tenant Lain');
    }

    public function test_mitra_cannot_open_another_mitras_control_room(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $userA = $this->userWithPermissions($mitraA->id, 'read_project');
        $projectB = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0042',
            'nama' => 'Project Mitra B',
            'mitra_id' => $mitraB->id,
        ]));

        $this->actingAs($userA)
            ->get(route('projects.show', $projectB))
            ->assertNotFound();
    }

    public function test_curve_panel_distinguishes_original_and_revised_baselines(): void
    {
        $mitra = Mitra::factory()->create();
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0046',
            'nama' => 'Project Baseline Terpisah',
            'mitra_id' => $mitra->id,
        ]));
        $planning = app(ProjectPlanningService::class);

        $this->asThc(fn () => $planning->savePlan($project, $thc, '2026-08-20', [
            ['date' => '2026-08-10', 'percent' => 70],
            ['date' => '2026-08-20', 'percent' => 100],
        ]));
        $this->asThc(fn () => $planning->savePlan($project, $thc, '2026-08-30', [
            ['date' => '2026-08-15', 'percent' => 30],
            ['date' => '2026-08-30', 'percent' => 100],
        ]));

        $this->actingAs($thc)
            ->get(route('projects.show', $project).'?as_of=2026-08-15')
            ->assertOk()
            ->assertSee('Original Baseline')
            ->assertSee('Revised Baseline')
            ->assertSee('data-curve-series="original"', false)
            ->assertSee('data-curve-series="revised"', false)
            ->assertSee('Pending shadow');
    }

    public function test_curve_panel_highlights_delay_after_original_baseline_toc(): void
    {
        $mitra = Mitra::factory()->create();
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0047',
            'nama' => 'Project Melewati TOC',
            'mitra_id' => $mitra->id,
        ]));

        $this->asThc(fn () => app(ProjectPlanningService::class)->savePlan($project, $thc, '2026-08-10', [
            ['date' => '2026-08-01', 'percent' => 40],
            ['date' => '2026-08-10', 'percent' => 100],
        ]));

        $this->actingAs($thc)
            ->get(route('projects.show', $project).'?as_of=2026-08-15')
            ->assertOk()
            ->assertSee('data-curve-overdue="true"', false)
            ->assertSee('control-room__chart-overdue', false)
            ->assertSee('Periode keterlambatan', false);
    }

    /** @return array{Project, User} */
    private function timelineProject(string $idProject, string $name): array
    {
        $mitra = Mitra::factory()->create();
        $thc = $this->userWithPermissions(null, 'read_project', 'read_project_timeline');
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => $idProject,
            'nama' => $name,
            'mitra_id' => $mitra->id,
        ]));

        return [$project, $thc];
    }

    /** @param array<string, mixed> $metadata */
    private function recordTimelineEvent(Project $project, string $eventKey, array $metadata = []): ProjectTimeline
    {
        return $this->asThc(fn (): ProjectTimeline => ProjectTimeline::recordSystem(
            $project,
            null,
            $eventKey,
            $metadata,
        ));
    }

    private function userWithPermissions(?int $mitraId, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::factory()->create(['kode' => $permission])->id,
        )->all());

        return User::factory()->create([
            'mitra_id' => $mitraId,
            'grup_id' => $group->id,
        ]);
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
