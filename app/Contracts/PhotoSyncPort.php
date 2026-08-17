<?php

namespace App\Contracts;

interface PhotoSyncPort
{
    public function copy(string $sourcePath, string $destination): string;
}
