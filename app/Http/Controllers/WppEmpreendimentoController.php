<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Empreendimento;
use Illuminate\Support\Facades\Log;

class WppEmpreendimentoController extends Controller
{
    /**
     * GET /api/wpp/empreendimentos/list?phone=5562...
     * Cria/atualiza a LISTA ATIVA na cache por 5 minutos.
     * Retorna state=awaiting_emp + texto da lista + items (com index).
     */
    public function list(Request $r)
    {
        $phone = $this->normalizePhone($r->query('phone'));
        if (!$phone) {
            return response()->json(['ok'=>false,'error'=>'invalid_phone'], 422);
        }

        // 1) Valida usuário + empresa
        $user = User::where('whatsapp', $phone)->first();
        if (!$user) {
            return response()->json(['ok'=>false,'error'=>'user_not_found'], 404);
        }

        // encontre a empresa do corretor (ajuste conforme seu schema)
        $companyId = $user->company_id ?? null;  // se seu users tiver company_id
        if (!$companyId) {
            return response()->json(['ok'=>false,'error'=>'no_company_bound'], 404);
        }

        // 2) Carrega empreendimentos da empresa
        $emps = Empreendimento::where('company_id', $companyId)
            ->orderBy('nome')
            ->get(['id','nome']);

        if ($emps->isEmpty()) {
            return response()->json([
                'ok'    => true,
                'state' => 'awaiting_emp',
                'text'  => "Não há empreendimentos disponíveis para sua empresa no momento.",
                'items' => [],
            ]);
        }

        // 3) Monta lista com índice 1..N
        $items = [];
        $lines = [];
        $i = 1;
        foreach ($emps as $e) {
            $items[] = ['index'=>$i, 'id'=>$e->id, 'name'=>mb_strtolower($e->nome)];
            $lines[] = "{$i} - " . $e->nome;
            $i++;
        }
        $text = "Escolha o empreendimento respondendo só o número:\n\n" . implode("\n", $lines);

        // 4) Salva LISTA ATIVA na cache por 5 minutos (chave por telefone)
        $cacheKey = $this->listKey($phone);
        Cache::put($cacheKey, $items, now()->addMinutes(5));

        return response()->json([
            'ok'    => true,
            'state' => 'awaiting_emp',
            'text'  => $text,
            'items' => $items,
        ]);
    }

    /**
     * POST /api/wpp/empreendimentos/select
     * body: { phone: "5562...", index: "1" }
     * Lê a LISTA ATIVA da cache e resolve o empreendimento escolhido.
     */
    // app/Http/Controllers/WppEmpreendimentoController.php
private function selectedKey(string $phone): string
{
    return "wpp:sel:{$phone}";
}

public function select(Request $r)
{
    $data = $r->validate([
        'phone' => ['required','string'],
        'index' => ['required','string'],
    ]);

    $phone = $this->normalizePhone($data['phone']);
    if (!$phone) return response()->json(['ok'=>false,'error'=>'invalid_phone'], 422);

    $index = (int) preg_replace('/\D+/', '', $data['index'] ?? '');
    if ($index < 1) return response()->json(['ok'=>false,'error'=>'invalid_index'], 422);

    // lista ativa
    $items = Cache::get($this->listKey($phone));
    if (!$items || !is_array($items)) {
        return response()->json(['ok'=>false,'error'=>'no_active_list'], 422);
    }

    $chosen = collect($items)->firstWhere('index', $index);
    if (!$chosen) return response()->json(['ok'=>false,'error'=>'invalid_choice'], 422);

    $emp = Empreendimento::find($chosen['id']);
    if (!$emp) return response()->json(['ok'=>false,'error'=>'emp_not_found'], 404);

    // 🔐 salva a seleção p/ próximas mensagens (10 min)
    Cache::put($this->selectedKey($phone), [
        'empreendimento_id' => $emp->id,
        'nome'              => $emp->nome,
    ], now()->addMinutes(10));

    return response()->json([
        'ok'                => true,
        'state'             => 'selected',
        'empreendimento_id' => $emp->id,
        'nome'              => $emp->nome,
    ]);
}

/**
 * GET /api/wpp/empreendimentos/status?phone=5562...
 * Se já existe seleção ativa: state=awaiting_question + dados do empreendimento
 * Senão: reaproveita sua lista (state=awaiting_emp + text + items)
 */
public function status(Request $r)
{
    $phone = $this->normalizePhone($r->query('phone'));
    if (!$phone) return response()->json(['ok'=>false,'error'=>'invalid_phone'], 422);

    if ($sel = Cache::get($this->selectedKey($phone))) {
        return response()->json([
            'ok'                => true,
            'state'             => 'awaiting_question',
            'empreendimento_id' => $sel['empreendimento_id'],
            'nome'              => $sel['nome'],
        ]);
    }

    // Sem seleção? Devolve a lista (mesmo retorno do list())
    return $this->list($r);
}

    private function normalizePhone(?string $raw): ?string
    {
        if (!$raw) return null;
        $digits = preg_replace('/\D+/', '', $raw);
        return strlen($digits) >= 10 ? $digits : null;
    }

    private function listKey(string $phone): string
    {
        return "wpp:list:{$phone}";
    }

   public function handle(Request $r)
{
    $phone   = $this->normalizePhone($r->input('phone'));
    $message = trim((string) $r->input('message', ''));

    if (!$phone) {
        return response()->json(['ok' => false, 'error' => 'invalid_phone'], 422);
    }

    // Normaliza apenas dígitos do que o usuário mandou
    $digits = preg_replace('/\D+/', '', $message ?? '');

    // Se existe uma lista ativa em cache E a mensagem parece um índice (1..99) => seleciona
    $cacheKey = $this->listKey($phone);
    $hasList  = Cache::has($cacheKey);

    if ($hasList && $digits !== '' && (int)$digits >= 1 && (int)$digits <= 99) {
        // Reaproveita seu método select()
        $req = new Request([
            'phone' => $phone,
            'index' => $digits,
        ]);
        Log::info('WPP handle -> select', ['phone' => $phone, 'index' => $digits]);
        return $this->select($req);
    }

    // Caso contrário, sempre manda a lista
    $req = new Request(['phone' => $phone]);
    Log::info('WPP handle -> list', ['phone' => $phone]);
    return $this->list($req);
}

}
