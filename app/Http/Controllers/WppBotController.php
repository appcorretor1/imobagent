<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Empreendimento;
use App\Services\VectorStoreService;
use OpenAI;

class WppBotController extends Controller
{
    public function handle(Request $r, VectorStoreService $svc)
    {
        $data = $r->validate([
            'phone'   => ['required','string'],
            'message' => ['nullable','string'],
        ]);

        $phone   = $this->normalize($data['phone']);
        $message = trim((string)($data['message'] ?? ''));

        Log::info('WPPBOT VERSION MARK', ['v'=>'files-gate-r1']);


        if (!$phone) {
            return response()->json(['ok'=>false,'message'=>'Telefone inválido.'], 422);
        }

        $user = User::where('whatsapp', $phone)->first();
        if (!$user) {
            return response()->json(['ok'=>true,'message'=>'Seu número não está cadastrado.'], 200);
        }
        $companyId = $user->company_id ?? null;
        if (!$companyId) {
            return response()->json(['ok'=>true,'message'=>'Nenhuma empresa vinculada ao seu cadastro.'], 200);
        }

        // === seleção de empreendimento ===
        $selKey = $this->selectedKey($phone);
        $sel    = Cache::get($selKey); // ['empreendimento_id'=>..., 'nome'=>...]

        if (!$sel) {
            if ($this->isIndex($message)) {
                $list = $this->buildList($companyId, $phone);
                if (empty($list['items'])) {
                    return response()->json([
                        'ok'=>true, 'message'=>'Não há empreendimentos disponíveis no momento.'
                    ]);
                }
                $idx = (int) $message;
                $chosen = collect($list['items'])->firstWhere('index', $idx);
                if (!$chosen) {
                    return response()->json(['ok'=>true,'message'=>$list['text']]);
                }
                $emp = Empreendimento::find($chosen['id']);
                if (!$emp) {
                    return response()->json(['ok'=>true,'message'=>$list['text']]);
                }
                Cache::put($selKey, ['empreendimento_id'=>$emp->id, 'nome'=>$emp->nome], now()->addMinutes(10));
                return response()->json([
                    'ok'=>true,
                    'message'=>"Perfeito! Empreendimento selecionado: {$emp->nome}.\nO que você deseja saber?"
                ]);
            }

            $list = $this->buildList($companyId, $phone);
            return response()->json(['ok'=>true,'message'=>$list['text']]);
        }

       // >>> SUPER HARD-GATE: qualquer msg com "arquiv" vai para a listagem <<<
if ($message !== '' && stripos($message, 'arquiv') !== false) {
    Log::info('WPPBOT SUPER-GATE arquivos acionado', ['msg'=>$message]);

    // precisa do empreendimento selecionado
    $selKey = $this->selectedKey($phone);
    $sel    = Cache::get($selKey);
    if (!$sel) {
        $list = $this->buildList($user->company_id ?? 0, $phone);
        return response()->json(['ok'=>true,'message'=>$list['text']]);
    }

    $empId    = (int) $sel['empreendimento_id'];
    $filesKey = $this->fileListKey($phone, $empId);

    // Se for pedido para VER/LISTAR/MOSTRAR, montamos a lista
    if (preg_match('/\b(ver|listar|mostrar)\s+arquivos\b/i', $message)) {
        $listText = $this->cacheAndBuildFilesList($filesKey, $empId, $user->company_id);
        return response()->json([
            'ok'=>true,
            'message'=>$listText . "\n\nResponda com os números (ex.: 1,2,5)."
        ], 200);
    }

    // Se mandou índices, responde com links
    if ($this->isMultiIndexList($message) && Cache::has($filesKey)) {
        $indices = $this->parseIndices($message);
        $items   = Cache::get($filesKey, []);
        $byIdx   = collect($items)->keyBy('index');

        $picked = [];
        foreach ($indices as $i) if ($byIdx->has($i)) $picked[] = $byIdx->get($i);
        if (empty($picked)) {
            return response()->json(['ok'=>true,'message'=>'Esses índices não existem. Diga: ver arquivos'], 200);
        }

        $lines = [];
        foreach ($picked as $p) {
            $freshUrl = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($p['path'], now()->addMinutes(10));
            $lines[]  = "{$p['name']}\n{$freshUrl}";
        }
        return response()->json(['ok'=>true,'message'=>implode("\n\n", $lines)], 200);
    }

    // Qualquer outra frase com "arquiv" (ex.: "me manda o book...") -> instruir a listar
    return response()->json([
        'ok'=>true,
        'message'=>"Entendi que você quer um arquivo.\nAntes, diga: *ver arquivos*.\nVou listar e você escolhe (ex.: 1,2,5)."
    ], 200);
}
// <<< FIM SUPER HARD-GATE >>>


        if ($message === '' || mb_strlen($message) < 1) {
            return response()->json([
                'ok'=>true,
                'message'=>'Pode me dizer sua dúvida sobre o empreendimento?'
            ]);
        }

        // ================== FLUXO: LISTAGEM E RETORNO DE LINKS POR ÍNDICE ==================
        $empId    = (int) $sel['empreendimento_id'];
        $filesKey = $this->fileListKey($phone, $empId);

        // B) Se mandou índices e JÁ tem lista em cache -> responde com links
        if ($this->isMultiIndexList($message) && Cache::has($filesKey)) {
            $indices = $this->parseIndices($message);
            if (empty($indices)) {
                return response()->json(['ok'=>true,'message'=>'Não entendi os números enviados. Tente algo como: 1,2,5'], 200);
            }

            $items  = Cache::get($filesKey, []);
            $byIdx  = collect($items)->keyBy('index');

            $picked = [];
            foreach ($indices as $i) {
                if ($byIdx->has($i)) $picked[] = $byIdx->get($i);
            }
            if (empty($picked)) {
                return response()->json(['ok'=>true,'message'=>'Esses índices não existem. Quer listar novamente? Diga: ver arquivos'], 200);
            }

            // Responde APENAS com links (e renova a URL para garantir validade)
            $lines = [];
            foreach ($picked as $p) {
                $freshUrl = Storage::disk('s3')->temporaryUrl($p['path'], now()->addMinutes(10));
                $lines[] = "{$p['name']}\n{$freshUrl}";
            }
            $resp = implode("\n\n", $lines);
            return response()->json(['ok'=>true,'message'=>$resp], 200);
        }

        // C) Mandou índices SEM cache -> instrui listar primeiro
        if ($this->isMultiIndexList($message) && !Cache::has($filesKey)) {
            return response()->json([
                'ok'=>true,
                'message'=>"Para enviar links, primeiro diga: *ver arquivos*.\nDepois responda com os números (ex.: 1,2,5)."
            ], 200);
        }

        // D) Mensagem sugere intenção de arquivo mas AINDA não listou -> instrui listar
        if ($this->parseFileIntent($message) !== null && !Cache::has($filesKey)) {
            return response()->json([
                'ok'=>true,
                'message'=>"Entendi que você quer um arquivo.\nAntes, peça: *ver arquivos*.\nVou te mostrar a lista e você escolhe (ex.: 1,2,5)."
            ], 200);
        }

        // E) Já listou e a mensagem ainda parece intenção de arquivo -> relembra como escolher
        if ($this->parseFileIntent($message) !== null && Cache::has($filesKey)) {
            return response()->json([
                'ok'=>true,
                'message'=>"Os arquivos já foram listados. Responda com os números (ex.: 1,2,5). Se quiser ver a lista de novo, diga: *ver arquivos*."
            ], 200);
        }
        // ================== FIM DO FLUXO DE ARQUIVOS ==================

        // === AI padrão ===
        try {
            $answer = $this->askAI($user, $empId, $message, $svc);
            return response()->json([
                'ok'=>true,
                'message'=> $answer !== '' ? $answer : 'Não consegui responder agora. Pode reformular?'
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI error', ['e'=>$e->getMessage()]);
            return response()->json([
                'ok'=>true,
                'message'=>'Tive um problema para consultar agora. Pode tentar novamente em instantes?'
            ], 200);
        }
    }

    // ===== Helpers =====

    private function buildList(int $companyId, string $phone): array
    {
        $emps = Empreendimento::where('company_id', $companyId)
            ->where('ativo', 1)
            ->orderBy('nome')->get(['id','nome']);

        if ($emps->isEmpty()) {
            return ['text'=>'Não há empreendimentos disponíveis no momento.', 'items'=>[]];
        }

        $items = [];
        $lines = [];
        $i = 1;
        foreach ($emps as $e) {
            $items[] = ['index'=>$i, 'id'=>$e->id, 'name'=>mb_strtolower($e->nome)];
            $lines[] = "{$i} - {$e->nome}";
            $i++;
        }

        Cache::put($this->listKey($phone), $items, now()->addMinutes(5));

        return [
            'text'  => "Escolha o empreendimento respondendo só o número:\n\n".implode("\n", $lines),
            'items' => $items,
        ];
    }

    /**
     * Lista e cacheia SOMENTE os arquivos da pasta S3 do empreendimento.
     * Ignora BD/Vector Store para evitar listar arquivos indevidos.
     * Mostra exatamente o nome do S3 (sem link nesta etapa).
     */
   private function cacheAndBuildFilesList(string $filesKey, int $empId, int $companyId): string
{
    $prefix = "documentos/tenants/{$companyId}/empreendimentos/{$empId}/";
    $disk   = \Illuminate\Support\Facades\Storage::disk('s3');
    $files  = $disk->files($prefix);

    $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','csv','txt','jpg','jpeg','png','gif','webp','mp4','mov','avi','mkv'];
    $files = array_values(array_filter($files, fn($p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), $allowed, true)));

    if (empty($files)) {
        \Illuminate\Support\Facades\Cache::put($filesKey, [], now()->addMinutes(15));
        return "Não encontrei arquivos para este empreendimento.";
    }

    usort($files, fn($a,$b) => strcasecmp(basename($a), basename($b)));

    $items = [];
    $lines = [];
    $i = 1;
    foreach ($files as $path) {
        $name = basename($path);
        $mime = $this->guessMimeByExt($name);
        $items[] = ['index'=>$i,'name'=>$name,'path'=>$path,'mime'=>$mime];
        $lines[] = "{$i}. {$name}";
        $i++;
    }

    \Illuminate\Support\Facades\Cache::put($filesKey, $items, now()->addMinutes(30));
    return "Arquivos disponíveis:\n\n" . implode("\n", $lines);
}


    private function guessMimeByExt(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx'=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx'=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx'=> 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'jpg','jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp'=> 'image/webp',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            default => 'application/octet-stream',
        };
    }

