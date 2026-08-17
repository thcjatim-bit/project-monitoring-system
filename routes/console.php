<?php

use App\Services\ProjectPhotoSyncService;
use App\Support\TenantDatabaseContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('photos:sync', function (ProjectPhotoSyncService $service, TenantDatabaseContext $tenant): int {
    $tenant->set(null, true);

    try {
        $summary = $service->syncPending();
        $this->info(sprintf(
            'Photo sync complete: %d discovered, %d synced, %d failed.',
            $summary['discovered'],
            $summary['synced'],
            $summary['failed'],
        ));

        return $summary['failed'] === 0 ? 0 : 1;
    } finally {
        $tenant->set(null, false);
    }
})->purpose('Synchronize pending project photos to Google Drive');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
