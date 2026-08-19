<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
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
            ->assertDontSee('Portfolio')
            ->assertDontSee('Simpan perubahan')
            ->assertDontSee('Hapus');

        $this->get(route('projects.show', $projectA))->assertOk();
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
        $grup->izins()->attach(collect($permissions)->map(fn (string $kode): int => Izin::factory()->create(['kode' => $kode])->id)->all());

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
