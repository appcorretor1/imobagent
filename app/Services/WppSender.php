<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WppSender
{
    private string $baseUrl;
    private string $instance;
    private string $token;        // token DA INSTÂNCIA (vai no path)
    private string $clientToken;  // client-token (vai no header)

    public function __construct()
    {
        // Z-API base
        $this->baseUrl = rtrim((string) config('services.zapi.base_url', 'https://api.z-api.io'), '/');

        // ID da instância
        $this->instance = (string) (env('ZAPI_INSTANCE_ID') ?: '');

        // Token da instância (o que o painel mostra no campo "Token da instância")
        $this->token = (string) (env('ZAPI_PATH_TOKEN') ?: '');

        // Client token (o que você confirmou que precisa ir no header)
        $this->clientToken = (string) (env('ZAPI_CLIENT_TOKEN') ?: '');
    }

    private function ensureConfigured(): ?array
    {
        if (!$this->baseUrl || !$this->instance || !$this->token || !$this->clientToken) {
            Log::error('Z-API não configurado no WppSender', [
                'base_url'       => $this->baseUrl ?: null,
                'instance'       => $this->instance ?: null,
                'token_set'      => (bool) $this->token,
                'client_set'     => (bool) $this->clientToken,
            ]);

            return [
                'ok' => false,
                'error' => 'Z-API não configurado (base_url/instance/token/client_token)',
            ];
        }

        return null;
    }

    private function http()
    {
        // ✅ Header obrigatório
        return Http::asJson()->withHeaders([
            'Client-Token' => $this->clientToken,
        ]);
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

        $resp = $this->http()->post($url, $payload);

        if ($resp->failed()) {
            Log::error('Z-API falhou ao enviar texto', [
                'status'   => $resp->status(),
                'error'    => $resp->json() ?: $resp->body(),
                'endpoint' => $url,
                'payload'  => $payload,
            ]);

            return [
                'ok'       => false,
                'status'   => $resp->status(),
                'error'    => $resp->json() ?: $resp->body(),
                'endpoint' => $url,
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
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed = [
            // docs
            'pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv',
            // imagens
            'jpg','jpeg','png','gif','webp',
            // vídeos
            'mp4','mov','avi','mkv',
        ];

        if (!in_array($ext, $allowed, true)) {
            return ['ok' => false, 'error' => "Extensão .$ext não suportada para envio como documento."];
        }

        $tempUrl = Storage::disk('s3')->temporaryUrl(
            $s3Path,
            now()->addMinutes($expiryMinutes),
            [
                'ResponseContentDisposition' => 'inline; filename="' . $fileName . '"',
            ]
        );

        $url = "{$this->baseUrl}/instances/{$this->instance}/token/{$this->token}/send-document/{$ext}";

        $payload = [
            'phone'    => $phone,
            'document' => $tempUrl,
            'fileName' => $fileName,
        ];

        $resp = $this->http()->post($url, $payload);

        if ($resp->failed()) {
            Log::error('Z-API falhou ao enviar documento', [
                'status'   => $resp->status(),
                'error'    => $resp->json() ?: $resp->body(),
                'endpoint' => $url,
                'payload'  => $payload,
            ]);

            return [
                'ok'       => false,
                'status'   => $resp->status(),
                'error'    => $resp->json() ?: $resp->body(),
                'endpoint' => $url,
            ];
        }

        return ['ok' => true, 'data' => $resp->json()];
    }
}
