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

     Log::info('WPP PROXY FROM MAKE → payload recebido', [
    'company_id' => $data['company_id'] ?? null,
    'phone'      => $data['phone'] ?? null,
    'message_preview' => isset($data['message'])
        ? mb_substr($data['message'], 0, 20) . '...'
        : null,
]);


        $data = $request->all();

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
        }

        $companyId = $data['company_id'] ?? null;
        $phone     = $data['phone']      ?? null;
        $message   = $data['message']    ?? null;

        if (!$companyId || !$phone || !$message) {
           Log::warning('WPP PROXY FROM MAKE → campos obrigatórios faltando', [
    'company_id' => $companyId,
    'phone'      => $phone,
    'message'    => $message ? '[RECEIVED]' : null,
]);


            return response()->json([
                'ok'    => false,
                'error' => 'Campos obrigatórios: company_id, phone, message',
            ], 422);
        }

        // 🔹 Busca empresa e credenciais Z-API (fallback pro .env)
        $company = Company::find($companyId);

        $baseUrl  = $company->zapi_base_url    ?: config('services.zapi.base_url');
        $instance = $company->zapi_instance_id ?: config('services.zapi.instance');
        // zapi_token é o PATH_TOKEN (token da instância que vai no path da URL)
        $token    = $company->zapi_token ?: config('services.zapi.token', env('ZAPI_PATH_TOKEN', env('ZAPI_TOKEN', '')));

        $url = rtrim($baseUrl, '/') . "/instances/{$instance}/token/{$token}/send-text";

        Log::info('Z-API sendFromMake → preparando envio', [
            'company_id' => $companyId,
            'phone'      => $phone,
            'url'        => $url,
        ]);

        try {
           $clientToken = config('services.zapi.client_token');

$http = Http::timeout(10);

if ($clientToken) {
    $http = $http->withHeaders([
        'client-token' => $clientToken,
    ]);
}

$zapiResp = $http->post($url, [
    'phone'   => $phone,
    'message' => $message,
]);


            Log::info('Z-API sendFromMake → resposta', [
                'company_id' => $companyId,
                'phone'      => $phone,
                'status'     => $zapiResp->status(),
                'body'       => $zapiResp->json(),
            ]);

            return response()->json([
                'ok'       => true,
                'status'   => $zapiResp->status(),
                'zapi_raw' => $zapiResp->json(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Z-API sendFromMake → erro ao enviar', [
                'company_id' => $companyId,
                'phone'      => $phone,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'ok'    => false,
                'error' => 'Erro ao enviar para Z-API',
            ], 500);
        }
    }
}
