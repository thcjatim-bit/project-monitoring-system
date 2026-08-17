<?php

namespace App\Services;

use App\Contracts\CommandRunner;
use App\Contracts\PhotoSyncPort;
use InvalidArgumentException;
use RuntimeException;

class RclonePhotoSyncPort implements PhotoSyncPort
{
    private readonly string $remoteRoot;

    public function __construct(
        private readonly CommandRunner $runner,
        string $remoteRoot,
    ) {
        $this->remoteRoot = rtrim(trim($remoteRoot), '/');

        if ($this->remoteRoot === '') {
            throw new InvalidArgumentException('The rclone remote root must be configured.');
        }
    }

    public function copy(string $sourcePath, string $destination): string
    {
        $remotePath = $this->remotePath($destination);

        $this->runner->run([
            'copyto',
            $sourcePath,
            $remotePath,
            '--checksum',
        ]);

        $driveUrl = trim($this->runner->run([
            'link',
            $remotePath,
            '--expire',
            '0',
        ]));

        if ($driveUrl === '') {
            throw new RuntimeException('rclone link returned an empty URL.');
        }

        return $driveUrl;
    }

    private function remotePath(string $destination): string
    {
        $destination = trim($destination, '/');

        if ($destination === '') {
            throw new InvalidArgumentException('The photo sync destination must not be empty.');
        }

        return $this->remoteRoot.'/'.$destination;
    }
}
