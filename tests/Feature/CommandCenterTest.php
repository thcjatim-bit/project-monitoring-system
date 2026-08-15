<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Mitra;
use App\Support\TenantDatabaseContext;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_thc_with_read_dashboard_can_open_the_command_center(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Command Center');
    }

    public function test_mitra_with_read_dashboard_is_forbidden_from_the_command_center(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_dashboard');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_thc_without_read_dashboard_is_forbidden_even_by_direct_url(): void
    {
        $thc = $this->userWithPermissions(null);

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_command_center_shows_only_submitted_requests_requiring_thc_decision(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_material_request', 'create_material_request');

        $this->actingAs($mitraUser)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 4]],
        ])->assertRedirect('/material-requests');
        $submitted = MaterialRequest::query()->firstOrFail();

        $this->actingAs($mitraUser)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 2]],
        ])->assertRedirect('/material-requests');
        $approved = MaterialRequest::query()->latest('id')->firstOrFail();
        $this->asThc(fn () => $approved->update(['status' => 'disetujui']));

        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_material_request');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<strong>1</strong>', false)
            ->assertSee('Request Material menunggu keputusan')
            ->assertSee('Request Material #'.$submitted->id)
            ->assertDontSee('Request Material #'.$approved->id);
    }

    public function test_command_center_navigation_only_contains_modules_allowed_by_permissions(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_material_request');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('material-requests.index').'"', false)
            ->assertDontSee('href="'.route('projects.index').'"', false)
            ->assertDontSee('href="'.route('admin.users').'"', false)
            ->assertDontSee('href="'.route('admin.warehouses').'"', false);
    }

    public function test_command_center_hides_request_material_panel_without_read_permission(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Request Material yang membutuhkan keputusan')
            ->assertDontSee('Request Material menunggu keputusan');
    }

    public function test_command_center_uses_read_only_queue_and_detail_links(): void
    {
        [$submitted] = $this->createSubmittedRequest();
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_material_request');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('material-requests.index').'"', false)
            ->assertSee('href="'.route('material-requests.show', $submitted).'"', false)
            ->assertDontSee('Setujui')
            ->assertDontSee('Tolak')
            ->assertDontSee('method="POST" action="'.route('material-requests.approve', $submitted).'"', false);
    }

    public function test_mitra_cannot_open_another_mitras_request_detail(): void
    {
        [$requestA, $userA] = $this->createSubmittedRequest();
        [$requestB] = $this->createSubmittedRequest();

        $this->actingAs($userA)
            ->get('/material-requests/'.$requestB->id)
            ->assertNotFound();

        $this->assertNotSame($requestA->mitra_id, $requestB->mitra_id);
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

    /** @return array{MaterialRequest, User} */
    private function createSubmittedRequest(): array
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_material_request', 'create_material_request');

        $this->actingAs($mitraUser)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 4]],
        ])->assertRedirect('/material-requests');

        return [MaterialRequest::query()->firstOrFail(), $mitraUser];
    }
}