    private function askAI($user, int $empId, string $question, VectorStoreService $svc): string
    {
        $vsId   = $svc->ensureVectorStoreForEmpreendimento($empId);
        $asstId = $svc->ensureAssistantForEmpreendimento($empId, $vsId);

        // Thread por (phone + empreendimento)
        $threadKey = "wpp:thread:{$user->whatsapp}:{$empId}";
        $threadId = Cache::get($threadKey);

        $client = OpenAI::client(config('services.openai.key'));

        if (!$threadId) {
            $th = $client->threads()->create();
            $threadId = $th->id;
            Cache::put($threadKey, $threadId, now()->addDays(7));
        }

        // Mensagem do usuário
        $client->threads()->messages()->create($threadId, [
            'role'    => 'user',
            'content' => $question,
        ]);

        // Cria o run
        $run = $client->threads()->runs()->create($threadId, [
            'assistant_id' => $asstId,
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [$vsId],
                ],
            ],
        ]);

        // Polling simples
        do {
            usleep(300000);
            $run = $client->threads()->runs()->retrieve($threadId, $run->id);
        } while (in_array($run->status, ['queued','in_progress','cancelling']));

        if ($run->status !== 'completed') {
            return '';
        }

        $msgs = $client->threads()->messages()->list($threadId, ['limit'=>10]);
        foreach ($msgs->data as $m) {
            if ($m->role === 'assistant') {
                foreach ($m->content as $c) {
                    if ($c->type === 'text') {
                        return $c->text->value;
                    }
                }
            }
        }
        return '';
    }

    // ====== Parsing & Utils ======

    private function parseFileIntent(string $msg): ?array
    {
        $t = mb_strtolower($msg);
        $terms = [
            'arquivo','documento','pdf','doc','imagem','foto',
            'vídeo','video','apresentação','apresentacao',
            'planta','tabela','planilha','brochura','book','book digital'
        ];
        foreach ($terms as $term) {
            if (mb_strpos($t, $term) !== false) return ['intent'=>'file'];
        }
        if (preg_match('/\b(pdf|docx?|xlsx?|pptx?|jpg|jpeg|png|gif|webp|mp4|mov|avi|mkv|csv|txt)\b/i', $t)) {
            return ['intent'=>'file'];
        }
        return null;
    }

    private function isMultiIndexList(string $msg): bool
    {
        $t = trim(mb_strtolower($msg));
        return (bool) preg_match('/^[0-9,\s\-]+$/', $t);
    }

    private function parseIndices(string $msg): array
    {
        $t = preg_replace('/\s+/', '', $msg);
        $parts = explode(',', $t);
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (str_contains($p, '-')) {
                [$a,$b] = array_pad(explode('-', $p, 2), 2, null);
                $a = (int) $a; $b = (int) $b;
                if ($a > 0 && $b > 0) {
                    foreach (range(min($a,$b), max($a,$b)) as $n) $out[] = $n;
                }
            } else {
                $n = (int) $p;
                if ($n > 0) $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    private function normalize(?string $raw): ?string
    {
        if (!$raw) return null;
        $d = preg_replace('/\D+/', '', $raw);
        return strlen($d) >= 10 ? $d : null;
    }

    private function isIndex(string $msg): bool
    {
        $t = preg_replace('/\s+/', '', mb_strtolower($msg));
        return (bool) preg_match('/^[0-9]{1,2}$/', $t);
    }

    private function listKey(string $phone): string { return "wpp:list:{$phone}"; }
    private function selectedKey(string $phone): string { return "wpp:sel:{$phone}"; }
    private function fileListKey(string $phone, int $empId): string { return "wpp:filelist:{$phone}:{$empId}"; }
}
