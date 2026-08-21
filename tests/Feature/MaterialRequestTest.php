<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MaterialRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_can_submit_a_request_for_its_own_material_need(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $user = $this->userWith('create_material_request', $mitra);

        $this->actingAs($user)
            ->post('/material-requests', [
                'catatan' => 'Kebutuhan untuk pekerjaan lapangan',
                'items' => [
                    ['material_id' => $material->id, 'qty' => '12.500'],
                ],
            ])
            ->assertRedirect('/material-requests');

        $request = MaterialRequest::query()->with('items')->firstOrFail();

        $this->assertSame($mitra->id, $request->mitra_id);
        $this->assertSame($user->id, $request->requested_by);
        $this->assertSame('diajukan', $request->status);
        $this->assertSame('12.500', $request->items->first()->qty);
        $this->assertSame($mitra->id, $request->items->first()->mitra_id);
    }

    public function test_request_rows_are_isolated_between_mitras_even_for_raw_queries(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $material = Material::factory()->create();
        $userA = $this->userWith('create_material_request', $mitraA);
        $userB = $this->userWith('create_material_request', $mitraB);

        $this->actingAs($userB)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 3]],
        ])->assertRedirect('/material-requests');
        $this->actingAs($userA)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 4]],
        ])->assertRedirect('/material-requests');
        $requestA = MaterialRequest::query()->where('mitra_id', $mitraA->id)->firstOrFail();

        $this->actingAs($userA)->get('/material-requests')->assertOk();

        $this->assertSame([$requestA->id], DB::table('material_requests')->pluck('id')->all());

        $this->expectException(QueryException::class);
        DB::table('material_requests')->where('id', $requestA->id)->update(['mitra_id' => $mitraB->id]);
    }

    public function test_request_list_shows_project_context_and_explicitly_marks_requests_without_project(): void
    {
        $mitra = Mitra::factory()->create();
        $requester = $this->userWith('create_material_request', $mitra);
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-0093',
            'nama' => 'Project Traceability',
            'mitra_id' => $mitra->id,
            'status_project' => 'aktif',
        ]));

        $this->asThc(function () use ($mitra, $requester, $project): void {
            MaterialRequest::query()->create([
                'mitra_id' => $mitra->id,
                'project_id' => $project->id,
                'requested_by' => $requester->id,
                'status' => 'diajukan',
            ]);
            MaterialRequest::query()->create([
                'mitra_id' => $mitra->id,
                'project_id' => null,
                'requested_by' => $requester->id,
                'status' => 'diajukan',
            ]);
        });

        $thc = User::factory()->create([
            'mitra_id' => null,
            'grup_id' => $this->groupWith('read_material_request')->id,
        ]);

        $this->actingAs($thc)
            ->get('/material-requests')
            ->assertOk()
            ->assertSee('PRJ-2608-0093 — Project Traceability')
            ->assertSee('Tanpa Project')
            ->assertSee('ui-badge', false)
            ->assertDontSee('href="'.route('projects.show', $project).'"', false);

        $thcWithProjectRead = User::factory()->create([
            'mitra_id' => null,
            'grup_id' => $this->groupWith('read_material_request', 'read_project')->id,
        ]);

        $this->actingAs($thcWithProjectRead)
            ->get('/material-requests')
            ->assertSee('href="'.route('projects.show', $project).'"', false);
    }

    public function test_request_item_cannot_be_attached_to_another_mitras_request(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $material = Material::factory()->create();
        $userA = $this->userWith('create_material_request', $mitraA);
        $userB = $this->userWith('create_material_request', $mitraB);

        $this->actingAs($userB)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 3]],
        ])->assertRedirect('/material-requests');
        $requestB = MaterialRequest::query()->firstOrFail();

        $this->actingAs($userA)->get('/material-requests')->assertOk();

        $this->expectException(QueryException::class);
        DB::table('material_request_items')->insert([
            'material_request_id' => $requestB->id,
            'mitra_id' => $mitraA->id,
            'material_id' => $material->id,
            'qty' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_thc_can_approve_a_submitted_request(): void
    {
        [$request, $thc] = $this->submittedRequest();

        $this->actingAs($thc)
            ->patch("/material-requests/{$request->id}/approve", ['catatan' => 'Disetujui untuk pengiriman'])
            ->assertRedirect('/material-requests');

        $request->refresh();
        $this->assertSame('disetujui', $request->status);
        $this->assertSame($thc->id, $request->decided_by);
        $this->assertSame('Disetujui untuk pengiriman', $request->decision_note);
        $this->assertNotNull($request->decided_at);
    }

    public function test_thc_can_reject_a_submitted_request(): void
    {
        [$request, $thc] = $this->submittedRequest();

        $this->actingAs($thc)
            ->patch("/material-requests/{$request->id}/reject", ['catatan' => 'Data kebutuhan belum lengkap'])
            ->assertRedirect('/material-requests');

        $request->refresh();
        $this->assertSame('ditolak', $request->status);
        $this->assertSame($thc->id, $request->decided_by);
        $this->assertSame('Data kebutuhan belum lengkap', $request->decision_note);
    }

    public function test_users_without_decision_authority_cannot_approve_or_reject(): void
    {
        [$request] = $this->submittedRequest();
        $unauthorizedThc = User::factory()->create([
            'mitra_id' => null,
            'grup_id' => $this->groupWith('read_dashboard')->id,
        ]);
        $mitraUser = User::query()->findOrFail($request->requested_by);

        $this->actingAs($unauthorizedThc)
            ->patch("/material-requests/{$request->id}/approve")
            ->assertForbidden();

        $this->actingAs($mitraUser)
            ->patch("/material-requests/{$request->id}/reject")
            ->assertForbidden();

        $this->assertSame('diajukan', $request->fresh()->status);
    }

    public function test_only_submitted_requests_can_receive_a_decision(): void
    {
        [$request, $thc] = $this->submittedRequest();

        $this->actingAs($thc)->patch("/material-requests/{$request->id}/approve");

        $this->actingAs($thc)
            ->patch("/material-requests/{$request->id}/reject")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame('disetujui', $request->fresh()->status);
    }

    /** @return array{MaterialRequest, User} */
    private function submittedRequest(): array
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $mitraUser = $this->userWith('create_material_request', $mitra);
        $thc = User::factory()->create([
            'mitra_id' => null,
            'grup_id' => $this->groupWith('approve_material_request')->id,
        ]);

        $this->actingAs($mitraUser)
            ->post('/material-requests', ['items' => [['material_id' => $material->id, 'qty' => 2]]])
            ->assertRedirect('/material-requests');

        return [MaterialRequest::query()->firstOrFail(), $thc];
    }

    private function userWith(string $permission, Mitra $mitra): User
    {
        $permissions = [$permission];
        if ($permission === 'create_material_request') {
            $permissions[] = 'read_material_request';
        }

        return User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith(...$permissions)->id,
        ]);
    }

    private function groupWith(string ...$permissions): Grup
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::query()->firstOrCreate(['kode' => $permission], ['nama' => $permission])->id,
        )->all());

        return $group;
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
