<?php

namespace Tests\Feature;

use App\Contracts\CommandRunner;
use App\Services\RclonePhotoSyncPort;
use Mockery;
use Tests\TestCase;

class RclonePhotoSyncPortTest extends TestCase
{
    public function test_copy_uses_non_destructive_copyto_then_returns_drive_link(): void
    {
        $runner = Mockery::mock(CommandRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->with([
                'copyto',
                '/srv/pms/storage/app/private/project-photos/photo.jpg',
                'gdrive-public:Foto Pekerjaan/PRJ-2608-0001/deployment/2026-08-12/photo.jpg',
                '--checksum',
            ])
            ->andReturn('');
        $runner->shouldReceive('run')
            ->once()
            ->with([
                'link',
                'gdrive-public:Foto Pekerjaan/PRJ-2608-0001/deployment/2026-08-12/photo.jpg',
                '--expire',
                '0',
            ])
            ->andReturn('https://drive.example/photo.jpg');

        $port = new RclonePhotoSyncPort($runner, 'gdrive-public:Foto Pekerjaan');

        $this->assertSame(
            'https://drive.example/photo.jpg',
            $port->copy(
                '/srv/pms/storage/app/private/project-photos/photo.jpg',
                'PRJ-2608-0001/deployment/2026-08-12/photo.jpg',
            ),
        );
    }
}
