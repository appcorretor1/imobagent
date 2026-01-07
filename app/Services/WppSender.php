<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WppSender
{
    private string $baseUrl;
    private string $instance;
    private string $token;

    public function __construct()
    {
        // Base oficial da Z-API
        $this->baseUrl = 'https://api.z-api.io';

        // EXATAMENTE como o painel mostra
        $this->instance = env('ZAPI_INSTANCE_ID', '');
        $this->token    = env('ZAPI_PATH_TOKEN', ''); // ← Token da instância
    }

    private function ensureConfigured(): ?array
    {
        if (!$this->instance || !$this->token) {
            Log::error('Z-API não configurado corretamente', [
                'instance' => $this->instance ?: null,
                'token_set' => (bool) $this->token,
            ]);

            return ['ok' => false, 'error' => 'Z-API não configurado'];
        }
        return null;
    }

    public function sendText(string $phone, string $message): array
    {
        if ($err = $this->ensureConfigured()) {
            return $err;
        }

        $url = "{$this->baseUrl}/instances/{$this->instance}/token/{$this->token}/send-text";

        $payload = [
            'phone'   => $phone,
            'message' => $message,
        ];

        $resp = Http::asJson()->post($url, $payload);

        if ($resp->failed()) {
            Log::error('Z-API erro ao enviar texto', [
                'status' => $resp->status(),
                'error' => $resp->json() ?: $resp->body(),
                'endpoint' => $url,
            ]);

            return [
                'ok' => false,
                'status' => $resp->status(),
                'error' => $resp->json() ?: $resp->body(),
            ];
        }

        return ['ok' => true, 'data' => $resp->json()];
    }

    public function sendFileFromS3(string $phone, string $s3Path, ?string $fileName = null, int $expiryMinutes = 10): array
    {
        if ($err = $this->ensureConfigured()) {
            return $err;
        }

        $fileName = $fileName ?: basename($s3Path);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $tempUrl = Storage::disk('s3')->temporaryUrl(
            $s3Path,
            now()->addMinutes($expiryMinutes),
            ['ResponseContentDisposition' => 'inline; filename="'.$fileName.'"']
        );

        $url = "{$this->baseUrl}/instances/{$this->instance}/token/{$this->token}/send-document/{$ext}";

        $payload = [
            'phone' => $phone,
            'document' => $tempUrl,
            'fileName' => $fileName,
        ];

        $resp = Http::asJson()->post($url, $payload);

        if ($resp->failed()) {
            Log::error('Z-API erro ao enviar arquivo', [
                'status' => $resp->status(),
                'error' => $resp->json() ?: $resp->body(),
            ]);

            return ['ok' => false, 'error' => $resp->json() ?: $resp->body()];
        }

        return ['ok' => true, 'data' => $resp->json()];
    }
}
