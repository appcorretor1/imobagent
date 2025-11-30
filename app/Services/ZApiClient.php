<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZApiClient
{
    public function sendText(string $phone, string $message): array
    {
        $url = sprintf(
            '%s/instances/%s/token/%s/send-text',
            rtrim(config('services.zapi.base_url'), '/'),
            config('services.zapi.instance'),
            config('services.zapi.token')
        );

        $payload = [
            'phone'   => $phone,       // ex: 5562XXXXXXXX
            'message' => $message,
        ];

        Log::info('ZAPI.request', ['url' => $url, 'payload' => $payload]);

        $response = Http::asJson()
            ->timeout(20)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post($url, $payload);

        Log::info('ZAPI.response', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if ($response->failed()) {
            Log::error('ZAPI.failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'json'   => $response->json(),
        ];
    }
}
