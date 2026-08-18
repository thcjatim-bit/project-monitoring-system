<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\MitraHargaJasa;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use App\Models\Project;
use App\Models\ProjectProgress;
use App\Models\ProjectRabJasa;
use App\Models\ProjectRabMaterial;
use App\Models\ProjectTimeline;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Queries\PortfolioCockpitQuery;
use App\Services\ProjectPlanningService;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Closure;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PortfolioCockpitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_thc_with_read_dashboard_can_open_the_portfolio_cockpit(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Portfolio Cockpit');
    }

    public function test_mitra_with_read_dashboard_can_open_the_portfolio_cockpit(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_dashboard');

        $this->actingAs($user)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Portfolio Cockpit');
    }

    public function test_user_without_read_dashboard_is_forbidden_even_by_direct_url(): void
    {
        $thc = $this->userWithPermissions(null, 'read_project');
        $mitraUser = $this->userWithPermissions(Mitra::factory()->create()->id, 'read_project');

        $this->actingAs($thc)->get('/portfolio')->assertForbidden();
        $this->actingAs($mitraUser)->get('/portfolio')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('portfolio.index'))->assertRedirect(route('login'));
    }

    public function test_kpis_are_calculated_from_actual_project_data(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $active = $this->projectFor($mitra, 'PRJ-2608-0001', 'Project Aktif');
        $rab = $this->addRabJasa($active, '100');
        $this->recordProgress($active, $rab, '2026-08-10', '40', 'verified');
        $done = $this->projectFor($mitra, 'PRJ-2608-0002', 'Project Selesai', 'selesai');
        $this->addRabJasa($done, '50');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Project aktif')
            ->assertSee('<strong data-kpi="active-projects">1</strong>', false)
            ->assertSee('Realisasi jasa terverifikasi')
            ->assertSee('<strong data-kpi="verified-percent">40.00%</strong>', false)
            ->assertSee('Nilai RAB Jasa aktif')
            ->assertSee('<strong data-kpi="active-rab-value">Rp 1.000.000</strong>', false)
            ->assertSee('Data diperbarui');
    }

    public function test_pending_progress_is_separated_and_does_not_raise_verified_realisasi(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-2608-0003', 'Project Pending');
        $rab = $this->addRabJasa($project, '100');
        $this->recordProgress($project, $rab, '2026-08-10', '40', 'verified');
        $this->recordProgress($project, $rab, '2026-08-12', '20', 'pending');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Realisasi jasa terverifikasi')
            ->assertSee('<strong data-kpi="verified-percent">40.00%</strong>', false)
            ->assertSee('Progres pending 20.00%');
    }

    public function test_portfolio_spi_is_na_when_cumulative_baseline_is_zero(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-2608-0004', 'Project Tanpa Baseline');
        $rab = $this->addRabJasa($project, '100');
        $this->recordProgress($project, $rab, '2026-08-10', '40', 'verified');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('SPI portofolio')
            ->assertSee('<strong data-kpi="spi">N/A</strong>', false)
            ->assertSee('data-spi-status="na"', false);
    }

    public function test_portfolio_spi_uses_the_active_baseline_and_counts_projects_that_need_attention(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $late = $this->projectFor($mitra, 'PRJ-2608-0005', 'Project Terlambat');
        $lateRab = $this->addRabJasa($late, '100');
        $this->savePlan($late, '2026-08-30');
        $this->recordProgress($late, $lateRab, '2026-08-10', '40', 'verified');
        $healthy = $this->projectFor($mitra, 'PRJ-2608-0006', 'Project Sehat');
        $healthyRab = $this->addRabJasa($healthy, '100');
        $this->savePlan($healthy, '2026-08-30');
        $this->recordProgress($healthy, $healthyRab, '2026-08-10', '50', 'verified');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('SPI portofolio')
            ->assertSee('<strong data-kpi="spi">0.90</strong>', false)
            ->assertSee('data-spi-status="yellow"', false)
            ->assertSee('Project perlu perhatian')
            ->assertSee('<strong data-kpi="attention-projects">1</strong>', false);
    }

    public function test_portfolio_spi_ignores_projects_without_an_active_baseline(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $planned = $this->projectFor($mitra, 'PRJ-2608-0013', 'Project Berbaseline');
        $plannedRab = $this->addRabJasa($planned, '100');
        $this->savePlan($planned, '2026-08-30');
        $this->recordProgress($planned, $plannedRab, '2026-08-10', '40', 'verified');
        $unplanned = $this->projectFor($mitra, 'PRJ-2608-0014', 'Project Tanpa Baseline');
        $unplannedRab = $this->addRabJasa($unplanned, '100');
        $this->recordProgress($unplanned, $unplannedRab, '2026-08-10', '100', 'verified');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('<strong data-kpi="spi">0.80</strong>', false)
            ->assertSee('data-spi-status="red"', false)
            ->assertSee('Dihitung dari 1 Project dengan baseline berlaku')
            ->assertSee('<strong data-kpi="verified-percent">70.00%</strong>', false);
    }

    public function test_material_readiness_averages_projects_instead_of_summing_across_units(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $meter = Material::factory()->create();
        $piece = Material::factory()->create();
        $ready = $this->projectFor($mitra, 'PRJ-2608-0015', 'Project Material Siap');
        $this->requireMaterial($ready, $meter, '10');
        $this->deliverMaterial($ready, $meter, qty: '10', received: '10');
        $waiting = $this->projectFor($mitra, 'PRJ-2608-0016', 'Project Material Menunggu');
        $this->requireMaterial($waiting, $piece, '1000');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('<strong data-kpi="material-readiness">50.00%</strong>', false)
            ->assertSee('Rata-rata kesiapan 2 Project ber-RAB Material');
    }

    public function test_progress_and_material_kpis_are_restricted_without_their_module_permission(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-2608-0017', 'Project Terbatas');
        $rab = $this->addRabJasa($project, '100');
        $this->recordProgress($project, $rab, '2026-08-10', '40', 'verified');
        $material = Material::factory()->create();
        $this->requireMaterial($project, $material, '10');
        $this->deliverMaterial($project, $material, qty: '10', received: '4');
        $limited = $this->userWithPermissions(null, 'read_dashboard', 'read_project');

        $this->actingAs($limited)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('<strong data-kpi="active-projects">1</strong>', false)
            ->assertSee('<strong data-kpi="active-rab-value">Rp 1.000.000</strong>', false)
            ->assertSee('<strong data-kpi="verified-percent">Terbatas</strong>', false)
            ->assertSee('<strong data-kpi="material-readiness">Terbatas</strong>', false)
            ->assertDontSee('40.00%');
    }

    public function test_material_readiness_is_separate_from_jasa_and_excludes_transit(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-2608-0007', 'Project Material');
        $this->addRabJasa($project, '100');
        $material = Material::factory()->create();
        $this->requireMaterial($project, $material, '10');
        $this->deliverMaterial($project, $material, qty: '10', received: '4');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Kesiapan Material')
            ->assertSee('<strong data-kpi="material-readiness">40.00%</strong>', false)
            ->assertSee('Material Transit yang belum dihitung sebagai Material tersedia');
    }

    public function test_mitra_scope_keeps_other_tenants_out_of_the_portfolio_kpis(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitraA = Mitra::factory()->create(['nama' => 'Mitra Alpha']);
        $mitraB = Mitra::factory()->create(['nama' => 'Mitra Beta']);
        $projectA = $this->projectFor($mitraA, 'PRJ-2608-0008', 'Project Alpha');
        $this->addRabJasa($projectA, '100');
        $projectB = $this->projectFor($mitraB, 'PRJ-2608-0009', 'Project Beta');
        $this->addRabJasa($projectB, '50');
        $this->asThc(function () use ($projectA, $projectB): void {
            foreach ([$projectA, $projectB] as $project) {
                ProjectTimeline::query()->create([
                    'mitra_id' => $project->mitra_id,
                    'project_id' => $project->id,
                    'type' => 'system_log',
                    'event_key' => 'step_changed',
                    'created_at' => '2026-08-15 08:00:00',
                    'updated_at' => '2026-08-15 08:00:00',
                ]);
            }
        });
        $user = $this->userWithPermissions($mitraA->id, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material', 'read_project_timeline');

        $this->actingAs($user)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('<strong data-kpi="active-projects">1</strong>', false)
            ->assertSee('<strong data-kpi="active-rab-value">Rp 1.000.000</strong>', false)
            ->assertSee('Step Project diperbarui')
            ->assertSee('Project Alpha')
            ->assertDontSee('Rp 1.500.000')
            ->assertDontSee('Project Beta')
            ->assertDontSee('PRJ-2608-0009')
            ->assertDontSee('Mitra Beta');
    }

    public function test_filters_change_the_kpis_and_stay_visible_as_page_context(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 09:00:00');
        $mitraA = Mitra::factory()->create(['nama' => 'Mitra Alpha']);
        $mitraB = Mitra::factory()->create(['nama' => 'Mitra Beta']);
        $projectA = $this->projectFor($mitraA, 'PRJ-2609-0001', 'Project Alpha');
        $rabA = $this->addRabJasa($projectA, '100');
        $this->recordProgress($projectA, $rabA, '2026-08-10', '30', 'verified');
        $this->recordProgress($projectA, $rabA, '2026-09-05', '20', 'verified');
        $projectB = $this->projectFor($mitraB, 'PRJ-2609-0002', 'Project Beta');
        $this->addRabJasa($projectB, '100');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('<strong data-kpi="active-projects">2</strong>', false)
            ->assertSee('<strong data-kpi="verified-percent">25.00%</strong>', false);

        $this->actingAs($thc)
            ->get(route('portfolio.index', ['mitra' => $mitraA->id]))
            ->assertOk()
            ->assertSee('Filter aktif')
            ->assertSee('Mitra Alpha')
            ->assertSee('<strong data-kpi="active-projects">1</strong>', false)
            ->assertSee('<strong data-kpi="verified-percent">50.00%</strong>', false);

        $this->actingAs($thc)
            ->get(route('portfolio.index', ['mitra' => $mitraA->id, 'periode' => '2026-08']))
            ->assertOk()
            ->assertSee('Agustus 2026')
            ->assertSee('<strong data-kpi="verified-percent">30.00%</strong>', false);

        $this->actingAs($thc)
            ->get(route('portfolio.index', ['project' => $projectB->id]))
            ->assertOk()
            ->assertSee('Project Beta')
            ->assertSee('<strong data-kpi="active-projects">1</strong>', false)
            ->assertSee('<strong data-kpi="verified-percent">0.00%</strong>', false);
    }

    public function test_portfolio_includes_a_scoped_trend_health_matrix_status_distribution_and_public_activity(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create(['nama' => 'Mitra Cockpit']);
        $project = $this->projectFor($mitra, 'PRJ-2608-0018', 'Project Cockpit');
        $rab = $this->addRabJasa($project, '100');
        $this->savePlan($project, '2026-08-30');
        $this->recordProgress($project, $rab, '2026-08-10', '40', 'verified');
        $finished = $this->projectFor($mitra, 'PRJ-2608-0019', 'Project Selesai', 'selesai');
        $this->addRabJasa($finished, '50');

        $this->asThc(function () use ($project): void {
            ProjectTimeline::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'type' => 'comment',
                'event_key' => 'comment_created',
                'body' => 'Aktivitas publik Project Cockpit',
            ]);
            ProjectTimeline::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'type' => 'internal_note',
                'event_key' => 'internal_note_created',
                'body' => 'Rahasia internal tidak boleh tampil',
            ]);
        });

        $thc = $this->userWithPermissions(
            null,
            'read_dashboard',
            'read_project',
            'read_project_progress',
            'read_project_material',
            'read_project_timeline',
        );

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Tren realisasi jasa')
            ->assertSee('Health Matrix')
            ->assertSee('Distribusi Status Project')
            ->assertSee('Aktivitas terbaru lintas Project')
            ->assertSee('data-portfolio-trend', false)
            ->assertSee('data-health-matrix', false)
            ->assertSee('data-status-distribution', false)
            ->assertSee('data-project-activity', false)
            ->assertSee('data-project-identity', false)
            ->assertSee('min-width: 800px', false)
            ->assertSee('Aktivitas publik Project Cockpit')
            ->assertSee('Project Selesai')
            ->assertSee('Mitra Cockpit')
            ->assertDontSee('Rahasia internal tidak boleh tampil')
            ->assertSee('40.00%', false)
            ->assertSee('50.00%', false);
    }

    public function test_portfolio_panels_follow_risk_and_period_filters_together(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $red = $this->projectFor($mitra, 'PRJ-2608-0020', 'Project Merah');
        $redRab = $this->addRabJasa($red, '100');
        $this->savePlan($red, '2026-08-30');
        $this->recordProgress($red, $redRab, '2026-08-10', '30', 'verified');
        $green = $this->projectFor($mitra, 'PRJ-2608-0021', 'Project Hijau');
        $greenRab = $this->addRabJasa($green, '100');
        $this->savePlan($green, '2026-08-30');
        $this->recordProgress($green, $greenRab, '2026-08-10', '50', 'verified');

        $this->asThc(function () use ($red, $green): void {
            foreach ([$red, $green] as $project) {
                ProjectTimeline::query()->create([
                    'mitra_id' => $project->mitra_id,
                    'project_id' => $project->id,
                    'type' => 'system_log',
                    'event_key' => 'step_changed',
                    'created_at' => '2026-08-10 08:00:00',
                    'updated_at' => '2026-08-10 08:00:00',
                ]);
            }
        });

        $thc = $this->userWithPermissions(
            null,
            'read_dashboard',
            'read_project',
            'read_project_progress',
            'read_project_material',
            'read_project_timeline',
        );

        $response = $this->actingAs($thc)
            ->get(route('portfolio.index', ['periode' => '2026-08', 'risiko' => 'merah']))
            ->assertOk()
            ->assertSee('Merah')
            ->assertSee('<strong data-kpi="active-projects">1</strong>', false)
            ->assertSee('30.00%', false)
            ->assertSee('50.00%', false)
            ->assertSee('<div class="portfolio__distribution-row" data-status-key="red">', false)
            ->assertSee('overflow-x: auto', false);

        $html = $response->getContent();
        $matrixStart = strpos($html, 'id="portfolio-health-matrix"');
        $matrixEnd = strpos($html, '</section>', $matrixStart);
        $activityStart = strpos($html, 'id="portfolio-project-activity"');
        $activityEnd = strpos($html, '</section>', $activityStart);
        $this->assertNotFalse($matrixStart);
        $this->assertNotFalse($matrixEnd);
        $this->assertNotFalse($activityStart);
        $this->assertNotFalse($activityEnd);
        $matrixHtml = substr($html, $matrixStart, $matrixEnd - $matrixStart);
        $activityHtml = substr($html, $activityStart, $activityEnd - $activityStart);
        $this->assertStringContainsString('PRJ-2608-0020', $matrixHtml);
        $this->assertStringNotContainsString('PRJ-2608-0021', $matrixHtml);
        $this->assertStringContainsString('PRJ-2608-0020', $activityHtml);
        $this->assertStringNotContainsString('PRJ-2608-0021', $activityHtml);
    }

    public function test_decision_queue_surfaces_low_spi_as_a_read_only_project_exception(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create(['nama' => 'Mitra Queue']);
        $project = $this->projectFor($mitra, 'PRJ-2608-0050', 'Project Queue SPI');
        $rab = $this->addRabJasa($project, '100');
        $this->savePlan($project, '2026-08-30');
        $this->recordProgress($project, $rab, '2026-08-10', '30', 'verified');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress');

        $response = $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Decision Queue')
            ->assertSee('SPI rendah')
            ->assertSee('Project Queue SPI')
            ->assertSee('data-decision-category="spi"', false)
            ->assertSee('href="'.route('projects.show', $project).'"', false);

        $queueStart = strpos($response->getContent(), 'id="portfolio-decision-queue"');
        $this->assertNotFalse($queueStart);
        $queueHtml = substr($response->getContent(), $queueStart, strpos($response->getContent(), '</section>', $queueStart) - $queueStart);
        $this->assertStringNotContainsStringIgnoringCase('method="post"', $queueHtml);
        $this->assertStringNotContainsStringIgnoringCase('<form', $queueHtml);
    }

    public function test_decision_queue_lists_material_transit_toc_and_pending_evidence_exceptions(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 12:00:00');
        $mitra = Mitra::factory()->create(['nama' => 'Mitra Queue Categories']);

        $materialProject = $this->projectFor($mitra, 'PRJ-2608-0051', 'Project Material Queue');
        $material = Material::factory()->create(['nama' => 'Material Queue']);
        $this->requireMaterial($materialProject, $material, '10');
        $this->createTransferFor($materialProject, $material, '2026-08-19 08:00:00', '10', '0');

        $transitProject = $this->projectFor($mitra, 'PRJ-2608-0052', 'Project Transit Queue');
        $transitMaterial = Material::factory()->create(['nama' => 'Transit Queue']);
        $this->createTransferFor($transitProject, $transitMaterial, '2026-08-15 08:00:00', '5', '0');

        $tocProject = $this->projectFor($mitra, 'PRJ-2608-0053', 'Project TOC Queue');
        $this->asThc(fn () => $tocProject->update(['toc' => '2026-08-25']));

        $evidenceProject = $this->projectFor($mitra, 'PRJ-2608-0054', 'Project Evidence Queue');
        $evidenceRab = $this->addRabJasa($evidenceProject, '100');
        $this->recordProgress($evidenceProject, $evidenceRab, '2026-08-19', '40', 'pending');

        $thc = $this->userWithPermissions(
            null,
            'read_dashboard',
            'read_project',
            'read_project_progress',
            'read_project_material',
            'operate_warehouse',
        );

        $response = $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Transit melewati batas')
            ->assertSee('Material belum lengkap')
            ->assertSee('TOC mendekat')
            ->assertSee('Bukti pekerjaan pending')
            ->assertSee('Project Material Queue')
            ->assertSee('Project Transit Queue')
            ->assertSee('Project TOC Queue')
            ->assertSee('Project Evidence Queue')
            ->assertSee('Material Transit tidak dianggap sebagai Material tersedia')
            ->assertSee('<strong data-kpi="verified-percent">0.00%</strong>', false);

        $queueStart = strpos($response->getContent(), 'id="portfolio-decision-queue"');
        $queueEnd = strpos($response->getContent(), '</section>', $queueStart);
        $this->assertNotFalse($queueStart);
        $this->assertNotFalse($queueEnd);
        $queueHtml = substr($response->getContent(), $queueStart, $queueEnd - $queueStart);
        $this->assertStringNotContainsStringIgnoringCase('method="post"', $queueHtml);
        $this->assertStringNotContainsStringIgnoringCase('<form', $queueHtml);
    }

    public function test_decision_queue_follows_risk_and_period_filters(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 12:00:00');
        $mitra = Mitra::factory()->create();
        $red = $this->projectFor($mitra, 'PRJ-2608-0055', 'Project Queue Merah');
        $redRab = $this->addRabJasa($red, '100');
        $this->savePlan($red, '2026-08-30');
        $this->recordProgress($red, $redRab, '2026-08-19', '30', 'verified');
        $green = $this->projectFor($mitra, 'PRJ-2608-0056', 'Project Queue Hijau');
        $greenRab = $this->addRabJasa($green, '100');
        $this->savePlan($green, '2026-08-30');
        $this->recordProgress($green, $greenRab, '2026-08-19', '50', 'verified');
        $this->recordProgress($green, $greenRab, '2026-08-19', '10', 'pending');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress');

        $response = $this->actingAs($thc)
            ->get(route('portfolio.index', ['periode' => '2026-08', 'risiko' => 'merah']))
            ->assertOk();
        $queueStart = strpos($response->getContent(), 'id="portfolio-decision-queue"');
        $queueEnd = strpos($response->getContent(), '</section>', $queueStart);
        $queueHtml = substr($response->getContent(), $queueStart, $queueEnd - $queueStart);
        $this->assertStringContainsString('Project Queue Merah', $queueHtml);
        $this->assertStringNotContainsString('Project Queue Hijau', $queueHtml);

        $priorPeriodResponse = $this->actingAs($thc)
            ->get(route('portfolio.index', ['periode' => '2026-06']))
            ->assertOk();
        $priorPeriodContent = $priorPeriodResponse->getContent();
        $priorPeriodQueueStart = strpos($priorPeriodContent, 'id="portfolio-decision-queue"');
        $priorPeriodQueueEnd = strpos($priorPeriodContent, '</section>', $priorPeriodQueueStart);
        $priorPeriodQueueHtml = substr($priorPeriodContent, $priorPeriodQueueStart, $priorPeriodQueueEnd - $priorPeriodQueueStart);
        $this->assertTrue(
            str_contains($priorPeriodQueueHtml, 'Tidak ada pengecualian yang perlu ditindaklanjuti untuk filter aktif.'),
            $priorPeriodQueueHtml,
        );
    }

    public function test_decision_queue_uses_the_toc_snapshot_for_the_selected_period(): void
    {
        CarbonImmutable::setTestNow('2026-09-20 12:00:00');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-2609-0061', 'Project Queue TOC Snapshot');
        $thc = $this->userWithPermissions(null, 'manage_project_plan');
        $this->asThc(fn () => app(ProjectPlanningService::class)->savePlan($project, $thc, '2026-09-25', [
            ['date' => '2026-09-01', 'percent' => 0],
            ['date' => '2026-09-25', 'percent' => 100],
        ]));
        $viewer = $this->userWithPermissions(null, 'read_dashboard', 'read_project');

        $this->actingAs($viewer)
            ->get(route('portfolio.index', ['periode' => '2026-09']))
            ->assertOk()
            ->assertSee('TOC mendekat')
            ->assertSee('Project Queue TOC Snapshot');

        $priorPeriod = $this->actingAs($viewer)
            ->get(route('portfolio.index', ['periode' => '2026-08']))
            ->assertOk();
        $queueStart = strpos($priorPeriod->getContent(), 'id="portfolio-decision-queue"');
        $queueEnd = strpos($priorPeriod->getContent(), '</section>', $queueStart);
        $this->assertNotFalse($queueStart);
        $this->assertNotFalse($queueEnd);
        $queueHtml = substr($priorPeriod->getContent(), $queueStart, $queueEnd - $queueStart);
        $this->assertStringNotContainsString('Project Queue TOC Snapshot', $queueHtml);
    }

    public function test_decision_queue_explains_when_source_permissions_are_missing(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 12:00:00');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-2608-0057', 'Project Queue Terbatas');
        $rab = $this->addRabJasa($project, '100');
        $this->savePlan($project, '2026-08-30');
        $this->recordProgress($project, $rab, '2026-08-19', '30', 'verified');
        $viewer = $this->userWithPermissions(null, 'read_dashboard');

        $response = $this->actingAs($viewer)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Decision Queue membutuhkan izin pada sumber Project, Progres jasa, atau Transit.');

        $queueStart = strpos($response->getContent(), 'id="portfolio-decision-queue"');
        $queueEnd = strpos($response->getContent(), '</section>', $queueStart);
        $queueHtml = substr($response->getContent(), $queueStart, $queueEnd - $queueStart);
        $this->assertStringNotContainsString('Project Queue Terbatas', $queueHtml);
        $this->assertStringNotContainsString('SPI rendah', $queueHtml);
    }

    public function test_decision_queue_does_not_turn_a_zero_baseline_into_a_spi_exception(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 12:00:00');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-2608-0058', 'Project Queue N/A');
        $rab = $this->addRabJasa($project, '100');
        $this->recordProgress($project, $rab, '2026-08-19', '40', 'verified');
        $viewer = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress');

        $response = $this->actingAs($viewer)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Tidak ada pengecualian yang perlu ditindaklanjuti untuk filter aktif.');
        $queueStart = strpos($response->getContent(), 'id="portfolio-decision-queue"');
        $queueEnd = strpos($response->getContent(), '</section>', $queueStart);
        $queueHtml = substr($response->getContent(), $queueStart, $queueEnd - $queueStart);
        $this->assertStringNotContainsString('Project Queue N/A', $queueHtml);
        $this->assertStringNotContainsString('SPI rendah', $queueHtml);
    }

    public function test_mitra_decision_queue_isolated_from_other_mitras(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 12:00:00');
        $mitraA = Mitra::factory()->create(['nama' => 'Mitra Queue A']);
        $mitraB = Mitra::factory()->create(['nama' => 'Mitra Queue B']);
        foreach ([[$mitraA, 'PRJ-2608-0059', 'Project Queue A'], [$mitraB, 'PRJ-2608-0060', 'Project Queue B']] as [$mitra, $idProject, $name]) {
            $project = $this->projectFor($mitra, $idProject, $name);
            $rab = $this->addRabJasa($project, '100');
            $this->savePlan($project, '2026-08-30');
            $this->recordProgress($project, $rab, '2026-08-19', '30', 'verified');
        }
        $viewer = $this->userWithPermissions($mitraA->id, 'read_dashboard', 'read_project', 'read_project_progress');

        $response = $this->actingAs($viewer)
            ->get(route('portfolio.index'))
            ->assertOk();
        $queueStart = strpos($response->getContent(), 'id="portfolio-decision-queue"');
        $queueEnd = strpos($response->getContent(), '</section>', $queueStart);
        $queueHtml = substr($response->getContent(), $queueStart, $queueEnd - $queueStart);
        $this->assertStringContainsString('Project Queue A', $queueHtml);
        $this->assertStringNotContainsString('Project Queue B', $queueHtml);
        $this->assertStringNotContainsString('Mitra Queue B', $queueHtml);
    }

    public function test_risk_filter_narrows_the_portfolio_to_projects_beyond_the_spi_threshold(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $late = $this->projectFor($mitra, 'PRJ-2608-0010', 'Project Terlambat');
        $lateRab = $this->addRabJasa($late, '100');
        $this->savePlan($late, '2026-08-30');
        $this->recordProgress($late, $lateRab, '2026-08-10', '40', 'verified');
        $healthy = $this->projectFor($mitra, 'PRJ-2608-0011', 'Project Sehat');
        $healthyRab = $this->addRabJasa($healthy, '100');
        $this->savePlan($healthy, '2026-08-30');
        $this->recordProgress($healthy, $healthyRab, '2026-08-10', '50', 'verified');
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index', ['risiko' => 'merah']))
            ->assertOk()
            ->assertSee('<strong data-kpi="active-projects">1</strong>', false)
            ->assertSee('<strong data-kpi="verified-percent">40.00%</strong>', false)
            ->assertSee('data-spi-status="red"', false);
    }

    public function test_empty_state_is_shown_when_no_project_is_in_scope(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('Belum ada Project dalam cakupan akses Anda.')
            ->assertSee('data-portfolio-state="empty"', false)
            ->assertSee('Filter aktif');
    }

    public function test_error_state_keeps_the_filters_and_user_context(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create(['nama' => 'Mitra Alpha']);
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');
        $this->partialMock(PortfolioCockpitQuery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('for')->andThrow(new RuntimeException('read model unavailable'));
        });

        $this->actingAs($thc)
            ->get(route('portfolio.index', ['mitra' => $mitra->id, 'periode' => '2026-07', 'risiko' => 'merah']))
            ->assertOk()
            ->assertSee('Portfolio Cockpit')
            ->assertSee('Portfolio Cockpit belum dapat dimuat. Coba lagi atau buka modul sumbernya.')
            ->assertSee('data-portfolio-state="error"', false)
            ->assertSee('Filter aktif')
            ->assertSee('Juli 2026')
            ->assertSee('Merah');
    }

    public function test_cockpit_is_read_only_and_links_to_authorized_sources(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 09:00:00');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-2608-0012', 'Project Tautan');
        $this->addRabJasa($project, '100');
        $reader = $this->userWithPermissions(null, 'read_dashboard', 'read_project', 'read_project_progress', 'read_project_material');
        $withoutProjects = $this->userWithPermissions(null, 'read_dashboard');

        $response = $this->actingAs($reader)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('href="'.route('projects.index').'"', false);

        $this->assertStringNotContainsStringIgnoringCase('method="post"', $response->getContent());
        $this->assertStringNotContainsStringIgnoringCase('<form method="POST"', $response->getContent());

        $this->actingAs($withoutProjects)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('projects.index').'"', false);
    }

    public function test_loading_state_is_present_without_hiding_the_filters(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($thc)
            ->get(route('portfolio.index'))
            ->assertOk()
            ->assertSee('data-portfolio-state="loading"', false)
            ->assertSee('Filter aktif');
    }

    private function projectFor(Mitra $mitra, string $idProject, string $nama, string $status = 'aktif'): Project
    {
        return $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => $idProject,
            'nama' => $nama,
            'mitra_id' => $mitra->id,
            'status_project' => $status,
        ]));
    }

    private function addRabJasa(Project $project, string $qty): ProjectRabJasa
    {
        $thc = $this->userWithPermissions(null, 'manage_project_plan');

        return $this->asThc(function () use ($project, $thc, $qty): ProjectRabJasa {
            $job = PekerjaanJasa::query()->create([
                'kode' => 'JASA-'.fake()->unique()->numerify('#####'),
                'nama' => 'Pekerjaan Portfolio',
                'aktif' => true,
            ]);
            $pks = Pks::query()->firstOrCreate(
                ['mitra_id' => $project->mitra_id],
                [
                    'nomor' => 'PKS-'.fake()->unique()->numerify('#####'),
                    'tanggal_mulai' => '2026-01-01',
                    'tanggal_berakhir' => '2026-12-31',
                ],
            );
            $price = MitraHargaJasa::query()->create([
                'mitra_id' => $project->mitra_id,
                'pks_id' => $pks->id,
                'pekerjaan_jasa_id' => $job->id,
                'harga' => '10000.00',
                'status' => 'disetujui',
                'berlaku_mulai' => '2026-01-01',
            ]);

            return app(ProjectPlanningService::class)->addRabJasa($project, $thc, $price->id, $qty);
        });
    }

    private function savePlan(Project $project, string $toc): void
    {
        $thc = $this->userWithPermissions(null, 'manage_project_plan');

        $this->asThc(fn () => app(ProjectPlanningService::class)->savePlan($project, $thc, $toc, [
            ['date' => '2026-08-10', 'percent' => 50],
            ['date' => $toc, 'percent' => 100],
        ]));
    }

    private function recordProgress(Project $project, ProjectRabJasa $rab, string $date, string $qty, string $status): void
    {
        $reporter = $this->userWithPermissions($project->mitra_id, 'report_project_progress');
        $verifier = $this->userWithPermissions(null, 'verify_project_progress');

        $this->asThc(fn (): ProjectProgress => ProjectProgress::query()->create([
            'mitra_id' => $project->mitra_id,
            'project_id' => $project->id,
            'project_rab_jasa_id' => $rab->id,
            'reported_by' => $reporter->id,
            'actual_date' => $date,
            'qty' => $qty,
            'status' => $status,
            'verified_by' => $status === 'verified' ? $verifier->id : null,
            'verified_at' => $status === 'verified' ? $date.' 10:00:00' : null,
        ]));
    }

    private function requireMaterial(Project $project, Material $material, string $qty): void
    {
        $this->asThc(fn (): ProjectRabMaterial => ProjectRabMaterial::query()->create([
            'mitra_id' => $project->mitra_id,
            'project_id' => $project->id,
            'material_id' => $material->id,
            'qty' => $qty,
        ]));
    }

    private function deliverMaterial(Project $project, Material $material, string $qty, string $received): void
    {
        $this->createTransferFor($project, $material, '2026-08-12 08:00:00', $qty, $received);
    }

    private function createTransferFor(Project $project, Material $material, string $issuedAt, string $qty, string $received): void
    {
        $issuer = $this->userWithPermissions(null, 'operate_warehouse');

        $this->asThc(function () use ($project, $material, $issuedAt, $qty, $received, $issuer): void {
            $origin = Warehouse::factory()->create(['mitra_id' => $project->mitra_id]);
            $destination = Warehouse::factory()->create(['mitra_id' => $project->mitra_id]);
            $suratJalan = SuratJalan::query()->create([
                'nomor' => 'SJ-'.CarbonImmutable::parse($issuedAt)->format('ym').'-'.fake()->unique()->numerify('####'),
                'tanggal' => substr($issuedAt, 0, 10),
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'issued_by' => $issuer->id,
                'issued_at' => $issuedAt,
                'status' => 'terbit',
                'pengirim' => 'THC',
            ]);
            SuratJalanItem::query()->create([
                'surat_jalan_id' => $suratJalan->id,
                'mitra_id' => $project->mitra_id,
                'material_id' => $material->id,
                'qty' => $qty,
                'qty_diterima' => $received,
                'qty_diretur' => 0,
            ]);
        });
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

    private function asThc(Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }
}
