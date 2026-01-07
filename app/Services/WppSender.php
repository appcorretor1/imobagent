<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WppSender
{
    private string $baseUrl;
    private string $instance;
    private string $token;

   public function __construct()
{
    $this->baseUrl = rtrim((string) config('services.zapi.base_url', 'https://api.z-api.io'), '/');

    // ✅ NÃO dependa só de services.php / ZAPI_INSTANCE e ZAPI_TOKEN
    $this->instance = (string) (
        config('services.zapi.instance')         // ZAPI_INSTANCE (se existir)
        ?: env('ZAPI_INSTANCE_ID')               // ✅ seu .env atual
        ?: env('ZAPI_INSTANCE')                  // fallback extra
        ?: ''
    );

    $this->token = (string) (
        config('services.zapi.token')            // ZAPI_TOKEN (se existir)
        ?: config('services.zapi.client_token')  // ZAPI_CLIENT_TOKEN (já existe no services.php)
        ?: env('ZAPI_CLIENT_TOKEN')              // ✅ seu .env atual
        ?: env('ZAPI_PATH_TOKEN')                // ✅ seu .env atual (caso seja este)
        ?: ''
    );
}
private function ensureConfigured(): ?array
{
    if (!$this->baseUrl || !$this->instance || !$this->token) {
        \Log::error('Z-API não configurado no WppSender', [
            'base_url' => $this->baseUrl ?: null,
            'instance' => $this->instance ?: null,
            'token_set' => (bool) $this->token,
        ]);

        return [
            'ok' => false,
            'error' => 'Z-API não configurado (base_url/instance/token)',
        ];
    }

    return null;
}


    /**
     * Envia QUALQUER arquivo do S3 como DOCUMENTO no WhatsApp.
     * Mantém qualidade (evita compressão de imagem/vídeo).
     *
     * @param string      $phone   Ex.: 55629...
     * @param string      $s3Path  Caminho no S3 (ex.: tenants/1/arquivo.pdf)
     * @param string|null $fileName Nome exibido no WhatsApp
     * @param int         $expiryMinutes Validade do link temporário
     */
    public function sendFileFromS3(string $phone, string $s3Path, ?string $fileName = null, int $expiryMinutes = 10): array
    {   
if ($err = $this->ensureConfigured()) {
        return $err;
    }

        $fileName = $fileName ?: basename($s3Path);
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Whitelist básica de extensões (adicione mais se quiser)
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

        // URL temporária S3 (sem expor publicamente)
        $tempUrl = Storage::disk('s3')->temporaryUrl(
            $s3Path,
            now()->addMinutes($expiryMinutes),
            [
                // força content-disposition legível
                'ResponseContentDisposition' => 'inline; filename="' . $fileName . '"',
            ]
        );

        // Endpoint "send-document/{extension}"
        $url = "{$this->baseUrl}/instances/{$this->instance}/token/{$this->token}/send-document/{$ext}";

        $payload = [
            'phone'    => $phone,
            'document' => $tempUrl,    // também aceita Base64 se quiser
            'fileName' => $fileName,
        ];

        $resp = Http::asJson()->post($url, $payload);

        if ($resp->failed()) {
            return [
                'ok' => false,
                'status' => $resp->status(),
                'error' => $resp->json() ?: $resp->body(),
                'endpoint' => $url,
                'payload' => $payload,
            ];
        }

        return ['ok' => true, 'data' => $resp->json()];
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
        return [
            'ok'      => false,
            'status'  => $resp->status(),
            'error'   => $resp->json() ?: $resp->body(),
            'endpoint'=> $url,
            'payload' => $payload,
        ];
    }

    return ['ok' => true, 'data' => $resp->json()];
}


}
