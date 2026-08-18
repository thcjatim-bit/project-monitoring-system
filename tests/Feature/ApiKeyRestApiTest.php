<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectTimeline;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ApiKeyRestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_thc_can_create_a_key_once_reveal_its_plaintext_and_revoke_it(): void
    {
        $thc = User::factory()->create([
            'mitra_id' => null,
            'grup_id' => $this->groupWith('manage_api_keys')->id,
        ]);

        $response = $this->actingAs($thc)->post(route('admin.api-keys.store'), [
            'name' => 'Internal dashboard',
            'expires_in_days' => 90,
        ]);

        $response
            ->assertOk()
            ->assertSee('pms_live_', false)
            ->assertSee('Internal dashboard');

        $key = ApiKey::query()->firstOrFail();
        $plaintext = (string) $response->viewData('plaintext');

        $this->assertStringStartsWith('pms_live_', $plaintext);
        $this->assertSame(hash('sha256', $plaintext), $key->key_hash);
        $this->assertNotSame($plaintext, $key->key_hash);

        $this->actingAs($thc)
            ->get(route('admin.api-keys.index'))
            ->assertOk()
            ->assertDontSee($plaintext, false)
            ->assertDontSee($key->key_hash, false);

        $this->actingAs($thc)
            ->patch(route('admin.api-keys.revoke', $key))
            ->assertRedirect(route('admin.api-keys.index'));

        $this->assertNotNull($key->fresh()->revoked_at);
    }

    public function test_api_rejects_missing_invalid_revoked_and_unpermitted_keys_without_data(): void
    {
        $this->getJson('/api/v1/projects')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'api_key_invalid');

        $this->withHeader('Authorization', 'Bearer pms_live_invalid')
            ->getJson('/api/v1/projects')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'api_key_invalid');

        [$plaintext, $apiKey, $actor] = $this->credential();
        app(ApiKeyService::class)->revoke($apiKey, $actor);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'api_key_invalid');

        $unpermitted = $this->inThc(fn (): ApiKey => ApiKey::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'No read permission',
            'key_hash' => hash('sha256', 'pms_live_no_permission'),
            'permissions' => [],
            'expires_at' => now()->addDay(),
        ]));

        $this->withHeader('Authorization', 'Bearer pms_live_no_permission')
            ->getJson('/api/v1/projects')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'api_permission_denied');

        $this->assertNotNull($unpermitted->fresh());
    }

    public function test_mitra_key_is_scoped_by_rls_and_business_identifiers(): void
    {
        [$mitraA, $mitraB, $projectA, $projectB] = $this->inThc(function (): array {
            $mitraA = Mitra::factory()->create(['kode' => 'MTR-API-A']);
            $mitraB = Mitra::factory()->create(['kode' => 'MTR-API-B']);

            return [$mitraA, $mitraB, $this->project($mitraA, 'PRJ-API-A'), $this->project($mitraB, 'PRJ-API-B')];
        });
        [$plaintext] = $this->credential($mitraA->id);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonPath('meta.scope.type', 'mitra')
            ->assertJsonPath('meta.scope.mitra', 'MTR-API-A')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_project', 'PRJ-API-A')
            ->assertJsonMissing(['id_project' => 'PRJ-API-B']);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects/PRJ-API-B')
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'resource_not_found');

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects?filter[mitra]=MTR-API-B')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'scope_violation');

        $this->assertSame($mitraA->id, $projectA->mitra_id);
        $this->assertSame($mitraB->id, $projectB->mitra_id);
    }

    public function test_api_read_surface_uses_versioned_envelope_signed_cursor_and_rejects_writes(): void
    {
        $mitra = $this->inThc(fn (): Mitra => Mitra::factory()->create(['kode' => 'MTR-API-C']));
        $project = $this->inThc(fn (): Project => $this->project($mitra, 'PRJ-API-C'));
        [$plaintext] = $this->credential();

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects?page[size]=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['api_version', 'read_at', 'reporting_as_of', 'scope', 'filters', 'pagination', 'request_id'], 'links'])
            ->assertJsonPath('meta.api_version', 'v1')
            ->assertJsonPath('meta.pagination.size', 1);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->postJson('/api/v1/projects', [])
            ->assertStatus(405)
            ->assertJsonPath('errors.0.code', 'method_not_allowed');

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects?filter[unknown]=value')
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'invalid_filter');

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects/'.$project->id_project.'?include=steps,curve')
            ->assertOk()
            ->assertJsonPath('data.id_project', 'PRJ-API-C')
            ->assertJsonStructure(['data' => ['steps', 'curve']])
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.mitra.id');
    }

    public function test_portfolio_activity_excludes_internal_comments_and_photo_links_exclude_server_paths(): void
    {
        [$mitra, $project, $actor] = $this->inThc(function (): array {
            $mitra = Mitra::factory()->create(['kode' => 'MTR-API-D']);
            $project = $this->project($mitra, 'PRJ-API-D');
            $actor = User::factory()->create(['mitra_id' => null]);
            ProjectTimeline::query()->create([
                'mitra_id' => $mitra->id,
                'project_id' => $project->id,
                'actor_id' => $actor->id,
                'type' => 'internal_note',
                'body' => 'SECRET INTERNAL COMMENT',
            ]);
            ProjectTimeline::query()->create([
                'mitra_id' => $mitra->id,
                'project_id' => $project->id,
                'actor_id' => $actor->id,
                'type' => 'comment',
                'body' => 'Public project update',
            ]);
            $step = $project->steps()->where('step', 'survey')->firstOrFail();
            ProjectPhoto::query()->create([
                'mitra_id' => $mitra->id,
                'project_id' => $project->id,
                'project_step_id' => $step->id,
                'uploaded_by' => $actor->id,
                'original_name' => 'evidence.jpg',
                'stored_path' => 'private/server/path/evidence.jpg',
                'mime_type' => 'image/jpeg',
                'original_size' => 1024,
                'drive_url' => 'https://drive.example/folder/evidence',
            ]);

            return [$mitra, $project, $actor];
        });
        [$plaintext] = $this->credential();

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/portfolio')
            ->assertOk()
            ->assertJsonFragment(['body' => 'Public project update'])
            ->assertJsonMissing(['body' => 'SECRET INTERNAL COMMENT']);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects/'.$project->id_project.'?include=photo_links')
            ->assertOk()
            ->assertJsonPath('data.photo_links.0.url', 'https://drive.example/folder/evidence')
            ->assertJsonMissing(['url' => 'private/server/path/evidence.jpg']);

        $this->assertSame($mitra->id, $project->mitra_id);
        $this->assertNotNull($actor->id);
    }

    public function test_all_curated_read_resources_have_success_envelopes(): void
    {
        $this->inThc(fn (): Project => $this->project(Mitra::factory()->create(), 'PRJ-API-E'));
        [$plaintext] = $this->credential();
        $headers = ['Authorization' => 'Bearer '.$plaintext];

        foreach ([
            '/api/v1/portfolio',
            '/api/v1/portfolio/projects',
            '/api/v1/portfolio/decision-queue',
            '/api/v1/projects',
            '/api/v1/stocks',
            '/api/v1/material-requests',
            '/api/v1/material-transactions',
            '/api/v1/material-reconciliations',
            '/api/v1/mitra-service-prices',
        ] as $uri) {
            $this->withHeaders($headers)->getJson($uri)->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
        }
    }

    public function test_api_request_resets_database_tenant_context_after_completion(): void
    {
        [$plaintext] = $this->credential();

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/projects')
            ->assertOk();

        $settings = DB::selectOne("select current_setting('app.mitra_id', true) as mitra_id, current_setting('app.is_thc', true) as is_thc");
        $this->assertSame('', (string) $settings->mitra_id);
        $this->assertSame('off', (string) $settings->is_thc);
    }

    /** @return array{string,ApiKey,User} */
    private function credential(?int $mitraId = null): array
    {
        $actor = User::factory()->create([
            'mitra_id' => null,
            'grup_id' => $this->groupWith('manage_api_keys')->id,
        ]);
        $credential = $this->inThc(fn () => app(ApiKeyService::class)->create($actor, 'API test key', $mitraId));

        return [$credential->plaintext, $credential->apiKey, $actor];
    }

    private function project(Mitra $mitra, string $identifier): Project
    {
        return Project::query()->create([
            'id_project' => $identifier,
            'nama' => 'Project '.$identifier,
            'mitra_id' => $mitra->id,
            'status_project' => 'aktif',
        ]);
    }

    private function inThc(Closure $callback): mixed
    {
        $context = app(TenantDatabaseContext::class);
        $context->set(null, true);
        try {
            return $callback();
        } finally {
            $context->set(null, false);
        }
    }

    private function groupWith(string ...$permissions): Grup
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::query()->firstOrCreate(['kode' => $permission], ['nama' => $permission])->id,
        )->all());

        return $group;
    }
}
