<?php

namespace App\Support;

use App\Models\ApiKey;

final readonly class ApiKeyCredential
{
    public function __construct(
        public ApiKey $apiKey,
        public string $plaintext,
    ) {}
}
