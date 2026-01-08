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
        $user = auth()->user();
        $threadKey = "admin:thread:{$user->id}:{$empreendimento->id}";
        $threadId = Cache::get($threadKey);
        
        $messages = [];
        
        if ($threadId) {
            try {
                $client = OpenAI::client(config('services.openai.key'));
                $msgs = $client->threads()->messages()->list($threadId, ['limit' => 50, 'order' => 'asc']);
                $messages = $msgs->data;
            } catch (\Exception $e) {
                Log::warning('QA: Erro ao buscar mensagens da thread', [
                    'threadId' => $threadId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Se veio mensagens via session (redirect), usa elas
        if (session('messages')) {
            $messages = session('messages');
        }
        
        return view('admin.qa.chat', [
            'e' => $empreendimento,
            'messages' => $messages,
        ]);
    }

    // POST: /admin/empreendimentos/{empreendimento}/perguntar
    public function askSubmit(
        Request $request,
        Empreendimento $empreendimento,
        VectorStoreService $svc
    ) {
        $data = $request->validate([
            'q' => ['required','string','min:2'], // A view chat envia 'q', não 'question'
        ]);

        $question = trim($data['q']);

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

            // Busca todas as mensagens para retornar
            $msgs = $client->threads()->messages()->list($threadId, ['limit' => 50, 'order' => 'asc']);
            
            if ($run->status !== 'completed') {
                Log::warning('QA run not completed', [
                    'status' => $run->status,
                    'empId'  => $empreendimento->id
                ]);
            }

            // Redireciona de volta para a view chat com as mensagens atualizadas
            return redirect()->route('admin.qa.form', $empreendimento)
                ->with('messages', $msgs->data);
                
        } catch (\Throwable $e) {
            Log::error('QA askSubmit error', [
                'empId' => $empreendimento->id,
                'err'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.qa.form', $empreendimento)
                ->with('error', 'Tive um problema ao consultar os arquivos do empreendimento. Tente novamente em instantes.');
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
