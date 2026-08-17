<?php

namespace App\Providers;

use App\Contracts\CommandRunner;
use App\Contracts\PhotoSyncPort;
use App\Contracts\WahaClient;
use App\Services\RclonePhotoSyncPort;
use App\Services\WahaHttpClient;
use App\Support\TenantDatabaseContext;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantDatabaseContext::class);
        $this->app->bind(WahaClient::class, WahaHttpClient::class);
        $this->app->singleton(CommandRunner::class, function (): CommandRunner {
            return new class implements CommandRunner
            {
                public function run(array $arguments): string
                {
                    $result = Process::forever()->run([
                        (string) config('photo_sync.rclone_binary', 'rclone'),
                        ...$arguments,
                    ]);

                    if ($result->failed()) {
                        $error = trim($result->errorOutput());

                        throw new RuntimeException($error !== '' ? $error : 'rclone command failed.');
                    }

                    return $result->output();
                }
            };
        });
        $this->app->bind(PhotoSyncPort::class, function (): PhotoSyncPort {
            return new RclonePhotoSyncPort(
                app(CommandRunner::class),
                (string) config('photo_sync.remote_root'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'pgsql') {
                app(TenantDatabaseContext::class)->set(null, false);
            }
        });
    }
}
