<?php 

// app/Http/Controllers/WppSessionController.php
namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WppSessionController extends Controller
{
    const TIMEOUT_MINUTES = 5;

    // GET /api/wpp/session?phone=...
    public function get(Request $r) {
        $phone = preg_replace('/\D+/', '', (string)$r->query('phone', ''));
        if (!$phone) return response()->json(['error'=>'phone_required'], 400);

        $s = WhatsappSession::firstOrCreate(['phone'=>$phone], []);

        // Auto-expira se passou de 5 min sem interação
        $now = now();
        $expired = !$s->last_interaction_at || $s->last_interaction_at->lt($now->copy()->subMinutes(self::TIMEOUT_MINUTES));

        if ($expired) {
            $s->state = 'awaiting_emp';
            $s->empreendimento_id = null;
            $s->last_interaction_at = $now; // toca para evitar re-expirar em loop
            $s->save();
        }

        return response()->json([
            'state' => $s->state,
            'empreendimento_id' => $s->empreendimento_id,
            'expired' => (bool)$expired,
        ]);
    }

    // POST /api/wpp/session   { phone, state?, empreendimento_id? }
    public function set(Request $r) {
        $data = $r->validate([
            'phone' => ['required','string'],
            'state' => ['nullable','in:awaiting_emp,chatting'],
            'empreendimento_id' => ['nullable','integer'],
        ]);
        $phone = preg_replace('/\D+/', '', $data['phone']);
        $s = WhatsappSession::firstOrCreate(['phone'=>$phone], []);
        if (isset($data['state'])) $s->state = $data['state'];
        if (array_key_exists('empreendimento_id', $data)) $s->empreendimento_id = $data['empreendimento_id'];
        $s->last_interaction_at = now();
        $s->save();

        return response()->json([
            'ok'=>true,
            'state'=>$s->state,
            'empreendimento_id'=>$s->empreendimento_id
        ]);
    }

    // POST /api/wpp/session/touch { phone }
    public function touch(Request $r) {
        $data = $r->validate(['phone'=>['required','string']]);
        $phone = preg_replace('/\D+/', '', $data['phone']);
        $s = WhatsappSession::firstOrCreate(['phone'=>$phone], []);
        $s->last_interaction_at = now();
        $s->save();
        return response()->json(['ok'=>true]);
    }

    // POST /api/wpp/session/reset  { phone }
    public function reset(Request $r) {
        $data = $r->validate(['phone'=>['required','string']]);
        $phone = preg_replace('/\D+/', '', $data['phone']);
        $s = WhatsappSession::firstOrCreate(['phone'=>$phone], []);
        $s->state = 'awaiting_emp';
        $s->empreendimento_id = null;
        $s->last_interaction_at = now();
        $s->save();
        return response()->json(['ok'=>true]);
    }
}
