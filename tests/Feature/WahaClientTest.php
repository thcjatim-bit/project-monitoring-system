<?php

namespace Tests\Feature;

use App\Services\WahaHttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WahaClientTest extends TestCase
{
    public function test_sends_text_to_waha_contract(): void
    {
        Http::fake(['http://waha.test/*' => Http::response(['ok' => true], 201)]);
        config(['waha.url' => 'http://waha.test', 'waha.api_key' => 'secret']);
        app(WahaHttpClient::class)->sendText('628123456789', 'Halo');
        Http::assertSent(fn (Request $request) => $request->url() === 'http://waha.test/api/sendText' && $request['chatId'] === '628123456789@c.us' && $request->hasHeader('Authorization', 'Bearer secret'));
    }

    public function test_non_success_response_throws(): void
    {
        Http::fake(['http://waha.test/*' => Http::response([], 500)]);
        config(['waha.url' => 'http://waha.test']);
        $this->expectException(\RuntimeException::class);
        app(WahaHttpClient::class)->sendText('628123456789', 'Halo');
    }
}
