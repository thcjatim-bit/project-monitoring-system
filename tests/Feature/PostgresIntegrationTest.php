<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostgresIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for PostgreSQL integration tests.');
        }

        parent::setUp();
    }

    public function test_a_user_is_persisted_in_postgresql(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
