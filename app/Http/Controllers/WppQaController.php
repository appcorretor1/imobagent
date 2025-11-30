<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Empreendimento;
use App\Models\WhatsappThread;
use App\Services\VectorStoreService;
use Illuminate\Support\Facades\Log;
use OpenAI;

class WppQaController extends Controller
{
    /**
     * POST /api/wpp/qa/ask
     * body: { phone: "5562...", empreendimento_id: 123, message: "pergunta do usuário" }
     * Retorna: { answer: "texto" }
     */
    public function ask(Request $r, VectorStoreService $svc)
    {
        $data = $r->validate([
            'phone'            => ['required','string'],
            'empreendimento_id'=> ['required','integer','exists:empreendimentos,id'],
            'message'          => ['required','string'],
        ]);

        // 1) Só atende quem está cadastrado
        $user = User::where('whatsapp', preg_replace('/\D+/', '', $data['phone']))->first();
        if (!$user) {
            return response()->json(['error' => 'not_registered'], 403);
        }

        $e = Empreendimento::findOrFail($data['empreendimento_id']);

        // 2) Garante Vector Store + Assistant do empreendimento (você já tem isso no serviço)
        $vsId   = $svc->ensureVectorStoreForEmpreendimento($e->id);
        $asstId = $svc->ensureAssistantForEmpreendimento($e->id, $vsId);

        // 3) Thread por (phone + empreendimento)
        $thread = WhatsappThread::firstOrCreate(
            ['phone' => $user->whatsapp, 'empreendimento_id' => $e->id],
            ['thread_id' => ''] // placeholder
        );

        $client = OpenAI::client(config('services.openai.key'));

        if (!$thread->thread_id) {
            // Cria thread na primeira vez
            $th = $client->threads()->create();
            $thread->thread_id = $th->id;
            $thread->save();
        }

        // 4) Adiciona a mensagem do usuário na thread
        $client->threads()->messages()->create($thread->thread_id, [
            'role'    => 'user',
            'content' => $data['message'],
        ]);

        // 5) Roda com o assistant do empreendimento
        $run = $client->threads()->runs()->create($thread->thread_id, [
            'assistant_id' => $asstId,
            // system prompt do assistant já deve orientar: "responda somente com base no VS do empreendimento X"
        ]);

        // 6) Polling simples até completar (pode refinar com webhooks/queue)
        do {
            usleep(300000); // 300ms
            $run = $client->threads()->runs()->retrieve($thread->thread_id, $run->id);
        } while (in_array($run->status, ['queued','in_progress','cancelling']));

        if ($run->status !== 'completed') {
            Log::warning('Run not completed', ['status'=>$run->status]);
            return response()->json(['answer' => 'No momento não consegui consultar as informações. Tente novamente.']);
        }

        // 7) Busca a última mensagem do assistant
        $msgs = $client->threads()->messages()->list($thread->thread_id, ['limit'=>10]);
        $answer = '';
        foreach ($msgs->data as $m) {
            if ($m->role === 'assistant') {
                // pega o primeiro bloco textual
                foreach ($m->content as $c) {
                    if ($c->type === 'text') { $answer = $c->text->value; break 2; }
                }
            }
        }

        return response()->json([
            'answer' => $answer ?: 'Não encontrei informações suficientes nos documentos deste empreendimento.',
            'name'   => $user->name,
            'empreendimento' => ['id'=>$e->id, 'nome'=>$e->nome],
        ]);

        \App\Models\WhatsappSession::where('phone', $user->whatsapp)
  ->update(['last_interaction_at' => now()]);
    }
}
