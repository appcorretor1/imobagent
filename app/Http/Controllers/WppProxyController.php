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

        // Log detalhado do payload bruto para debug
        Log::info('WPP PROXY FROM MAKE → payload bruto recebido', [
            'raw_length'    => strlen($raw),
            'raw_preview'   => mb_substr($raw, 0, 500),
            'parsed_keys'   => array_keys($data),
            'parsed_count'  => count($data),
        ]);

        Log::info('WPP PROXY FROM MAKE → payload parseado', [
            'company_id'      => $data['company_id'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'has_message'     => isset($data['message']) && $data['message'] !== '',
            'has_url'         => !empty($data['url'] ?? null) || !empty($data['fileUrl'] ?? null) || !empty($data['file_url'] ?? null),
            'message_preview' => isset($data['message'])
                ? mb_substr((string)$data['message'], 0, 100) . '...'
                : null,
            'message_is_json' => isset($data['message']) && is_string($data['message']) && str_starts_with(ltrim($data['message']), '{'),
            'fileName'        => $data['fileName'] ?? ($data['filename'] ?? null),
            'mime'            => $data['mime'] ?? null,
            'caption'         => isset($data['caption']) ? mb_substr((string)$data['caption'], 0, 100) : null,
        ]);

        // Se o Laravel não conseguir parsear (json vazio ou dados inválidos), faz parsing "na unha"
        $needsManualParsing = empty($data) || (!isset($data['company_id']) && !isset($data['phone']));
        
        if ($needsManualParsing && !empty($raw)) {
            Log::info('WPP PROXY FROM MAKE → fazendo parsing manual do raw');
            
            // Tenta parsear como JSON primeiro (pode ter JSON aninhado no message)
            $jsonData = json_decode($raw, true);
            $jsonError = json_last_error();
            
            if ($jsonError === JSON_ERROR_NONE && is_array($jsonData) && !empty($jsonData)) {
                // Se message for string JSON, tenta parsear também
                if (isset($jsonData['message']) && is_string($jsonData['message']) && str_starts_with(ltrim($jsonData['message']), '{')) {
                    $nestedJson = json_decode($jsonData['message'], true);
                    if (is_array($nestedJson)) {
                        // Se for envelope de mídia, extrai os campos diretamente
                        if (isset($nestedJson['__imobagent']) && $nestedJson['__imobagent'] === 'media') {
                            $jsonData['url'] = $nestedJson['url'] ?? ($jsonData['url'] ?? null);
                            $jsonData['mime'] = $nestedJson['mime'] ?? ($jsonData['mime'] ?? null);
                            $jsonData['fileName'] = $nestedJson['fileName'] ?? ($jsonData['fileName'] ?? null);
                            $jsonData['caption'] = $nestedJson['caption'] ?? ($jsonData['caption'] ?? null);
                            // Mantém message como string para detecção posterior, mas já tem url
                            Log::info('WPP PROXY FROM MAKE → JSON aninhado detectado e extraído', [
                                'has_url' => !empty($jsonData['url']),
                            ]);
                        }
                    }
                }
                $data = array_merge($data, $jsonData);
                Log::info('WPP PROXY FROM MAKE → parsing JSON bem-sucedido', ['keys' => array_keys($data)]);
            } else {
                Log::warning('WPP PROXY FROM MAKE → JSON parse falhou, usando parsing manual', [
                    'json_error' => $jsonError !== JSON_ERROR_NONE ? json_last_error_msg() : null,
                    'raw_preview' => mb_substr($raw, 0, 200),
                ]);
                
                // Fallback: parsing manual
                // O problema é que o message contém JSON sem escape, tornando o JSON principal inválido
                $data = [];

                // company_id (aceita string ou número)
                if (preg_match('/"company_id"\s*:\s*"?([^",}\]]+)"?/', $raw, $m)) {
                    $data['company_id'] = trim($m[1], '"');
                }

                // phone
                if (preg_match('/"phone"\s*:\s*"([^"]*)"/', $raw, $m)) {
                    $data['phone'] = $m[1];
                }

                // message: procura por {"__imobagent" que indica JSON de mídia
                // Estratégia: encontra o início do JSON dentro de message e extrai até o } final
                if (preg_match('/"message"\s*:\s*"(\{.*?\})"/s', $raw, $m)) {
                    // Tentou regex simples, mas pode não pegar tudo
                    $msgCandidate = $m[1] ?? null;
                } else {
                    // Fallback: procura diretamente por {"__imobagent
                    $imobagentPos = strpos($raw, '{"__imobagent"');
                    if ($imobagentPos !== false) {
                        // Encontra o } de fechamento correspondente
                        $pos = $imobagentPos;
                        $len = strlen($raw);
                        $depth = 0;
                        $found = false;
                        
                        while ($pos < $len) {
                            $char = $raw[$pos];
                            
                            if ($char === '{') {
                                $depth++;
                            } elseif ($char === '}') {
                                $depth--;
                                if ($depth === 0) {
                                    // Encontrou o fechamento
                                    $msgCandidate = substr($raw, $imobagentPos, $pos - $imobagentPos + 1);
                                    $found = true;
                                    break;
                                }
                            }
                            
                            $pos++;
                        }
                        
                        if (!$found) {
                            $msgCandidate = null;
                        }
                    } else {
                        $msgCandidate = null;
                    }
                }
                
                if (!empty($msgCandidate)) {
                    // Tenta parsear o JSON extraído
                    $nestedJson = json_decode($msgCandidate, true);
                    if (is_array($nestedJson) && isset($nestedJson['__imobagent']) && $nestedJson['__imobagent'] === 'media') {
                        // É envelope de mídia! Extrai os campos diretamente
                        $data['url'] = $nestedJson['url'] ?? null;
                        $data['mime'] = $nestedJson['mime'] ?? null;
                        $data['fileName'] = $nestedJson['fileName'] ?? ($nestedJson['filename'] ?? null);
                        $data['caption'] = $nestedJson['caption'] ?? null;
                        // Não precisa de message se já extraiu tudo
                        $data['message'] = null;
                        
                        Log::info('WPP PROXY FROM MAKE → JSON de mídia extraído diretamente do raw', [
                            'has_url' => !empty($data['url']),
                            'url_preview' => !empty($data['url']) ? substr($data['url'], 0, 100) : null,
                        ]);
                    } else {
                        // Não é envelope de mídia, mantém como message
                        $data['message'] = $msgCandidate;
                    }
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

                // caption (pode conter JSON escapado)
                if (preg_match('/"caption"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $raw, $m)) {
                    $cap = stripcslashes($m[1]);
                    $data['caption'] = $cap;
                }
                
                Log::info('WPP PROXY FROM MAKE → parsing regex concluído', ['keys' => array_keys($data)]);
            }
        }

        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : null;
        $phone     = $data['phone']      ?? null;
        $message   = $data['message']    ?? null;
        $url       = $data['url'] ?? ($data['fileUrl'] ?? ($data['file_url'] ?? null));
        $mime      = $data['mime'] ?? null;
        $fileName  = $data['fileName'] ?? ($data['filename'] ?? null);
        $caption   = $data['caption'] ?? null;

        // Log dos valores extraídos ANTES da detecção do envelope
        Log::info('WPP PROXY FROM MAKE → valores extraídos (antes detecção envelope)', [
            'company_id' => $companyId,
            'phone'      => $phone,
            'has_message' => !empty($message),
            'message_preview' => $message ? mb_substr((string)$message, 0, 150) : null,
            'message_starts_with_brace' => $message && str_starts_with(ltrim((string)$message), '{'),
            'has_url'    => !empty($url),
            'mime'       => $mime,
            'fileName'   => $fileName,
            'caption'    => $caption ? mb_substr((string)$caption, 0, 100) : null,
        ]);

        /**
         * Compatibilidade com o Make "antigo":
         * Ele envia apenas {company_id, phone, message}.
         * Se message for um JSON contendo url/mime/fileName/caption, tratamos como envio de mídia.
         * 
         * IMPORTANTE: O Make pode enviar o envelope JSON no campo "message" OU no campo "caption".
         * 
         * PRIORIDADE: Verifica envelope JSON ANTES de decidir se é texto ou mídia.
         */
        $jsonEnvelope = null;
        
        // Tenta parsear message como JSON (PRIORIDADE 1)
        if (is_string($message) && $message !== '' && str_starts_with(ltrim($message), '{')) {
            Log::info('WPP PROXY FROM MAKE → tentando parsear message como JSON', [
                'message_length' => strlen($message),
                'message_start' => mb_substr($message, 0, 100),
            ]);
            
            // Limpa escapes que podem ter sido adicionados pelo regex
            $cleanMessage = $message;
            // Remove escapes de aspas duplas se houver
            $cleanMessage = str_replace('\"', '"', $cleanMessage);
            $cleanMessage = str_replace('\\"', '"', $cleanMessage);
            
            $decoded = json_decode($cleanMessage, true);
            $jsonError = json_last_error();
            
            // Se falhar, tenta sem limpeza também
            if ($jsonError !== JSON_ERROR_NONE) {
                $decoded = json_decode($message, true);
                $jsonError = json_last_error();
            }
            
            Log::info('WPP PROXY FROM MAKE → resultado json_decode message', [
                'is_array' => is_array($decoded),
                'json_error' => $jsonError !== JSON_ERROR_NONE ? json_last_error_msg() : null,
                'has_imobagent' => is_array($decoded) && isset($decoded['__imobagent']),
                'imobagent_value' => is_array($decoded) ? ($decoded['__imobagent'] ?? null) : null,
                'decoded_keys' => is_array($decoded) ? array_keys($decoded) : null,
            ]);
            
            if (is_array($decoded) && isset($decoded['__imobagent']) && $decoded['__imobagent'] === 'media') {
                $jsonEnvelope = $decoded;
                Log::info('WPP PROXY FROM MAKE → detectado envelope JSON no campo message', [
                    'has_url' => !empty($decoded['url'] ?? null),
                    'url_preview' => !empty($decoded['url']) ? substr($decoded['url'], 0, 100) : null,
                ]);
            }
        }
        
        // Tenta parsear caption como JSON (PRIORIDADE 2 - fallback)
        if (!$jsonEnvelope && is_string($caption) && $caption !== '' && str_starts_with(ltrim($caption), '{')) {
            $decoded = json_decode($caption, true);
            if (is_array($decoded) && isset($decoded['__imobagent']) && $decoded['__imobagent'] === 'media') {
                $jsonEnvelope = $decoded;
                Log::info('WPP PROXY FROM MAKE → detectado envelope JSON no campo caption');
            }
        }
        
        // Se encontrou envelope JSON, extrai os dados e FORÇA modo mídia
        if ($jsonEnvelope) {
            $maybeUrl = $jsonEnvelope['url'] ?? ($jsonEnvelope['fileUrl'] ?? ($jsonEnvelope['file_url'] ?? null));
            if (!empty($maybeUrl)) {
                $url = $maybeUrl;
                $mime = $mime ?: ($jsonEnvelope['mime'] ?? null);
                $fileName = $fileName ?: ($jsonEnvelope['fileName'] ?? ($jsonEnvelope['filename'] ?? null));
                $caption = $jsonEnvelope['caption'] ?? $caption; // mantém caption humano se existir
                // CRÍTICO: anula message para forçar modo mídia
                $message = null;
                
                Log::info('WPP PROXY FROM MAKE → extraído do envelope JSON (modo mídia)', [
                    'url'      => substr($url, 0, 100) . '...',
                    'mime'     => $mime,
                    'fileName' => $fileName,
                    'message_null' => true,
                ]);
            } else {
                Log::warning('WPP PROXY FROM MAKE → envelope JSON sem URL válida', [
                    'envelope_keys' => array_keys($jsonEnvelope),
                ]);
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
        // Usa WppSender que já funciona para texto (garante mesmo formato/credenciais)
        $mediaUrl = (string) $url;
        if (!$fileName) {
            $path = parse_url($mediaUrl, PHP_URL_PATH) ?: $mediaUrl;
            $fileName = basename($path) ?: ('arquivo_' . time());
        }
        $fileName = (string) $fileName;
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mime = $mime ? (string) $mime : null;
        $caption = $caption ? (string) $caption : null;

        Log::info('Z-API sendFromMake → preparando envio (mídia via WppSender)', [
            'company_id' => $companyId,
            'phone'      => $phone,
            'fileName'   => $fileName,
            'mime'       => $mime,
            'ext'        => $ext,
            'url'        => substr($mediaUrl, 0, 100) . '...',
        ]);

        try {
            // Usa o mesmo formato que WppSender usa (que funciona para texto)
            // Endpoint: send-document/{ext} com payload {phone, document, fileName}
            $base = rtrim($baseUrl, '/') . "/instances/{$instance}/token/{$token}";
            $endpoint = $ext ? "send-document/{$ext}" : 'send-document';
            $endpointUrl = "{$base}/{$endpoint}";
            
            $http = Http::asJson()->timeout(25);
            if ($clientToken) {
                $http = $http->withHeaders([
                    'Client-Token' => $clientToken,
                ]);
            }
            
            // Mesmo formato que WppSender::sendFileFromS3 usa
            $payload = [
                'phone'    => $phone,
                'document' => $mediaUrl, // URL assinada do S3
                'fileName' => $fileName,
            ];
            if ($caption) {
                $payload['caption'] = $caption;
            }
            
            Log::info('Z-API sendFromMake → enviando mídia (formato WppSender)', [
                'endpoint' => $endpoint,
                'url'     => $endpointUrl,
                'phone'   => $phone,
                'fileName' => $fileName,
                'hasCaption' => !empty($caption),
            ]);
            
            $resp = $http->post($endpointUrl, $payload);
            
            Log::info('Z-API sendFromMake → resposta (mídia)', [
                'endpoint' => $endpoint,
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
                        'endpoint'  => $endpoint,
                        'status'    => $resp->status(),
                        'zapi_raw'  => $j,
                    ]);
                }
            }
            
            return response()->json([
                'ok'    => false,
                'mode'  => 'media',
                'error' => 'zapi_media_failed',
                'status' => $resp->status(),
                'body'   => $resp->json() ?: $resp->body(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Z-API sendFromMake → erro ao enviar (mídia)', [
                'company_id' => $companyId,
                'phone'      => $phone,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'    => false,
                'mode'  => 'media',
                'error' => 'Erro ao enviar mídia para Z-API: ' . $e->getMessage(),
            ], 500);
        }
    }
}
