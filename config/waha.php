<?php

return [
    'url' => env('WAHA_URL', 'http://127.0.0.1:3000'),
    'api_key' => env('WAHA_API_KEY'),
    'webhook_secret' => env('WAHA_WEBHOOK_SECRET'),
    'session' => env('WAHA_SESSION', 'default'),
    'timeout' => env('WAHA_TIMEOUT', 10),
];
