<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_thc_project_form_lists_only_active_mitra_with_searchable_code_and_name(): void
    {
        $thc = $this->thcWith('create_project');
        $active = Mitra::factory()->create(['kode' => 'MTR-AKTIF', 'nama' => 'Mitra Aktif']);
        $inactive = Mitra::factory()->create(['kode' => 'MTR-LAMA', 'nama' => 'Mitra Lama', 'aktif' => false]);

        $this->actingAs($thc)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('MTR-AKTIF - Mitra Aktif')
            ->assertSee('data-mitra-search', false)
            ->assertSee('data-search-text="MTR-AKTIF Mitra Aktif"', false)
            ->assertDontSee('MTR-LAMA - Mitra Lama');

        $this->assertNotSame($active->id, $inactive->id);
    }

    public function test_thc_can_create_project_with_an_automatic_monthly_id(): void
    {
        CarbonImmutable::setTestNow('2026-08-19 10:00:00');

        try {
            $thc = $this->thcWith('create_project');
            $mitra = Mitra::factory()->create(['kode' => 'MTR-AKTIF', 'nama' => 'Mitra Aktif']);

            $this->actingAs($thc)
                ->post(route('projects.store'), [
                    'id_project' => '',
                    'nama' => 'Project Otomatis',
                    'mitra_id' => $mitra->id,
                ])
                ->assertRedirect(route('projects.create'))
                ->assertSessionHas('status', 'Project PRJ-2608-0001 dibuat.');

            $this->actingAs($thc)
                ->get(route('projects.create'))
                ->assertOk();

            $this->assertDatabaseHas('projects', [
                'id_project' => 'PRJ-2608-0001',
                'nama' => 'Project Otomatis',
                'mitra_id' => $mitra->id,
            ]);
            $this->assertDatabaseHas('project_code_issued', ['kode' => 'PRJ-2608-0001']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_automatic_project_id_is_not_reused_after_project_deletion(): void
    {
        CarbonImmutable::setTestNow('2026-08-19 10:00:00');

        try {
            $thc = $this->thcWith('create_project', 'delete_project');
            $mitra = Mitra::factory()->create();

            $this->actingAs($thc)->post(route('projects.store'), [
                'id_project' => '', 'nama' => 'Project Pertama', 'mitra_id' => $mitra->id,
            ])->assertRedirect();
            $first = Project::query()->where('id_project', 'PRJ-2608-0001')->firstOrFail();

            $this->actingAs($thc)->delete(route('projects.destroy', $first))->assertRedirect();

            $this->actingAs($thc)->post(route('projects.store'), [
                'id_project' => '', 'nama' => 'Project Kedua', 'mitra_id' => $mitra->id,
            ])->assertRedirect();

            $this->assertDatabaseHas('projects', ['id_project' => 'PRJ-2608-0002']);
            $this->assertDatabaseMissing('projects', ['id_project' => 'PRJ-2608-0001']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_manual_project_id_is_retained_and_duplicate_validation_returns_old_input(): void
    {
        $thc = $this->thcWith('create_project');
        $mitra = Mitra::factory()->create();

        $this->actingAs($thc)->post(route('projects.store'), [
            'id_project' => 'LEGACY-PROJECT-001',
            'nama' => 'Project Manual',
            'mitra_id' => $mitra->id,
        ])->assertRedirect();

        $response = $this->actingAs($thc)->from(route('projects.create'))->post(route('projects.store'), [
            'id_project' => 'LEGACY-PROJECT-001',
            'nama' => 'Project Duplikat',
            'mitra_id' => $mitra->id,
        ]);

        $response->assertRedirect(route('projects.create'))->assertSessionHasErrors('id_project');
        $this->assertSame('LEGACY-PROJECT-001', session()->getOldInput('id_project'));
        $this->assertDatabaseHas('projects', ['id_project' => 'LEGACY-PROJECT-001', 'nama' => 'Project Manual']);
    }

    public function test_manual_automatic_shaped_project_id_is_reserved_without_reuse(): void
    {
        CarbonImmutable::setTestNow('2026-08-19 10:00:00');

        try {
            $thc = $this->thcWith('create_project', 'delete_project');
            $mitra = Mitra::factory()->create();

            $this->actingAs($thc)->post(route('projects.store'), [
                'id_project' => 'PRJ-2608-0001', 'nama' => 'Project Legacy', 'mitra_id' => $mitra->id,
            ])->assertRedirect();
            $first = Project::query()->where('id_project', 'PRJ-2608-0001')->firstOrFail();

            $this->actingAs($thc)->delete(route('projects.destroy', $first))->assertRedirect();
            $this->actingAs($thc)->post(route('projects.store'), [
                'id_project' => '', 'nama' => 'Project Berikutnya', 'mitra_id' => $mitra->id,
            ])->assertRedirect();

            $this->assertDatabaseHas('projects', ['id_project' => 'PRJ-2608-0002']);
            $this->assertDatabaseHas('project_code_issued', ['kode' => 'PRJ-2608-0001']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_project_id_with_new_prefix_must_use_the_automatic_format(): void
    {
        $thc = $this->thcWith('create_project');
        $mitra = Mitra::factory()->create();

        $this->actingAs($thc)->from(route('projects.create'))->post(route('projects.store'), [
            'id_project' => 'PRJ-2608-ABCD', 'nama' => 'Project Salah Format', 'mitra_id' => $mitra->id,
        ])->assertRedirect(route('projects.create'))->assertSessionHasErrors('id_project');

        $this->assertDatabaseMissing('projects', ['nama' => 'Project Salah Format']);
    }

    public function test_project_id_sequence_overflow_returns_form_error(): void
    {
        CarbonImmutable::setTestNow('2026-08-19 10:00:00');

        try {
            $thc = $this->thcWith('create_project');
            $mitra = Mitra::factory()->create();
            DB::table('project_code_sequences')->insert([
                'bulan' => '2608', 'nomor_berikutnya' => 10000, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->actingAs($thc)->from(route('projects.create'))->post(route('projects.store'), [
                'id_project' => '', 'nama' => 'Project Overflow', 'mitra_id' => $mitra->id,
            ])->assertRedirect(route('projects.create'))->assertSessionHasErrors('id_project');

            $this->assertDatabaseMissing('projects', ['nama' => 'Project Overflow']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_inactive_mitra_is_rejected_and_validation_preserves_form_input(): void
    {
        $thc = $this->thcWith('create_project');
        $inactive = Mitra::factory()->create(['aktif' => false]);

        $this->actingAs($thc)->from(route('projects.create'))->post(route('projects.store'), [
            'id_project' => '',
            'nama' => 'Project Mitra Lama',
            'mitra_id' => $inactive->id,
        ])->assertRedirect(route('projects.create'))->assertSessionHasErrors('mitra_id');

        $this->assertSame('Project Mitra Lama', session()->getOldInput('nama'));
        $this->assertDatabaseMissing('projects', ['nama' => 'Project Mitra Lama']);
    }

    public function test_mitra_user_cannot_open_or_post_the_thc_project_creation_form(): void
    {
        $mitra = Mitra::factory()->create();
        $mitraUser = $this->mitraWith($mitra, 'create_project');

        $this->actingAs($mitraUser)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($mitraUser)->post(route('projects.store'), [
            'id_project' => '', 'nama' => 'Tidak Boleh',
        ])->assertForbidden();
    }

    public function test_project_update_does_not_change_the_issued_project_id(): void
    {
        $thc = $this->thcWith('update_project');
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0007', 'nama' => 'Project Lama', 'mitra_id' => $mitra->id,
        ]));

        $this->actingAs($thc)->patch(route('projects.update', $project), [
            'id_project' => 'PRJ-2608-9999',
            'nama' => 'Project Baru',
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'id_project' => 'PRJ-2608-0007',
            'nama' => 'Project Baru',
        ]);
    }

    public function test_project_model_rejects_changing_an_issued_project_id(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0008', 'nama' => 'Project Immutable', 'mitra_id' => $mitra->id,
        ]));

        $this->expectException(\LogicException::class);

        $this->asThc(fn (): bool => $project->update(['id_project' => 'PRJ-2608-0009']));
    }

    private function thcWith(string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission): int => Izin::factory()->create(['kode' => $permission])->id
        )->all());

        return User::factory()->create(['mitra_id' => null, 'grup_id' => $group->id]);
    }

    private function mitraWith(Mitra $mitra, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission): int => Izin::factory()->create(['kode' => $permission])->id
        )->all());

        return User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $group->id]);
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
