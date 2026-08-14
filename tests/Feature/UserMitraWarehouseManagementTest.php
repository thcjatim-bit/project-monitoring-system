<?php

namespace Tests\Feature;

use App\Contracts\WahaClient;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_onboarding_rolls_back_when_whatsapp_delivery_fails(): void
    {
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
            'kode' => 'MTR-ROLLBACK', 'nama' => 'Rollback', 'admin_name' => 'Admin',
            'admin_email' => 'rollback@example.com', 'no_wa' => '628123456789',
        ])->assertStatus(500);

        $this->assertDatabaseMissing('mitras', ['kode' => 'MTR-ROLLBACK']);
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
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
