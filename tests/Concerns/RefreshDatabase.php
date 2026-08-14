<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase as LaravelRefreshDatabase;

trait RefreshDatabase
{
    use LaravelRefreshDatabase;

    protected function migrateDatabases()
    {
        $this->artisan('migrate:fresh', [
            '--database' => 'migrator',
            '--force' => true,
        ]);
    }
}
