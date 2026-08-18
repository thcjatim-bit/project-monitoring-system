<?php

namespace App\Support;

use Illuminate\Support\Collection;

final readonly class ApiPage
{
    public function __construct(
        public Collection $items,
        public int $size,
        public bool $hasMore,
        public ?string $nextCursor,
        public ?string $previousCursor = null,
    ) {}
}
