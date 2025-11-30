<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Pega telefone e texto, independente do formato
    $phone = $this->normalizePhone(
        $request->input('phone') ?? $request->input('data.from')
    );

    $msg = trim(
        (string) ($request->input('message') ?? $request->input('data.message.text.body'))
    );


        

        if (!$phone) {
            // Sem telefone válido → silencia
            return response('', 204);
        }

        // 1) Já vinculado?
        $user = User::where('whatsapp', $phone)->first();
        if ($user) {
            return $this->reply("Olá, {$user->name}! 👋 O que deseja saber hoje?");
        }

        // 2) Não vinculado: só fala se a mensagem for um ID válido existente
        if ($this->looksLikeId($msg)) {
            $candidate = User::find((int) $msg);

            if ($candidate) {
                // vincula e responde
                $candidate->whatsapp = $phone;
                $candidate->save();

                return $this->reply("Olá, {$candidate->name}! 👋 O que deseja saber hoje?");
            }
        }

        // 3) Qualquer outro caso → silêncio total
        return response('', 204);
        \Log::info('Webhook recebido:', $request->all());

    }

   private function reply(string $text)
{
    // Quando quiser testar com resposta real via Z-API:
    // $this->sendMessage($phone, $text);
    // Mas no webhook, normalmente devolvemos JSON:
    return response()->json(['reply' => $text]);
}


    private function looksLikeId(string $msg): bool
    {
        // Aceita apenas números inteiros positivos até 10 dígitos
        return preg_match('/^[0-9]{1,10}$/', $msg) === 1;
    }

    private function normalizePhone(?string $raw): ?string
    {
        if (!$raw) return null;
        // Remove tudo que não é dígito
        $digits = preg_replace('/\D+/', '', $raw);
        return strlen($digits) >= 10 ? $digits : null;
    }

    private function sendMessage($phone, $text)
{
    $instanceId = '3D73708E241210F980345E093E4930CD'; // ID da sua instância
    $token = '94C2BEC407EEB5940EE079FE'; // Token da sua instância

    $url = "https://api.z-api.io/instances/{$instanceId}/token/{$token}/send-text";

    $payload = [
        'phone' => $phone,
        'message' => $text
    ];

    $response = \Http::post($url, $payload);

    return $response->json();
}

}
