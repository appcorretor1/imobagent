<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Empreendimento;
use App\Services\VectorStoreService;
use OpenAI;

class QaController extends Controller
{
    /** ===== Aliases legados (compat) ===== */
    public function form(Empreendimento $e)
    {
        return $this->askForm($e);
    }

    public function process(Request $r, Empreendimento $e, VectorStoreService $svc)
    {
        return $this->askSubmit($r, $e, $svc);
    }

    /** ===== Fluxo novo (recomendado) ===== */

    // GET: /admin/empreendimentos/{empreendimento}/perguntar
    public function askForm(Empreendimento $empreendimento)
    {
       return view('admin.qa.ask', [
    'empreendimento' => $empreendimento,
    'answer' => null,
    'question' => null,
]);

    }

    // POST: /admin/empreendimentos/{empreendimento}/perguntar
    public function askSubmit(
        Request $request,
        Empreendimento $empreendimento,
        VectorStoreService $svc
    ) {
        $data = $request->validate([
            'question' => ['required','string','min:2'],
        ]);

        $question = trim($data['question']);

        try {
            // Garante Vector Store e Assistant
            $vsId   = $svc->ensureVectorStoreForEmpreendimento($empreendimento->id);
            $asstId = $svc->ensureAssistantForEmpreendimento($empreendimento->id, $vsId);

            // Thread por (user + empreendimento)
            $user      = $request->user();
            $threadKey = "admin:thread:{$user->id}:{$empreendimento->id}";
            $threadId  = Cache::get($threadKey);

            $client = OpenAI::client(config('services.openai.key'));

            if (!$threadId) {
                $th = $client->threads()->create();
                $threadId = $th->id;
                Cache::put($threadKey, $threadId, now()->addDays(7));
            }

            // Add a pergunta
            $client->threads()->messages()->create($threadId, [
                'role'    => 'user',
                'content' => $question,
            ]);

            // Executa o assistant
            $run = $client->threads()->runs()->create($threadId, [
                'assistant_id' => $asstId,
            ]);

            // Polling simples
            do {
                usleep(300000);
                $run = $client->threads()->runs()->retrieve($threadId, $run->id);
            } while (in_array($run->status, ['queued','in_progress','cancelling']));

            $answer = '';
            if ($run->status === 'completed') {
                $msgs = $client->threads()->messages()->list($threadId, ['limit' => 10]);
                foreach ($msgs->data as $m) {
                    if ($m->role === 'assistant') {
                        foreach ($m->content as $c) {
                            if ($c->type === 'text') {
                                $answer = $c->text->value;
                                break 2;
                            }
                        }
                    }
                }
            } else {
                Log::warning('QA run not completed', [
                    'status' => $run->status,
                    'empId'  => $empreendimento->id
                ]);
            }

            return view('admin.qa.ask', [
                'empreendimento' => $empreendimento,
                'answer'         => $answer !== '' ? $answer : 'Não consegui responder agora. Pode tentar novamente?',
                'question'       => $question,
            ]);
        } catch (\Throwable $e) {
            Log::error('QA askSubmit error', [
                'empId' => $empreendimento->id,
                'err'   => $e->getMessage(),
            ]);

            return view('admin.qa.ask', [
                'empreendimento' => $empreendimento,
                'answer'         => 'Tive um problema ao consultar os arquivos do empreendimento. Tente novamente em instantes.',
                'question'       => $question,
            ]);
        }
    }

    /** ===== Gerência de arquivos do VS (stubs seguros) ===== */
    public function attachVsFile(Request $r, Empreendimento $e, VectorStoreService $svc)
    {
        // Se já tem esses métodos no service, você pode implementar aqui.
        // Por enquanto, devolvo 501 para não quebrar as rotas existentes.
        return response()->json(['ok'=>false,'message'=>'Not implemented'], 501);
    }

    public function deleteVsFile(Empreendimento $e, string $fileId, VectorStoreService $svc)
    {
        return response()->json(['ok'=>false,'message'=>'Not implemented'], 501);
    }

    public function listVsFiles(Empreendimento $e, VectorStoreService $svc)
    {
        return response()->json(['ok'=>false,'message'=>'Not implemented'], 501);
    }

    /** ===== Reset da thread (opcional) ===== */
    public function reset(Request $r, Empreendimento $e)
    {
        $user      = $r->user();
        $threadKey = "admin:thread:{$user->id}:{$e->id}";
        Cache::forget($threadKey);

        return redirect()
            ->route('admin.empreendimentos.ask', $e)
            ->with('ok', 'Conversa resetada.');
    }
}
