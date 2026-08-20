<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialInventoryService;
use App\Support\TenantDatabaseContext;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MitraDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_login_uses_the_mitra_dashboard_instead_of_thc_dashboard(): void
    {
        $user = $this->mitraUser(['read_dashboard']);

        $this->post('/masuk', [
            'email' => $user->email,
            'password' => 'rahasia-benar',
        ])->assertRedirect(route('mitra.dashboard'));

        $this->get(route('mitra.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Mitra')
            ->assertDontSee('Command Center THC');
    }

    public function test_mitra_dashboard_hides_transit_without_read_transit_permission(): void
    {
        $user = $this->mitraUser(['read_dashboard']);

        $this->actingAs($user)
            ->get(route('mitra.dashboard'))
            ->assertOk()
            ->assertDontSee('id="transit-summary-title"', false);
    }

    public function test_mitra_without_dashboard_permission_gets_a_clear_safe_landing(): void
    {
        $user = $this->mitraUser([]);

        $this->post('/masuk', [
            'email' => $user->email,
            'password' => 'rahasia-benar',
        ])->assertRedirect(route('mitra.landing'));

        $this->get(route('mitra.landing'))
            ->assertOk()
            ->assertSee('Akses Mitra belum tersedia')
            ->assertDontSee('href="'.route('dashboard').'"', false);
    }

    public function test_mitra_with_project_permission_uses_project_landing_when_dashboard_is_unavailable(): void
    {
        $user = $this->mitraUser(['read_project']);

        $this->post('/masuk', [
            'email' => $user->email,
            'password' => 'rahasia-benar',
        ])->assertRedirect(route('projects.index'));
    }

    public function test_mitra_cannot_enter_the_thc_dashboard_directly(): void
    {
        $user = $this->mitraUser(['read_dashboard']);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_mitra_dashboard_is_tenant_scoped_and_read_only(): void
    {
        $mitraA = Mitra::factory()->create(['nama' => 'Mitra A']);
        $mitraB = Mitra::factory()->create(['nama' => 'Mitra B']);
        $user = $this->mitraUser(['read_dashboard', 'read_project', 'read_project_material', 'read_project_progress', 'read_project_timeline'], $mitraA);

        $projectA = $this->asThc(fn () => Project::create([
            'id_project' => 'PRJ-2608-0083',
            'nama' => 'Project A terlihat',
            'mitra_id' => $mitraA->id,
            'status_project' => 'aktif',
        ]));
        $this->asThc(fn () => Project::create([
            'id_project' => 'PRJ-2608-0084',
            'nama' => 'Project B tersembunyi',
            'mitra_id' => $mitraB->id,
            'status_project' => 'selesai',
        ]));

        $this->actingAs($user)
            ->get(route('mitra.dashboard'))
            ->assertOk()
            ->assertSee('Project A terlihat')
            ->assertDontSee('Project B tersembunyi')
            ->assertSee('Project aktif')
            ->assertSee('Kesiapan Material Project')
            ->assertSee('Keluar')
            ->assertSee('Portfolio')
            ->assertDontSee('Simpan perubahan')
            ->assertDontSee('Hapus');

        $this->get(route('projects.show', $projectA))->assertOk();
    }

    public function test_mitra_dashboard_lists_each_special_material_balance_once(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $serialised = Material::factory()->create(['nama' => 'Material Ber-SN Dashboard', 'jenis' => 'ber_sn']);
        $drum = Material::factory()->create(['nama' => 'Material Drum Dashboard', 'jenis' => 'drum_kabel']);
        $actor = User::factory()->create();

        $this->asThc(function () use ($actor, $warehouse, $serialised, $drum): void {
            $inventory = app(MaterialInventoryService::class);
            $inventory->receive($actor, $warehouse, $serialised->id, '1', 'Saldo awal', 'SN-DASHBOARD-001');
            $inventory->receive($actor, $warehouse, $drum->id, '100', 'Saldo awal', null, 'DRM-DASHBOARD-001');
        });

        $user = $this->mitraUser(['read_dashboard', 'read_master_data'], $mitra);
        $response = $this->actingAs($user)->get(route('mitra.dashboard'))->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), 'Material Ber-SN Dashboard'));
        $this->assertSame(1, substr_count($response->getContent(), 'Material Drum Dashboard'));
    }

    public function test_login_page_contains_variant_a_branding_and_complete_field_states(): void
    {
        $this->get('/masuk')
            ->assertOk()
            ->assertSee('PMS THC')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('aria-invalid="false"', false)
            ->assertSee('responsive', false);

        $this->from('/masuk')
            ->post('/masuk', ['email' => 'not-an-email', 'password' => ''])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_authenticated_mitra_can_logout(): void
    {
        $user = $this->mitraUser(['read_dashboard']);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function mitraUser(array $permissions, ?Mitra $mitra = null): User
    {
        $mitra ??= Mitra::factory()->create();
        $grup = Grup::factory()->create(['nama' => 'Mitra '.Str::random(4)]);
        $grup->izins()->attach(collect($permissions)->map(fn (string $kode): int => Izin::query()->firstOrCreate(
            ['kode' => $kode],
            ['nama' => $kode],
        )->id)->all());

        return User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $grup->id,
            'password' => 'rahasia-benar',
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
