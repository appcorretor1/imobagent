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
    $this->baseUrl  = rtrim((string) config('services.zapi.base_url', ''), '/');
    $this->instance = (string) config('services.zapi.instance', '');
    $this->token    = (string) config('services.zapi.token', '');

    if (!$this->baseUrl || !$this->instance || !$this->token) {
        \Log::error('Z-API config ausente no WppSender', [
            'base_url' => $this->baseUrl ?: null,
            'instance' => $this->instance ?: null,
            'token'    => $this->token ? '***set***' : null,
        ]);

        // Você pode lançar exceção ou só deixar o sendText retornar erro.
        // Eu prefiro exception aqui pra não "falhar silencioso".
        throw new \RuntimeException('Z-API não configurado (base_url/instance/token)');
    }

    \Log::info('ZAPI cfg', [
  'base_url' => config('services.zapi.base_url'),
  'instance' => config('services.zapi.instance'),
  'token_set' => !!config('services.zapi.token'),
]);

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
