<?php

namespace App\Services;

use App\Contracts\WahaClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WahaHttpClient implements WahaClient
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('waha.url'), '/'))
            ->withToken((string) config('waha.api_key'))
            ->timeout((int) config('waha.timeout', 10));
    }

    public function sendText(string $to, string $text): void
    {
        try {
            $response = $this->client()->post('/api/sendText', ['session' => config('waha.session', 'default'), 'chatId' => $to.'@c.us', 'text' => $text]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('WAHA tidak dapat dihubungi.', 0, $exception);
        }
        if ($response->failed()) {
            throw new RuntimeException('WAHA menolak pengiriman pesan.');
        }
    }

    public function sessionStatus(string $session): array
    {
        $response = $this->client()->get('/api/sessions/'.$session);
        if ($response->failed()) {
            throw new RuntimeException('Status WAHA tidak tersedia.');
        }

        return $response->json();
    }

    public function restart(string $session): void
    {
        $response = $this->client()->post('/api/sessions/'.$session.'/restart');
        if ($response->failed()) {
            throw new RuntimeException('Sesi WAHA gagal dimulai ulang.');
        }
    }
}
