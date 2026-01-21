<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WppProxyController extends Controller
{
    public function sendFromMake(Request $request)
    {
        // Log do que chegou cru
        $raw = $request->getContent();

        $data = $request->all();

        Log::info('WPP PROXY FROM MAKE → payload recebido', [
            'company_id'      => $data['company_id'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'has_message'     => isset($data['message']) && $data['message'] !== '',
            'has_url'         => !empty($data['url'] ?? null) || !empty($data['fileUrl'] ?? null) || !empty($data['file_url'] ?? null),
            'message_preview' => isset($data['message'])
                ? mb_substr((string)$data['message'], 0, 20) . '...'
                : null,
            'fileName'        => $data['fileName'] ?? ($data['filename'] ?? null),
            'mime'            => $data['mime'] ?? null,
        ]);

        // Se o Laravel não conseguir parsear (json vazio), faz parsing "na unha"
        if (empty($data)) {
            $data = [];

            // company_id
            if (preg_match('/"company_id"\s*:\s*"([^"]*)"/', $raw, $m)) {
                $data['company_id'] = $m[1];
            }

            // phone
            if (preg_match('/"phone"\s*:\s*"([^"]*)"/', $raw, $m)) {
                $data['phone'] = $m[1];
            }

            // message (pega tudo até a última aspa)
            if (preg_match('/"message"\s*:\s*"(.*)"/s', $raw, $m)) {
                // remove escapes de barra e aspas
                $msg = stripcslashes($m[1]);
                $data['message'] = $msg;
            }

            // url / fileUrl / file_url
            if (preg_match('/"url"\s*:\s*"([^"]*)"/s', $raw, $m)) {
                $data['url'] = stripcslashes($m[1]);
            }
            if (preg_match('/"fileUrl"\s*:\s*"([^"]*)"/s', $raw, $m)) {
                $data['fileUrl'] = stripcslashes($m[1]);
            }
            if (preg_match('/"file_url"\s*:\s*"([^"]*)"/s', $raw, $m)) {
                $data['file_url'] = stripcslashes($m[1]);
            }

            // fileName
            if (preg_match('/"fileName"\s*:\s*"([^"]*)"/s', $raw, $m)) {
                $data['fileName'] = stripcslashes($m[1]);
            }

            // mime
            if (preg_match('/"mime"\s*:\s*"([^"]*)"/s', $raw, $m)) {
                $data['mime'] = stripcslashes($m[1]);
            }

            // caption
            if (preg_match('/"caption"\s*:\s*"(.*)"/s', $raw, $m)) {
                $cap = stripcslashes($m[1]);
                $data['caption'] = $cap;
            }
        }

        $companyId = $data['company_id'] ?? null;
        $phone     = $data['phone']      ?? null;
        $message   = $data['message']    ?? null;
        $url       = $data['url'] ?? ($data['fileUrl'] ?? ($data['file_url'] ?? null));
        $mime      = $data['mime'] ?? null;
        $fileName  = $data['fileName'] ?? ($data['filename'] ?? null);
        $caption   = $data['caption'] ?? null;

        /**
         * Compatibilidade com o Make "antigo":
         * Ele envia apenas {company_id, phone, message}.
         * Se message for um JSON contendo url/mime/fileName/caption, tratamos como envio de mídia.
         */
        if (!$url && is_string($message) && $message !== '' && str_starts_with(ltrim($message), '{')) {
            $decoded = json_decode($message, true);
            if (is_array($decoded)) {
                $maybeUrl = $decoded['url'] ?? ($decoded['fileUrl'] ?? ($decoded['file_url'] ?? null));
                if (!empty($maybeUrl)) {
                    $url = $maybeUrl;
                    $mime = $mime ?: ($decoded['mime'] ?? null);
                    $fileName = $fileName ?: ($decoded['fileName'] ?? ($decoded['filename'] ?? null));
                    $caption = $caption ?: ($decoded['caption'] ?? null);
                    // evita mandar texto "message" se na verdade é mídia
                    $message = null;
                }
            }
        }

        // 🔸 aceita dois modos:
        // - Texto: company_id + phone + message
        // - Mídia: company_id + phone + url (e opcional: mime/fileName/caption)
        if (!$companyId || !$phone || (!$message && !$url)) {
           Log::warning('WPP PROXY FROM MAKE → campos obrigatórios faltando', [
    'company_id' => $companyId,
    'phone'      => $phone,
    'message'    => $message ? '[RECEIVED]' : null,
    'url'        => $url ? '[RECEIVED]' : null,
]);


            return response()->json([
                'ok'    => false,
                'error' => 'Campos obrigatórios: company_id, phone e (message OU url)',
            ], 422);
        }

        // 🔹 Busca empresa e credenciais Z-API (fallback pro .env)
        $company = Company::find($companyId);

        $baseUrl  = $company->zapi_base_url    ?: config('services.zapi.base_url');
        $instance = $company->zapi_instance_id ?: config('services.zapi.instance');
        // zapi_token é o PATH_TOKEN (token da instância que vai no path da URL)
        $token    = $company->zapi_token ?: config('services.zapi.token', env('ZAPI_PATH_TOKEN', env('ZAPI_TOKEN', '')));

        $clientToken = config('services.zapi.client_token', env('ZAPI_CLIENT_TOKEN', ''));

        // normaliza telefone
        $phone = preg_replace('/\D+/', '', (string) $phone);

        // Se for texto
        if ($message) {
            $zapiUrl = rtrim($baseUrl, '/') . "/instances/{$instance}/token/{$token}/send-text";

            Log::info('Z-API sendFromMake → preparando envio (texto)', [
                'company_id' => $companyId,
                'phone'      => $phone,
                'url'        => $zapiUrl,
            ]);

            try {
                $http = Http::timeout(20);
                if ($clientToken) {
                    $http = $http->withHeaders([
                        'Client-Token' => $clientToken,
                    ]);
                }

                $zapiResp = $http->post($zapiUrl, [
                    'phone'   => $phone,
                    'message' => (string) $message,
                ]);

                Log::info('Z-API sendFromMake → resposta (texto)', [
                    'company_id' => $companyId,
                    'phone'      => $phone,
                    'status'     => $zapiResp->status(),
                    'body'       => $zapiResp->json() ?: $zapiResp->body(),
                ]);

                return response()->json([
                    'ok'       => $zapiResp->successful(),
                    'status'   => $zapiResp->status(),
                    'zapi_raw' => $zapiResp->json() ?: $zapiResp->body(),
                ], $zapiResp->successful() ? 200 : 422);

            } catch (\Throwable $e) {
                Log::error('Z-API sendFromMake → erro ao enviar (texto)', [
                    'company_id' => $companyId,
                    'phone'      => $phone,
                    'error'      => $e->getMessage(),
                ]);

                return response()->json([
                    'ok'    => false,
                    'error' => 'Erro ao enviar texto para Z-API',
                ], 500);
            }
        }

        // Senão, é mídia via URL
        $mediaUrl = (string) $url;
        if (!$fileName) {
            $path = parse_url($mediaUrl, PHP_URL_PATH) ?: $mediaUrl;
            $fileName = basename($path) ?: ('arquivo_' . time());
        }
        $fileName = (string) $fileName;
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mime = $mime ? (string) $mime : null;
        $caption = $caption ? (string) $caption : null;

        // classifica tipo pelo mime/ext
        $mimeL = strtolower((string) $mime);
        $isImage = ($mime && str_starts_with($mimeL, 'image/')) || in_array($ext, ['jpg','jpeg','png','gif','webp'], true);
        $isVideo = ($mime && str_starts_with($mimeL, 'video/')) || in_array($ext, ['mp4','mov','avi','mkv','webm'], true);

        $base = rtrim($baseUrl, '/') . "/instances/{$instance}/token/{$token}";

        if ($isImage) {
            $tries = [
                ['endpoint' => 'send-image', 'payloadKey' => 'image'],
            ];
        } elseif ($isVideo) {
            $tries = [
                ['endpoint' => 'send-video', 'payloadKey' => 'video'],
            ];
        } else {
            $tries = array_values(array_filter([
                $ext ? ['endpoint' => "send-document/{$ext}", 'payloadKey' => 'document'] : null,
                ['endpoint' => 'send-document', 'payloadKey' => 'document'],
                $ext ? ['endpoint' => "send-file/{$ext}", 'payloadKey' => 'file'] : null,
                ['endpoint' => 'send-file', 'payloadKey' => 'file'],
            ]));
        }

        Log::info('Z-API sendFromMake → preparando envio (mídia)', [
            'company_id' => $companyId,
            'phone'      => $phone,
            'fileName'   => $fileName,
            'mime'       => $mime,
            'ext'        => $ext,
            'base'       => $base,
            'tries'      => array_map(fn($t) => $t['endpoint'], $tries),
        ]);

        try {
            $http = Http::asJson()->timeout(25);
            if ($clientToken) {
                $http = $http->withHeaders([
                    'Client-Token' => $clientToken,
                ]);
            }

            $payloadBase = [
                'phone'    => $phone,
                'caption'  => $caption,
                'fileName' => $fileName,
            ];

            foreach ($tries as $t) {
                $payload = $payloadBase + [
                    $t['payloadKey'] => $mediaUrl,
                ];

                $endpointUrl = "{$base}/{$t['endpoint']}";
                $resp = $http->post($endpointUrl, $payload);

                Log::info('Z-API sendFromMake → tentativa mídia', [
                    'endpoint' => $t['endpoint'],
                    'status'   => $resp->status(),
                    'body'     => $resp->json() ?: $resp->body(),
                ]);

                if ($resp->successful()) {
                    $j = $resp->json();
                    $ok = is_array($j) && (
                        ($j['ok'] ?? false) ||
                        isset($j['messageId']) ||
                        isset($j['id']) ||
                        isset($j['zaapId'])
                    );

                    if ($ok) {
                        return response()->json([
                            'ok'        => true,
                            'mode'      => 'media',
                            'endpoint'  => $t['endpoint'],
                            'status'    => $resp->status(),
                            'zapi_raw'  => $j,
                        ]);
                    }
                }
            }

            return response()->json([
                'ok'    => false,
                'mode'  => 'media',
                'error' => 'no_zapi_endpoint_succeeded',
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Z-API sendFromMake → erro ao enviar (mídia)', [
                'company_id' => $companyId,
                'phone'      => $phone,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'ok'    => false,
                'mode'  => 'media',
                'error' => 'Erro ao enviar mídia para Z-API',
            ], 500);
        }
    }
}
