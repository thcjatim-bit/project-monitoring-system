<?php

namespace Tests\Feature;

use App\Contracts\WahaClient;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class UserMitraWarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_thc_can_onboard_mitra_and_sends_generated_credentials(): void
    {
        $admin = $this->adminWith('manage_mitras');
        $sent = [];
        $this->app->bind(WahaClient::class, function () use (&$sent): WahaClient {
            return new class($sent) implements WahaClient
            {
                public function __construct(private array &$sent) {}

                public function sendText(string $to, string $text): void
                {
                    $this->sent[] = [$to, $text];
                }

                public function sessionStatus(string $session): array
                {
                    return [];
                }

                public function restart(string $session): void {}
            };
        });

        $this->actingAs($admin)->post('/admin/mitras', [
            'kode' => 'MTR-NEW', 'nama' => 'Mitra Baru', 'admin_name' => 'Admin Baru',
            'admin_email' => 'admin.baru@example.com', 'no_wa' => '628123456789',
        ])->assertRedirect();

        $this->assertDatabaseHas('mitras', ['kode' => 'MTR-NEW']);
        $this->assertDatabaseHas('users', ['email' => 'admin.baru@example.com', 'aktif' => true]);
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('admin.baru@example.com', $sent[0][1]);
    }

    public function test_mitra_onboarding_generates_a_monthly_code_when_code_is_empty(): void
    {
        CarbonImmutable::setTestNow('2026-08-19 10:00:00');
        try {
            $admin = $this->adminWith('manage_mitras');
            $this->app->bind(WahaClient::class, fn () => new class implements WahaClient
            {
                public function sendText(string $to, string $text): void {}

                public function sessionStatus(string $session): array
                {
                    return [];
                }

                public function restart(string $session): void {}
            });

            $this->actingAs($admin)->post('/admin/mitras', [
                'kode' => '', 'nama' => 'Mitra Otomatis', 'admin_name' => 'Admin Otomatis',
                'admin_email' => 'admin.otomatis@example.com', 'no_wa' => '628123456789',
            ])->assertRedirect();

            $this->assertDatabaseHas('mitras', ['kode' => 'MTR-2608-0001']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_user_page_is_focused_on_user_management_and_mitra_page_owns_onboarding(): void
    {
        $admin = $this->adminWith('manage_users');
        $mitraAdmin = $this->adminWith('manage_mitras');
        $inactive = Mitra::factory()->create(['aktif' => false, 'nama' => 'Mitra Lama']);
        User::factory()->create(['mitra_id' => $inactive->id]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Edit User')
            ->assertSee('Mitra Lama')
            ->assertDontSee('Onboarding Mitra');

        $this->actingAs($mitraAdmin)->get('/admin/mitras')
            ->assertOk()
            ->assertSee('Onboarding Mitra')
            ->assertSee('Kode Mitra');
    }

    public function test_thc_can_edit_a_user_and_a_mitra_with_their_matching_permissions(): void
    {
        $userManager = $this->adminWith('manage_users');
        $mitra = Mitra::factory()->create(['kode' => 'MTR-OLD']);
        $user = User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $userManager->grup_id]);

        $this->actingAs($userManager)->patch('/admin/users/'.$user->id, [
            'name' => 'Nama Diperbarui', 'email' => 'updated@example.com', 'no_wa' => '628123456789',
            'mitra_id' => $mitra->id, 'grup_id' => $user->grup_id,
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nama Diperbarui', 'email' => 'updated@example.com']);

        $mitraManager = $this->adminWith('manage_mitras');
        $this->actingAs($mitraManager)->patch('/admin/mitras/'.$mitra->id, [
            'kode' => 'MTR-NEW', 'nama' => 'Mitra Diperbarui',
        ])->assertRedirect();
        $this->assertDatabaseHas('mitras', ['id' => $mitra->id, 'kode' => 'MTR-NEW', 'nama' => 'Mitra Diperbarui']);
    }

    public function test_user_delete_succeeds_without_history_and_is_rejected_with_a_deactivation_offer_with_history(): void
    {
        $admin = $this->adminWith('manage_users');
        $deletable = User::factory()->create(['mitra_id' => Mitra::factory()->create()->id]);
        $this->actingAs($admin)->delete('/admin/users/'.$deletable->id)->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $deletable->id]);

        $referenced = User::factory()->create(['mitra_id' => Mitra::factory()->create()->id]);
        $this->asThc(function () use ($referenced): void {
            $project = Project::query()->create([
                'id_project' => 'PRJ-2608-0001',
                'nama' => 'Project Referensi',
                'mitra_id' => $referenced->mitra_id,
            ]);
            ProjectTimeline::query()->create([
                'mitra_id' => $referenced->mitra_id,
                'project_id' => $project->id,
                'actor_id' => $referenced->id,
                'type' => 'system_log',
                'event_key' => 'test.reference',
            ]);
        });
        $this->actingAs($admin)->delete('/admin/users/'.$referenced->id)
            ->assertRedirect()
            ->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $referenced->id, 'aktif' => true]);
    }

    public function test_user_delete_protects_self_and_the_last_active_thc_user(): void
    {
        $admin = $this->adminWith('manage_users');
        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)
            ->assertRedirect()
            ->assertSessionHasErrors('delete');

        $last = User::factory()->create(['mitra_id' => null, 'aktif' => true, 'grup_id' => $admin->grup_id]);
        $this->actingAs($admin)->patch('/admin/users/'.$admin->id.'/toggle')->assertRedirect();
        $this->actingAs($last)->delete('/admin/users/'.$last->id)
            ->assertRedirect()
            ->assertSessionHasErrors('delete');
    }

    public function test_mitra_delete_requires_no_users_and_offers_deactivation_when_referenced(): void
    {
        $admin = $this->adminWith('manage_mitras');
        $withUser = Mitra::factory()->create();
        User::factory()->create(['mitra_id' => $withUser->id]);

        $this->actingAs($admin)->delete('/admin/mitras/'.$withUser->id)
            ->assertRedirect()
            ->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('mitras', ['id' => $withUser->id, 'aktif' => true]);

        $empty = Mitra::factory()->create();
        $this->actingAs($admin)->delete('/admin/mitras/'.$empty->id)->assertRedirect();
        $this->assertDatabaseMissing('mitras', ['id' => $empty->id]);
    }

    public function test_a_previously_issued_automatic_code_cannot_be_reassigned_during_mitra_edit(): void
    {
        $admin = $this->adminWith('manage_mitras');
        DB::table('mitra_code_sequences')->insert(['bulan' => '2608', 'nomor_berikutnya' => 2]);
        DB::table('mitra_code_issued')->insert(['kode' => 'MTR-2608-0001']);
        $mitra = Mitra::factory()->create(['kode' => 'MTR-MANUAL']);

        $this->actingAs($admin)->patch('/admin/mitras/'.$mitra->id, [
            'kode' => 'MTR-2608-0001', 'nama' => $mitra->nama,
        ])->assertRedirect()->assertSessionHasErrors('kode');
        $this->assertDatabaseHas('mitras', ['id' => $mitra->id, 'kode' => 'MTR-MANUAL']);
    }

    public function test_onboarding_rolls_back_when_whatsapp_delivery_fails(): void
    {
        CarbonImmutable::setTestNow('2026-08-19 10:00:00');
        $admin = $this->adminWith('manage_mitras');
        $this->app->bind(WahaClient::class, fn () => new class implements WahaClient
        {
            public function sendText(string $to, string $text): void
            {
                throw new \RuntimeException('offline');
            }

            public function sessionStatus(string $session): array
            {
                return [];
            }

            public function restart(string $session): void {}
        });

        $this->actingAs($admin)->post('/admin/mitras', [
            'kode' => '', 'nama' => 'Rollback', 'admin_name' => 'Admin',
            'admin_email' => 'rollback@example.com', 'no_wa' => '628123456789',
        ])->assertStatus(500);

        $this->assertDatabaseMissing('mitras', ['nama' => 'Rollback']);
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);

        $this->app->bind(WahaClient::class, fn () => new class implements WahaClient
        {
            public function sendText(string $to, string $text): void {}

            public function sessionStatus(string $session): array
            {
                return [];
            }

            public function restart(string $session): void {}
        });
        $this->actingAs($admin)->post('/admin/mitras', [
            'kode' => '', 'nama' => 'Rollback Berikutnya', 'admin_name' => 'Admin Berikutnya',
            'admin_email' => 'rollback.berikutnya@example.com', 'no_wa' => '628123456789',
        ])->assertRedirect();
        $this->assertDatabaseHas('mitras', ['kode' => 'MTR-2608-0002']);
        CarbonImmutable::setTestNow();
    }

    public function test_thc_can_deactivate_and_reset_a_user(): void
    {
        $admin = $this->adminWith('manage_users');
        $user = User::factory()->create(['no_wa' => '628123456789', 'aktif' => true, 'password' => 'old-password']);
        $this->app->bind(WahaClient::class, fn () => new class implements WahaClient
        {
            public function sendText(string $to, string $text): void {}

            public function sessionStatus(string $session): array
            {
                return [];
            }

            public function restart(string $session): void {}
        });

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/toggle")->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'aktif' => false]);
        $this->actingAs($admin)->post("/admin/users/{$user->id}/reset")->assertRedirect();
        $this->assertFalse(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_warehouse_operation_requires_permission_and_assignment(): void
    {
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create());
        $user = User::factory()->create();
        $this->assertFalse($user->canOperateWarehouse($warehouse, 'operate_warehouse'));
        $user->grup()->associate($this->groupWith('operate_warehouse'))->save();
        $this->assertFalse($user->fresh()->canOperateWarehouse($warehouse, 'operate_warehouse'));
        $warehouse->users()->attach($user);
        app(TenantDatabaseContext::class)->forUser($user);
        $this->assertTrue($user->fresh()->canOperateWarehouse($warehouse, 'operate_warehouse'));
    }

    public function test_mitra_can_only_query_its_own_warehouses(): void
    {
        [$mitraA, $mitraB] = Mitra::factory()->count(2)->create();
        [$warehouseA, $warehouseB] = $this->asThc(fn () => [
            Warehouse::factory()->create(['mitra_id' => $mitraA->id]),
            Warehouse::factory()->create(['mitra_id' => $mitraB->id]),
        ]);
        $user = User::factory()->create(['mitra_id' => $mitraA->id]);

        $this->actingAs($user)->get('/dashboard');
        app(TenantDatabaseContext::class)->forUser($user);

        $this->assertSame([$warehouseA->id], Warehouse::query()->pluck('id')->all());
        $this->assertNotContains($warehouseB->id, Warehouse::query()->pluck('id')->all());
    }

    private function adminWith(string $permission): User
    {
        return User::factory()->create(['grup_id' => $this->groupWith($permission)->id]);
    }

    private function groupWith(string $permission): Grup
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));

        return $group;
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
