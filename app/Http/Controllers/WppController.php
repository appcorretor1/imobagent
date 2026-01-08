<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\WhatsappMessage;
use App\Models\WhatsappThread;
use App\Models\Empreendimento;
use App\Services\VectorStoreService;
use App\Models\WhatsappQaCache;
use OpenAI;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\EmpreendimentoUnidade;
use App\Models\EmpreendimentoMidia;
use App\Services\WppSender;



class WppController extends Controller
{

        private const GALERIA_TIMEOUT_MIN = 5; // janela de 5 minutos
    
    /** TTL do mapa de empreendimentos (minutos) */
    protected int $empMapTtlMinutes = 15;
    /** Timeout de inatividade (horas) */
    protected int $sessionTimeoutHours = 8;

   public function inbound(Request $r)
    {
        // --- Pega payload cru e normaliza valores que podem vir como array ---
        $p = $r->all();
        $phoneRaw = $this->scalarize($p['phone'] ?? ($p['from'] ?? ''));
        $textRaw  = $this->scalarize($p['text']  ?? ($p['message'] ?? ''));

        $phone = preg_replace('/\D+/', '', (string)$phoneRaw);
        $text  = trim((string)$textRaw);

        if (!$phone) {
            Log::warning('WPP inbound sem phone', ['payload' => $p]);
            return response()->json(['ok' => false, 'error' => 'missing phone'], 422);
        }

      // Detecta se veio alguma mídia no payload
$hasMedia = false;

// Z-API costuma mandar fileUrl quando é mídia/arquivo
if (!empty($p['fileUrl'])) {
    $hasMedia = true;
}

// Algumas instâncias mandam um array "medias"
if (isset($p['medias']) && is_array($p['medias']) && count($p['medias']) > 0) {
    $hasMedia = true;
}

// Campos genéricos que também podem indicar mídia
if (!empty($p['image']) || !empty($p['video']) || !empty($p['document'])) {
    $hasMedia = true;
}


/**
 * 🔹 IMPORTANTE: ignorar mensagens SEM TEXTO E SEM MÍDIA
 * Isso evita responder webhooks internos da Z-API (status, confirmação, etc.).
 */
if ($text === '' && !$hasMedia) {
    Log::info('WPP inbound texto vazio e sem mídia, ignorando', [
        'phone'   => $phone,
        'payload' => array_keys($p),
    ]);

    return response()->noContent();
}



        /**
         * 🔒 HARD-GATE: só continua se o número existir na tabela users
         * Campos considerados: users.phone OU users.whatsapp
         */
        $user = User::where('phone', $phone)
            ->orWhere('whatsapp', $phone)
            ->first();

        if (!$user) {
            Log::info('WPP inbound – Número não cadastrado, fale com o administrador', ['phone' => $phone]);

            // mensagem que vai para o WhatsApp
            $this->sendText($phone, 'Número não cadastrado, fale com o administrador');

            // resposta HTTP para log/integração
            return response()->json([
                'ok'    => false,
                'error' => 'Número não cadastrado, fale com o administrador',
            ], 403);
        }

        $norm = $this->normalizeText($text);

        // ================== DEDUP (idempotência) ==================
        $providerMsgId = $this->scalarize($p['messageId'] ?? ($p['id'] ?? ''));
        if ($providerMsgId !== '') {
            $dedupKey = 'wpp:dedup:msg:' . $providerMsgId;
        } else {
            $dedupKey = 'wpp:dedup:ph:' . $phone . ':' . sha1($norm);
        }

        if (Cache::has($dedupKey)) {
            Log::info('WPP dedup: ignorando repetida', [
                'phone' => $phone,
                'norm'  => $norm,
                'msgId' => $providerMsgId ?: null,
            ]);
            return response()->json(['ok' => true, 'dedup' => true]);
        }
        Cache::put($dedupKey, 1, now()->addSeconds(15));
        // ================== FIM DEDUP ==================

        // --------------------------------------------------------------------
// Comando global: criar empreendimento / criar revenda
// Pode ser chamado a qualquer momento da conversa
// --------------------------------------------------------------------
if (str_contains($norm, 'criar empreendimento') || str_contains($norm, 'criar revenda')) {

    // vamos garantir que o thread exista primeiro
    $thread = WhatsappThread::firstOrCreate(
        ['phone' => $phone],
        [
            'thread_id' => 'thread_' . Str::random(24),
            'selected_empreendimento_id' => null,
            'empreendimento_id'          => null,
            'state' => 'awaiting_emp_choice',
        ]
    );

    // se ainda não tiver corretor vinculado, tenta vincular
    $this->attachCorretorToThread($thread, $phone);

    if (empty($thread->corretor_id)) {
        $this->sendText(
            $phone,
            "⚠️ Não consegui identificar seu usuário corretor na plataforma.\n" .
            "Verifique se seu número está cadastrado corretamente e tente novamente."
        );

        return response()->json(['ok' => true, 'handled' => 'criar_empreendimento_sem_corretor']);
    }

    // coloca o thread em estado de criação de revenda
    $thread->state = 'creating_revenda_nome';
    $thread->save();

    $this->sendText(
        $phone,
        "✨ Vamos criar um novo empreendimento de revenda só seu.\n\n" .
        "Me envie agora *o nome* desse novo empreendimento."
    );

    return response()->json(['ok' => true, 'handled' => 'criar_empreendimento_start']);
}


        $thread = WhatsappThread::firstOrCreate(
            ['phone' => $phone],
            [
                'thread_id' => 'thread_' . Str::random(24),
                'selected_empreendimento_id' => null,
                'empreendimento_id' => null,
                'state' => 'awaiting_emp_choice',
            ]
        );

       // Comando: criar empreendimento / criar revenda (sempre disponível)
if (str_contains($norm, 'criar empreendimento') || str_contains($norm, 'criar revenda')) {

    // garante que o corretor está vinculado ao thread
    $this->attachCorretorToThread($thread, $phone);

    if (empty($thread->corretor_id)) {
        $this->sendText(
            $phone,
            "⚠️ Não consegui identificar seu usuário corretor. Verifique se seu número está cadastrado corretamente na plataforma."
        );
        return response()->json(['ok' => true, 'handled' => 'criar_empreendimento_sem_corretor']);
    }

    $thread->state = 'creating_revenda_nome';
    $thread->save();

    $this->sendText(
        $phone,
        "✨ Vamos criar um novo empreendimento de revenda só seu.\n\n" .
        "Me envie agora *o nome* desse novo empreendimento."
    );

    return response()->json(['ok' => true, 'handled' => 'criar_empreendimento_start']);
}


        /**
         * ⏰ TIMEOUT DE INATIVIDADE (8 HORAS)
         * Se ficar 8h sem nenhuma mensagem nesse thread,
         * resetamos para a "tela inicial" de empreendimentos.
         */
        $sessionExpired = false;

        try {
            // última mensagem do thread
            $lastMsg = WhatsappMessage::where('thread_id', $thread->id)
                ->latest('created_at')
                ->first();

            $lastActivityAt = $lastMsg->created_at
                ?? $thread->updated_at
                ?? $thread->created_at;

            if ($lastActivityAt) {
                if (!$lastActivityAt instanceof Carbon) {
                    $lastActivityAt = Carbon::parse($lastActivityAt);
                }

                if ($lastActivityAt->lt(now()->subHours(8))) {
                    $sessionExpired = true;

                    Log::info('WPP: sessão expirada por inatividade (>8h)', [
                        'phone'        => $phone,
                        'lastActivity' => $lastActivityAt->toDateTimeString(),
                    ]);

                    // reset de estado para "tela de empreendimentos"
                    $ctx = $thread->context ?? [];

                    unset($ctx['emp_map'], $ctx['emp_map_created_at']);
                    unset($ctx['catalog_map'], $ctx['catalog_created_at']);

                    $thread->selected_empreendimento_id = null;
                    $thread->empreendimento_id          = null;
                    $thread->state                      = 'awaiting_emp_choice';
                    $thread->context                    = $ctx;

                    $thread->save();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WPP: falha ao calcular inatividade', [
                'phone' => $phone,
                'err'   => $e->getMessage(),
            ]);
        }

        /**
         * 👋 Saudação:
         * - primeira vez (thread recém-criado)
         * - OU sessão expirada por inatividade
         */
        if ($thread->wasRecentlyCreated || $sessionExpired) {
            $nome = trim($user->name ?? $user->first_name ?? '');
            if ($nome === '') {
                $nome = 'amigo';
            }

            $saudacao = "Olá {$nome}! 👋\nMe diga o que você deseja saber sobre os empreendimentos.";
            $this->sendText($phone, $saudacao);
        }

        // 🔹 tenta vincular corretor e, se existir, company_id também
$this->attachCorretorToThread($thread, $phone);

// -------------------------------------------------------
// PROCESSAR MÍDIAS (foto/vídeo) ENVIADAS PELO WHATSAPP
// -------------------------------------------------------

// 👉 Usa SEMPRE $p (payload bruto da request), não $payload
if ($hasMedia) {

    // 1) Determinar em QUAL empreendimento salvar
    $empreendimentoId = $thread->selected_empreendimento_id;

    // 2) Se não tiver selecionado, tenta o último usado na galeria
    $ctx = $thread->context ?? [];
    if (!is_array($ctx)) {
        $ctx = json_decode($ctx, true) ?: [];
    }

    if (!$empreendimentoId) {
        $lastEmp = $ctx['last_gallery_emp_id'] ?? null;
        $lastAt  = $ctx['last_gallery_at'] ?? null;

        if ($lastEmp && $lastAt) {
            try {
                $lastAtCarbon = \Carbon\Carbon::parse($lastAt);
            } catch (\Throwable $e) {
                $lastAtCarbon = null;
            }

            // ainda dentro da janela de timeout da galeria?
            if ($lastAtCarbon && $lastAtCarbon->gt(now()->subMinutes(self::GALERIA_TIMEOUT_MIN))) {
                $empreendimentoId = (int) $lastEmp;
            } else {
                // 🔴 JANELA EXPIRADA → perguntar se quer usar o último empreendimento
                $ctx['gallery_ask_emp'] = (int) $lastEmp;
                $thread->context = $ctx;
                $thread->save();

                $emp = \App\Models\Empreendimento::find($lastEmp);
                $nomeEmp = $emp?->nome ?? 'último empreendimento que você usou';

                $this->sendWppMessage(
                    $phone,
                    "Você quer adicionar essas mídias na galeria do empreendimento *{$nomeEmp}*?\n\n" .
                    "Responda com *SIM* ou *NÃO*.\n\n" .
                    "Depois é só reenviar as fotos/vídeos 🙂"
                );

                // não salva nada agora, espera a resposta do usuário
                return response()->json(['ok' => true, 'handled' => 'galeria_pergunta']);
            }
        }
    }

    // 3) Se mesmo assim não tiver empreendimento, não dá para salvar nada ainda
    if (!$empreendimentoId) {
        // aqui você pode só seguir para a IA, ou orientar o cara a escolher um empreendimento
        // Vou preferir orientar:
        $this->sendText(
            $phone,
            "Antes de salvar as fotos, preciso saber em qual empreendimento você quer usar.\n\n" .
            "Digite *mudar empreendimento* para escolher um da lista ou *criar empreendimento* para cadastrar um novo."
        );
        return response()->json(['ok' => true, 'handled' => 'media_sem_emp']);
    }

    // 4) Descobrir o corretor (user) vinculado ao thread
    $corretorId = $thread->user_id ?? $thread->corretor_id;
    if (!$corretorId) {
        // tenta vincular na marra
        $this->attachCorretorToThread($thread, $phone);
        $corretorId = $thread->user_id ?? $thread->corretor_id;
    }

    if (!$corretorId) {
        $this->sendText(
            $phone,
            "⚠️ Não consegui vincular seu usuário corretor. Verifique se seu número está cadastrado corretamente na plataforma."
        );
        return response()->json(['ok' => false, 'error' => 'sem_corretor_para_midias'], 422);
    }

     // 5) Montar lista de URLs de mídia a partir do payload da Z-API
$urls = [];

// a) Cenário simples: veio um único fileUrl na raiz
if (!empty($p['fileUrl']) && is_string($p['fileUrl'])) {
    $urls[] = $p['fileUrl'];
}

// b) Algumas instâncias mandam um array "medias"
if (isset($p['medias']) && is_array($p['medias'])) {
    foreach ($p['medias'] as $m) {
        $u = $m['mediaUrl'] ?? $m['fileUrl'] ?? null;
        if ($u && is_string($u)) {
            $urls[] = $u;
        }
    }
}

// c) Integrações que mandam via "messages" (Make, etc.)
if (!empty($p['messages']) && is_array($p['messages'])) {
    foreach ($p['messages'] as $msg) {
        if (!empty($msg['mimetype']) && !empty($msg['mediaUrl']) && is_string($msg['mediaUrl'])) {
            $urls[] = $msg['mediaUrl'];
        }
    }
}

/**
 * ✅ Z-API padrão: a foto da MENSAGEM vem em image.imageUrl
 *    https://developer.z-api.io (webhook "Ao receber")
 */
if (!empty($p['image']) && is_array($p['image'])) {
    \Log::info('WPP galeria debug image array', [
        'phone'      => $phone,
        'image_keys' => array_keys($p['image']),
        'image_url'  => $p['image']['imageUrl'] ?? null,
    ]);

    if (!empty($p['image']['imageUrl']) && is_string($p['image']['imageUrl'])) {
        $urls[] = $p['image']['imageUrl'];
    }
}

// 🚫 photo = avatar do usuário → NÃO salvar na galeria
if (!empty($p['photo'])) {
    if (is_array($p['photo'])) {
        \Log::info('WPP galeria debug photo array (IGNORADO PARA GALERIA)', [
            'phone'      => $phone,
            'photo_keys' => array_keys($p['photo']),
        ]);
    } elseif (is_string($p['photo'])) {
        \Log::info('WPP galeria debug photo scalar (IGNORADO PARA GALERIA)', [
            'phone'  => $phone,
            'sample' => substr($p['photo'], 0, 80),
        ]);
    }
}

// Limpa duplicados
$urls = array_values(array_unique(array_filter($urls)));


    if (empty($urls)) {
        \Log::warning('WPP galeria: hasMedia=true mas nenhuma URL encontrada', [
            'phone'   => $phone,
            'payload' => array_keys($p),
        ]);

        // Deixa seguir o fluxo normal (IA) para não travar
    } else {
        $salvos = 0;

        foreach ($urls as $u) {
            try {
                // 👉 Usa exatamente a tua função auxiliar
                $this->saveEmpreendimentoMediaFromUrl($u, (int) $empreendimentoId, (int) $corretorId);
                $salvos++;
            } catch (\Throwable $e) {
                \Log::warning('WPP galeria: erro ao salvar mídia', [
                    'phone' => $phone,
                    'url'   => $u,
                    'err'   => $e->getMessage(),
                ]);
            }
        }

      if ($salvos > 0) {

    // 🔄 Mensagem de progresso (no máx 1x a cada 5s por telefone)
    $progressKey = "galeria:progress:{$phone}";
    if (Cache::add($progressKey, 1, now()->addSeconds(5))) {
        $this->sendText(
            $phone,
            "⏳ Estou salvando suas fotos e vídeos na galeria desse empreendimento.\n" .
            "Pode continuar enviando, vou te avisar quando terminar de salvar esse lote. 🙂"
        );
    }

    // 1) Atualiza contexto da última galeria usada
    $ctx = $thread->context ?? [];
    if (!is_array($ctx)) {
        $ctx = json_decode($ctx, true) ?: [];
    }

    $ctx['last_gallery_emp_id'] = (int) $empreendimentoId;
    $ctx['last_gallery_at']     = now()->toIso8601String();
    unset($ctx['gallery_ask_emp']);

    $thread->context = $ctx;
    $thread->save();

    // 2) Acumula o lote em cache (por phone + empreendimento + corretor)
    $batchKey = "galeria:batch:{$phone}:{$empreendimentoId}:{$corretorId}";

    $batch = Cache::get($batchKey, [
        'count'   => 0,
        'last_at' => null,
    ]);

    $batch['count']   = ($batch['count'] ?? 0) + $salvos;
    $batch['last_at'] = now()->toIso8601String();

    // guarda por uns minutos, só pra garantir
    Cache::put($batchKey, $batch, now()->addMinutes(10));

    // 3) Dispara job "debounced" para enviar o resumo
    \App\Jobs\SendGaleriaResumoJob::dispatch(
        $phone,
        (int) $empreendimentoId,
        (int) $corretorId
    )->delay(now()->addSeconds(5));

    return response()->json(['ok' => true, 'handled' => 'galeria_midias_salvas']);
}

    }
}
// -------------------------------------------------------
// FIM PROCESSAMENTO DE MÍDIAS
// -------------------------------------------------------





// 🔹 Registra a mensagem recebida do usuário/corretor (apenas texto)
if ($text !== '') {
    $this->storeMessage($thread, [
        'sender' => 'user',
        'type'   => 'text',
        'body'   => $text,
        'meta'   => [
            'provider_msg_id' => $providerMsgId ?: null,
            'raw_payload'     => $p,
        ],
    ]);
}

$ctx = $thread->context ?? [];

// (se não tiver, adiciona esse tratamento)
if (!is_array($ctx)) {
    $ctx = json_decode($ctx, true) ?: [];
}

        Log::info('WPP inbound start', [
            'phone'  => $phone,
            'state'  => $thread->state,
            'sel_eid'=> $thread->selected_empreendimento_id,
            'has_map'=> is_array(data_get($ctx,'emp_map')),
            'map_sz' => is_array(data_get($ctx,'emp_map')) ? count(data_get($ctx,'emp_map')) : 0,
            'expired'=> $this->isEmpMapExpired($ctx),
            'text'   => $text,
            'norm'   => $norm,
        ]);

        // -------------------------------------------------------
// CONFIRMAÇÃO DA GALERIA (SIM / NÃO)
// -------------------------------------------------------
if (!empty($text) && isset($ctx['gallery_ask_emp'])) {
    $resp = \Illuminate\Support\Str::lower(trim($norm));

    if (in_array($resp, ['sim', 's', 'yes', 'y'])) {

        $empreendimentoId = (int) $ctx['gallery_ask_emp'];
        unset($ctx['gallery_ask_emp']);

        // deixa esse empreendimento como selecionado para as próximas mídias
        $thread->selected_empreendimento_id = $empreendimentoId;

        // já atualiza também como "última galeria usada"
        $ctx['last_gallery_emp_id'] = $empreendimentoId;
        $ctx['last_gallery_at']     = now()->toIso8601String();

        $thread->context = $ctx;
        $thread->save();

        $emp = \App\Models\Empreendimento::find($empreendimentoId);
        $nomeEmp = $emp?->nome ?? 'empreendimento';

        $this->sendWppMessage(
            $phone,
            "Perfeito! As próximas mídias que você enviar vou salvar na galeria do empreendimento *{$nomeEmp}*."
        );

        return response()->json(['ok' => true, 'handled' => 'galeria_confirmada']);
    }

    if (in_array($resp, ['nao', 'não', 'n'])) {
        unset($ctx['gallery_ask_emp']);
        $thread->context = $ctx;
        $thread->save();

        $this->sendWppMessage(
            $phone,
            "Beleza, não vou salvar essas mídias em nenhuma galeria por enquanto. 👍"
        );

        return response()->json(['ok' => true, 'handled' => 'galeria_recusada']);
    }
}



// ===== TRATAR RESPOSTA AO MENU (1-8) =====
if (!empty(data_get($ctx, 'shortcut_menu.shown_at')) && preg_match('/^\s*[1-8]\s*$/', $norm)) {
    $option = trim($norm);

    // se ainda não escolheu empreendimento, não adianta (exceto opções que não precisam)
    if (empty($thread->selected_empreendimento_id) && !in_array($option, ['0'])) {
        // limpa flag de menu
        $ctx = $thread->context ?? [];
        unset($ctx['shortcut_menu']);
        $thread->context = $ctx;
        $thread->save();

        return $this->sendText(
            $phone,
            "Antes de usar o menu, selecione um empreendimento enviando o número na lista." . $this->footerControls()
        );
    }

    // limpamos o flag de menu pra não confundir com outros números (ex.: escolher empreendimento)
    $ctx = $thread->context ?? [];
    unset($ctx['shortcut_menu']);
    $thread->context = $ctx;
    $thread->save();

    $empId = (int) $thread->selected_empreendimento_id;

    // 1 → atalho para "ver arquivos"
    if ($option === '1') {
        $text = 'ver arquivos';
        $norm = $this->normalizeText($text);
        // NÃO dá return aqui, deixa seguir o fluxo até o SUPER HARD-GATE ARQUIVOS
    }
    // 2 → instrução para solicitar arquivos
    elseif ($option === '2') {
        return $this->sendText(
            $phone,
            "Para solicitar arquivos, primeiro diga: *ver arquivos*\n" .
            "Depois responda com os números dos arquivos que deseja (ex: 1,2,5)" . 
            $this->footerControls()
        );
    }
    // 3 → atalho para "quais unidades livres"
    elseif ($option === '3') {
        $answer = $this->handleUnidadesPergunta($empId, $this->normalizeText('quais unidades livres'));
        if ($answer !== null) {
            return $this->sendText($phone, $answer . $this->footerControls());
        }
    }
    // 4 → instrução para consultar pagamento
    elseif ($option === '4') {
        return $this->sendText(
            $phone,
            "Para consultar informações de pagamento, pergunte algo como:\n" .
            "• *pagamento unidade 301 torre 5*\n" .
            "• *informações de pagamento unidade 2201*\n" .
            "• *tabela unidade 101*" .
            $this->footerControls()
        );
    }
    // 5 → instrução para gerar proposta
    elseif ($option === '5') {
        return $this->sendText(
            $phone,
            "Para gerar proposta em PDF, pergunte algo como:\n" .
            "• *proposta unidade 301 torre 5*\n" .
            "• *gerar proposta unidade 2201*\n" .
            "• *proposta PDF unidade 101*" .
            $this->footerControls()
        );
    }
    // 6 → instrução para atualizar status
    elseif ($option === '6') {
        return $this->sendText(
            $phone,
            "Para atualizar status de unidades, envie algo como:\n" .
            "• *unidade 301 torre 5 vendida*\n" .
            "• *unidade 2201 reservada*\n" .
            "• *unidade 101 livre*" .
            $this->footerControls()
        );
    }
    // 7 → instrução para galeria
    elseif ($option === '7') {
        return $this->sendText(
            $phone,
            "Para adicionar fotos/vídeos na galeria:\n" .
            "1. Selecione o empreendimento (se ainda não selecionou)\n" .
            "2. Envie as fotos/vídeos aqui mesmo\n" .
            "3. Confirme quando perguntado\n\n" .
            "As mídias serão salvas automaticamente na galeria do empreendimento selecionado." .
            $this->footerControls()
        );
    }
    // 8 → instrução para perguntas
    elseif ($option === '8') {
        return $this->sendText(
            $phone,
            "Você pode fazer qualquer pergunta sobre o empreendimento!\n\n" .
            "Exemplos:\n" .
            "• *qual endereço do empreendimento?*\n" .
            "• *quais as amenidades?*\n" .
            "• *qual o preço base?*\n" .
            "• *onde fica localizado?*\n" .
            "• *quais os diferenciais?*\n\n" .
            "A IA vai consultar os documentos e informações do empreendimento para responder." .
            $this->footerControls()
        );
    }
}
// ===== FIM RESPOSTA AO MENU =====

// ===== FIM MENU DE ATALHOS =====


        // Expira/limpa mapa antigo ou muito pequeno (ex.: inconsistência)
        $mapAtual = data_get($ctx, 'emp_map');
        $mapCount = is_array($mapAtual) ? count($mapAtual) : 0;
        if ($this->isEmpMapExpired($ctx) || $mapCount < 1) {
            $this->clearEmpMap($thread);
            $thread->save();
            Log::info('WPP: mapa expirado/insuficiente → limpo', ['phone' => $phone, 'mapCount' => $mapCount]);
            $ctx = $thread->context ?? [];
        }

        // Se já tiver empreendimento selecionado, tenta primeiro:
// Se já tiver empreendimento selecionado, primeiro tenta COMANDO de status,
// depois pergunta sobre unidades (disponibilidade/lista), antes da IA normal.
if ($thread && $thread->selected_empreendimento_id) {

    // 🔐 Só DIRETOR pode alterar status de unidade
    if ($respStatus = $this->handleUnidadesStatusComando(
        (int) $thread->selected_empreendimento_id,
        $norm,
        $this->userCanAlterUnidades($user) // ← passa permissão aqui
    )) {
        return $this->sendText($phone, $respStatus . $this->footerControls());
    }

    // 2) perguntas sobre unidades ("a 102 está livre?", "quais unidades livres?")
    if ($respUnidades = $this->handleUnidadesPergunta(
        (int) $thread->selected_empreendimento_id,
        $norm
    )) {
        return $this->sendText($phone, $respUnidades . $this->footerControls());
    }
}

        // ================== SUPER HARD-GATE: ARQUIVOS (sempre antes da IA) ==================
        if ($text !== '' && stripos(mb_strtolower($text), 'arquiv') !== false) {
            Log::info('WPP SUPER-GATE arquivos acionado', ['phone' => $phone, 'msg' => $text]);

            // precisa ter empreendimento selecionado para listar arquivos do S3
            if (empty($thread->selected_empreendimento_id)) {
                Log::info('WPP arquivos: sem empreendimento selecionado → listar empreendimentos', ['phone'=>$phone]);
                return $this->sendEmpreendimentosList($thread);
            }

            $empId     = (int) $thread->selected_empreendimento_id;
            $companyId = $this->resolveCompanyIdForThread($thread);
            $filesKey  = $this->fileListKey($phone, $empId);

            // A) Pedidos explícitos para listar
            if (preg_match('/\b(ver|listar|mostrar)\s+arquivos\b/i', $text)) {
                $listText = $this->cacheAndBuildFilesList($filesKey, $empId, $companyId);
                return $this->sendText($phone, $listText . "\n\nResponda com os números (ex.: 1,2,5)." . $this->footerControls());
            }

          
           // B) Índices com lista em cache → enviar como documento/mídia ou link de fotos
if ($this->isMultiIndexList($text) && Cache::has($filesKey)) {
    Log::info('WPP arquivos: bloco B acionado (índices + cache)', [
        'phone'    => $phone,
        'msg'      => $text,
        'filesKey' => $filesKey,
    ]);

    $indices = $this->parseIndices($text);

    Log::info('WPP arquivos: índices parseados', [
        'phone'   => $phone,
        'msg'     => $text,
        'indices' => $indices,
    ]);

    if (empty($indices)) {
        Log::warning('WPP arquivos: parseIndices vazio', [
            'phone' => $phone,
            'msg'   => $text,
        ]);

        return $this->sendText(
            $phone,
            'Não entendi os números enviados. Tente algo como: 1,2,5' . $this->footerControls()
        );
    }

    $items = Cache::get($filesKey, []);
    Log::info('WPP arquivos: items recuperados do cache', [
        'phone'    => $phone,
        'filesKey' => $filesKey,
        'qtde'     => count($items),
        'items'    => $items,
    ]);

    $byIdx = collect($items)->keyBy('index');

    $picked = [];
    foreach ($indices as $i) {
        if ($byIdx->has($i)) {
            $picked[] = $byIdx->get($i);
        }
    }

    Log::info('WPP arquivos: items selecionados pelos índices', [
        'phone'   => $phone,
        'indices' => $indices,
        'picked'  => $picked,
    ]);

    if (empty($picked)) {
        return $this->sendText(
            $phone,
            'Esses índices não batem com a lista atual. Quer listar novamente? Diga: ver arquivos' . $this->footerControls()
        );
    }

    Log::info('WPP arquivos: iniciando envio', [
        'phone' => $phone,
        'count' => count($picked),
    ]);

    $this->sendText($phone, "⏳ Enviando *" . count($picked) . "* item(s)…");

    $sent = 0;
    $vias = [];

    foreach ($picked as $pitem) {
        $type = $pitem['type'] ?? 'file';

        Log::info('WPP arquivos: processando item selecionado', [
            'phone' => $phone,
            'item'  => $pitem,
            'type'  => $type,
        ]);

        // 👉 Se for o bundle de fotos: envia só o link
        if ($type === 'photos_bundle') {
            Log::info('WPP arquivos: item é bundle de fotos, enviando link em vez de mídia', [
                'phone' => $phone,
                'item'  => $pitem,
            ]);

            $urlFotos = route('empreendimentos.fotos', [
                'company'  => $companyId,
                'empreend' => $empId,
            ]);

            Log::info('WPP arquivos: URL de fotos gerada', [
                'phone'    => $phone,
                'urlFotos' => $urlFotos,
            ]);

            $this->sendText(
                $phone,
                "Vou te mandar o link com todas as fotos do empreendimento:\n" .
                "🔗 {$urlFotos}"
            );

            // NÃO incrementa $sent aqui, é só link
            continue;
        }

        // 👉 Arquivo normal (PDF, etc.) segue fluxo padrão
        Log::info('Z-API envio: preparando arquivo normal', [
            'file'  => $pitem['name'] ?? null,
            'phone' => $phone,
            's3'    => $pitem['path'] ?? null,
            'mime'  => $pitem['mime'] ?? null,
        ]);

        $res = $this->sendMediaSmart(
            $phone,
            $pitem['path'] ?? '',
            $pitem['name'] ?? '',
            $pitem['mime'] ?? null
        );

        $vias[] = $res['via'] ?? 'n/a';

        if (!empty($res['ok'])) {
            $sent++;
        }

        Log::info('WPP arquivos: resultado sendMediaSmart', [
            'phone' => $phone,
            'res'   => $res,
        ]);
    }

    if ($sent > 0) {
        Log::info('WPP arquivos: envio finalizado', [
            'phone'    => $phone,
            'total'    => count($picked),
            'enviados' => $sent,
            'vias'     => $vias,
        ]);

        $this->sendText(
            $phone,
            "✅ Envio iniciado de *{$sent}* arquivo(s)." .
            (env('WPP_DEBUG') ? " via: " . implode(', ', array_unique($vias)) : "") .
            " Se não aparecerem, responda novamente os números." . $this->footerControls()
        );
    } else {
        Log::info('WPP arquivos: nenhum arquivo enviado (possivelmente só bundle de fotos)', [
            'phone'     => $phone,
            'total'     => count($picked),
            'temBundle' => collect($picked)->contains(fn($i) => ($i['type'] ?? 'file') === 'photos_bundle'),
        ]);
    }

    return response()->noContent();
}


            // C) Índices sem lista em cache
            if ($this->isMultiIndexList($text) && !Cache::has($filesKey)) {
                return $this->sendText($phone, "Para solicitar arquivos, primeiro diga: *ver arquivos*.\nDepois responda com os números (ex.: 1,2,5)." . $this->footerControls());
            }

            // D) Qualquer outra frase com "arquiv" → instrução
            return $this->sendText($phone, "Entendi que você quer um arquivo.\nAntes, diga: *ver arquivos*.\nVou listar e você escolhe (ex.: 1,2,5)." . $this->footerControls());
        }
        // ================== FIM SUPER HARD-GATE ARQUIVOS ==================

       // ===== MINI-GATE: resposta por índices quando já há lista de arquivos em cache =====
if (!empty($thread->selected_empreendimento_id) && $this->isMultiIndexList($text)) {
    $empId     = (int) $thread->selected_empreendimento_id;
    $companyId = $this->resolveCompanyIdForThread($thread);
    $filesKey  = $this->fileListKey($phone, $empId);

    if (Cache::has($filesKey)) {
        $indices = $this->parseIndices($text);
        if (empty($indices)) {
            return $this->sendText(
                $phone,
                'Não entendi os números enviados. Ex.: 1,2,5' . $this->footerControls()
            );
        }

        $items = Cache::get($filesKey, []);
        $byIdx = collect($items)->keyBy('index');

        $picked = [];
        foreach ($indices as $i) {
            if ($byIdx->has($i)) {
                $picked[] = $byIdx->get($i);
            }
        }

        if (empty($picked)) {
            return $this->sendText(
                $phone,
                'Esses índices não existem. Diga: *ver arquivos*' . $this->footerControls()
            );
        }

        Log::info('WPP MINI-GATE arquivos: iniciando envio', [
            'phone'  => $phone,
            'count'  => count($picked),
            'items'  => $picked,
            'empId'  => $empId,
            'companyId' => $companyId,
        ]);

        $this->sendText($phone, "⏳ Enviando *" . count($picked) . "* item(s)…");

        $vias      = [];
        $sent      = 0;
        $temBundle = false;

        foreach ($picked as $pitem) {
            $type = $pitem['type'] ?? 'file';

            Log::info('WPP MINI-GATE arquivos: processando item selecionado', [
                'phone' => $phone,
                'item'  => $pitem,
                'type'  => $type,
            ]);

            // 👉 Se for o bundle de fotos: envia só o link com todas as fotos
            if ($type === 'photos_bundle') {
                $temBundle = true;

                $urlFotos = route('empreendimentos.fotos', [
                    'company'  => $companyId,
                    'empreend' => $empId,
                ]);

                Log::info('WPP MINI-GATE arquivos: enviando link de fotos do empreendimento', [
                    'phone'    => $phone,
                    'urlFotos' => $urlFotos,
                ]);

                $this->sendText(
                    $phone,
                    "Vou te mandar o link com todas as fotos do empreendimento:\n" .
                    "🔗 {$urlFotos}"
                );

                // não chama sendMediaSmart para o bundle
                continue;
            }

            // Arquivo normal (PDF, XLS, etc.)
            $res = $this->sendMediaSmart(
                $phone,
                $pitem['path'] ?? '',
                $pitem['name'] ?? '',
                $pitem['mime'] ?? null
            );

            $vias[] = $res['via'] ?? 'n/a';
            if (!empty($res['ok'])) {
                $sent++;
            }

            Log::info('WPP MINI-GATE arquivos: resultado sendMediaSmart', [
                'phone' => $phone,
                'res'   => $res,
            ]);
        }

        Log::info('WPP MINI-GATE arquivos: envio finalizado', [
            'phone'     => $phone,
            'total'     => count($picked),
            'enviados'  => $sent,
            'vias'      => $vias,
            'temBundle' => $temBundle,
        ]);

        if ($sent > 0) {
            $this->sendText(
                $phone,
                "✅ Envio iniciado de *{$sent}* arquivo(s)." .
                (env('WPP_DEBUG') ? " via: " . implode(', ', array_unique($vias)) : "") .
                " Se não aparecerem, responda novamente os números." . $this->footerControls()
            );
        } elseif ($temBundle) {
            // Só bundle de fotos → já mandamos o link, não precisa texto extra
        }

        return response()->noContent();
    } else {
        return $this->sendText(
            $phone,
            "Para enviar arquivos, primeiro peça para eu *ver arquivos*.\n" .
            "Eu listo e você responde com os números (ex.: 1,2,5)." . $this->footerControls()
        );
    }
}

        // ===== RESUMO (sempre disponível) - ANTES DE QUALQUER PROCESSAMENTO DE IA =====
if ($this->isResumoCommand($norm)) {
    $resumoText = $this->buildResumoText($thread);
    return $this->sendText($phone, $resumoText);
}

        // ===== MENU DE ATALHOS (só com empreendimento selecionado) - ANTES DE QUALQUER PROCESSAMENTO DE IA =====
if ($this->isShortcutMenuCommand($norm)) {
    // Menu só funciona se tiver empreendimento selecionado
    if (empty($thread->selected_empreendimento_id)) {
        return $this->sendText(
            $phone,
            "⚠️ Para acessar o menu, primeiro selecione um empreendimento.\n\n" .
            "Digite *mudar empreendimento* para ver a lista de empreendimentos disponíveis." .
            $this->footerControls()
        );
    }
    
    $menuText = $this->buildShortcutMenuText($thread);

    // marca no contexto que o último comando foi "menu" (pra interpretar 1-8 depois)
    $ctx = $thread->context ?? [];
    $ctx['shortcut_menu'] = [
        'shown_at' => now()->toIso8601String(),
    ];
    $thread->context = $ctx;
    $thread->save();

    return $this->sendText($phone, $menuText);
}

        // ===== MODO CATÁLOGO: perguntar sobre TODOS os empreendimentos (usando texto_ia) =====
        // Só tenta catálogo se não for apenas lista de índices
        if (!$this->isMultiIndexList($text)) {
            $catalog = $this->maybeHandleCatalogQuestion($thread, $text);

            if (!empty($catalog) && !empty($catalog['answer'])) {
                // salva mapa de catálogo no contexto
                $ctx = $thread->context ?? [];
                $ctx['catalog_map']        = $catalog['map'] ?? [];
                $ctx['catalog_created_at'] = now()->toIso8601String();
                $thread->context = $ctx;
                $thread->save();

                $answerExtra = "\n\nSe quiser, responda com o *número* de um deles (ex.: 1) para eu focar nesse empreendimento.";
                $finalAnswer = $catalog['answer'] . $answerExtra;

                // registra mensagem da IA no histórico
                $this->storeMessage($thread, [
                    'sender' => 'ia',
                    'type'   => 'text',
                    'body'   => $finalAnswer,
                    'meta'   => [
                        'source' => 'catalogo_texto_ia',
                        'map'    => $catalog['map'] ?? [],
                    ],
                ]);

                return $this->sendText($phone, $finalAnswer . $this->footerControls());
            }
        }
        // ===== FIM MODO CATÁLOGO =====

        // ===== SELEÇÃO A PARTIR DO CATÁLOGO (usuário responde "1", "2" etc.) =====
        if ($idx = $this->extractIndexNumber($norm)) {
            $ctx = $thread->context ?? [];
            $catMap = data_get($ctx, 'catalog_map', []);
            if (is_array($catMap) && isset($catMap[$idx]) && !$this->isCatalogMapExpired($ctx)) {
                $empIdFromCatalog = (int) $catMap[$idx];

                // limpa catálogo do contexto
                unset($ctx['catalog_map'], $ctx['catalog_created_at']);
                $thread->context = $ctx;
                $thread->save();

                Log::info('WPP: seleção via catálogo', [
                    'phone' => $phone,
                    'idx'   => $idx,
                    'empId' => $empIdFromCatalog,
                ]);

                return $this->finalizeSelection($thread, $empIdFromCatalog);
            }
        }
        // ===== FIM SELEÇÃO A PARTIR DO CATÁLOGO =====

        // Comando para trocar empreendimento
        if ($this->isChangeEmpreendimento($norm)) {
            $this->resetEmpreendimento($thread);
            Log::info('WPP: comando de troca → listar', ['phone' => $phone]);
            return $this->sendEmpreendimentosList($thread);
        }

        // --------------------------------------------------------------------
// Estado: aguardando nome da revenda (creating_revenda_nome)
// --------------------------------------------------------------------
if ($thread->state === 'creating_revenda_nome') {

    if (trim($text) === '') {
        $this->sendText(
            $phone,
            "Por favor, me envie o *nome* do novo empreendimento que você quer criar."
        );

        return response()->json(['ok' => true, 'handled' => 'criar_empreendimento_nome_vazio']);
    }

    $nome       = trim($text);
    $corretorId = (int) $thread->corretor_id;
    $companyId  = $thread->company_id ?? null;

    $emp = new Empreendimento();
    $emp->nome             = $nome;
    $emp->is_revenda       = 1;
    $emp->dono_corretor_id = $corretorId;

    // se sua tabela tiver company_id e status, preenche
    if (Schema::hasColumn($emp->getTable(), 'company_id')) {
        $emp->company_id = $companyId;
    }
    if (Schema::hasColumn($emp->getTable(), 'status')) {
        $emp->status = 'rascunho';
    }

    $emp->save();

    // vincula esse empreendimento ao thread
    $thread->selected_empreendimento_id = $emp->id;
    $thread->state                      = 'idle';
    $thread->save();

    $this->sendText(
        $phone,
        "✅ Empreendimento de revenda criado com sucesso!\n\n" .
        "🏢 *{$emp->nome}*\n" .
        "Agora você pode enviar *fotos e vídeos* desse empreendimento aqui mesmo " .
        "que eu vou salvar tudo na sua galeria exclusiva dele. 😉"
    );

    return response()->json(['ok' => true, 'handled' => 'criar_empreendimento_nome_ok']);
}

        // Estado: aguardando escolha → tentar capturar índice
        if ($thread->state === 'awaiting_emp_choice') {
            if ($idx = $this->extractIndexNumber($norm)) {
                $map = data_get($ctx, 'emp_map', []);
                if (isset($map[$idx])) {
                    $empreendimentoId = (int) $map[$idx];
                    return $this->finalizeSelection($thread, $empreendimentoId);
                }
                // Índice inválido → relista
                return $this->sendEmpreendimentosList($thread);
            }

            // Se não mandou número, garantir que existe mapa válido; senão relista
            $mapAtual = data_get($ctx, 'emp_map');
            $expired  = $this->isEmpMapExpired($ctx);
            $mapOk    = is_array($mapAtual) && count($mapAtual) > 0;

            if (!$mapOk || $expired) {
                \Log::info('WPP: mapa inexistente/expirado → relistar', [
                    'phone' => $phone, 'mapOk' => $mapOk, 'expired' => $expired
                ]);
                return $this->sendEmpreendimentosList($thread);
            }

            // Reforço de instrução
            return $this->sendText(
                $phone,
                "Envie apenas o *número* do empreendimento (ex.: 1)." . $this->footerControls()
            );
        }

        // Se ainda não há empreendimento escolhido → listar
        if (empty($thread->selected_empreendimento_id)) {
            Log::info('WPP: sem empreendimento selecionado → listar', ['phone' => $phone]);
            return $this->sendEmpreendimentosList($thread);
        }

        // ✅ JÁ HÁ EMPREENDIMENTO SELECIONADO:
        // Primeiro tenta responder sobre UNIDADES (tabela empreendimento_unidades)
        $respUnidades = $this->handleUnidadesPergunta(
            (int) $thread->selected_empreendimento_id,
            $text
        );

        if ($respUnidades !== null) {
            return $this->sendText($phone, $respUnidades . $this->footerControls());
        }

        // Se não for pergunta de unidade, cai no fluxo normal da IA
        return $this->handleNormalAIFlow($thread, $text);
    }


    /**
 * Só pode alterar status de unidade quem tiver role = DIRETOR.
 */
protected function userCanAlterUnidades(?User $user): bool
{
    if (!$user) {
        return false;
    }

    // normaliza para minúsculo, já que no banco está "diretor", "corretor"
    $role = strtolower((string) ($user->role ?? ''));

    return $role === 'diretor';
}


    protected function attachCorretorToThread(WhatsappThread $thread, string $phone): void
    {
        // se a coluna não existir, não faz nada
        if (!Schema::hasColumn('whatsapp_threads', 'corretor_id')) {
            return;
        }

        // se já tiver corretor_id preenchido, não precisa procurar de novo
        if (!empty($thread->corretor_id)) {
            return;
        }

        // se não tiver tabela users, desiste
        if (!Schema::hasTable('users')) {
            return;
        }

        $q = DB::table('users');
        $hasCond = false;

        // tenta casar por phone / telefone / whatsapp (ajusta se tiver outro nome)
        if (Schema::hasColumn('users', 'phone')) {
            $q->orWhere('phone', $phone);
            $hasCond = true;
        }
        if (Schema::hasColumn('users', 'telefone')) {
            $q->orWhere('telefone', $phone);
            $hasCond = true;
        }
        if (Schema::hasColumn('users', 'whatsapp')) {
            $q->orWhere('whatsapp', $phone);
            $hasCond = true;
        }

        if (!$hasCond) {
            return;
        }

        $user = $q->first();
        if (!$user) {
            return;
        }

        $thread->corretor_id = $user->id;

        // opcional: se quiser já puxar company_id do corretor também
        if (Schema::hasColumn('whatsapp_threads', 'company_id') && isset($user->company_id)) {
            $thread->company_id = $user->company_id;
        }

        $thread->save();

        \Log::info('WPP thread vinculada a corretor', [
            'phone'      => $phone,
            'thread_id'  => $thread->id,
            'corretor_id'=> $thread->corretor_id,
            'company_id' => $thread->company_id ?? null,
        ]);
    }

    public function select(Request $r)
    {
        $data = $r->validate([
            'phone'            => ['required', 'string'],
            'empreendimento_id'=> ['required', 'integer', 'min:1'],
        ]);

        $phone = preg_replace('/\D+/', '', $data['phone']);
        $eid   = (int) $data['empreendimento_id'];

        $thread = WhatsappThread::firstOrCreate(['phone' => $phone]);
        return $this->finalizeSelection($thread, $eid);
    }

    // ===== Intents =====

  protected function isChangeEmpreendimento(string $norm): bool
{
    $norm = trim(mb_strtolower($norm));

    // 👇 Se a frase fala de status/unidade, NÃO é troca de empreendimento
    if (Str::contains($norm, [
        'status',
        'unidade',
        'apto',
        'apartamento',
        'lote',
        'casa',
        'torre',
        'bloco',
        'quadra',
    ])) {
        return false;
    }

    $aliases = [
        'mudar empreendimento',
        'trocar empreendimento',
        'alterar empreendimento',
        'mudar imovel',
        'trocar imovel',
        'alterar imovel',
        'trocar empreendimento por outro',
        'trocar por outro empreendimento',
        // 👈 aqui não tem mais 'mudar' sozinho
    ];

    foreach ($aliases as $a) {
        if (Str::contains($norm, $a)) {
            return true;
        }
    }

    return false;
}

    protected function extractIndexNumber(string $norm): ?string
    {
        if (preg_match('/\b(\d{1,3})\b/u', $norm, $m)) {
            $idx = ltrim($m[1], '0');
            return $idx === '' ? '0' : $idx;
        }
        return null;
    }

    protected function normalizeText(string $text): string
    {
        $t = Str::of($text)->lower()->squish()->toString();
        if (class_exists('\Normalizer')) {
            $t = \Normalizer::normalize($t, \Normalizer::FORM_D);
            $t = preg_replace('/\p{Mn}+/u', '', $t);
        }
        return $t;
    }

    /** Converte arrays do webhook em string útil (evita "Array to string conversion") */
    private function scalarize($v): string
    {
        if (!is_array($v)) return (string)$v;

        foreach (['text','body','message','id','phone','number'] as $k) {
            if (isset($v[$k]) && !is_array($v[$k])) {
                return (string)$v[$k];
            }
        }
        foreach ($v as $item) {
            if (!is_array($item)) return (string)$item;
        }
        return '';
    }

    // ===== Estado =====

    protected function resetEmpreendimento(WhatsappThread $thread): void
    {
        $thread->selected_empreendimento_id = null;
        $thread->empreendimento_id          = null;
        $thread->state                      = 'awaiting_emp_choice';
        $this->clearEmpMap($thread);

        // também limpa catálogo
        $ctx = $thread->context ?? [];
        unset($ctx['catalog_map'], $ctx['catalog_created_at']);
        $thread->context = $ctx;

        $thread->save();

        Log::info('WPP mudar empreendimento', ['phone' => $thread->phone, 'action' => 'reset_selection']);
    }

    protected function clearEmpMap(WhatsappThread $thread): void
    {
        $ctx = $thread->context ?? [];
        unset($ctx['emp_map'], $ctx['emp_map_created_at']);
        $thread->context = $ctx;
    }

    protected function isEmpMapExpired(array $ctx): bool
    {
        $created = data_get($ctx, 'emp_map_created_at');
        if (!$created) return false;
        try { return now()->diffInMinutes(Carbon::parse($created)) > $this->empMapTtlMinutes; }
        catch (\Throwable) { return true; }
    }

    protected function isCatalogMapExpired(array $ctx): bool
    {
        $created = data_get($ctx, 'catalog_created_at');
        if (!$created) return false;
        try { return now()->diffInMinutes(Carbon::parse($created)) > $this->empMapTtlMinutes; }
        catch (\Throwable) { return true; }
    }

    // ===== Query FILTRA EMPREENDIMENTOS DA EMPRESA =====

   protected function getEmpreendimentosQueryForThread(WhatsappThread $thread)
{
    $q = Empreendimento::query();

    // Se tiver coluna 'ativo', filtra apenas os ATIVOS
    if (Schema::hasColumn('empreendimentos','ativo')) {
        $q->where('ativo', 1);
    }

    // 🔐 Filtrar por empresa (tenant) com base na thread/corretor
    if (Schema::hasColumn('empreendimentos', 'company_id')) {
        $cid = $this->resolveCompanyIdForThread($thread);

        if ($cid) {
            $q->where('company_id', $cid);
        } else {
            // Se preferir não vazar empreendimentos de outras empresas:
            // $q->whereRaw('1 = 0');
        }
    }

    return $q;
}


    // ===== Helpers =====

    protected function oneLine(?string $v, int $max = 80): string
    {
        $v = trim((string) $v);
        $v = preg_replace('/\s+/', ' ', $v);
        if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max - 1) . '…';
        return $v;
    }

    protected function footerControls(): string
    {
        return "\n\nDigite *mudar empreendimento* para voltar à lista, ou *menu* para ver opções.\n";
    }

    protected function resolveCompanyIdForThread(WhatsappThread $thread): ?int
    {
        // 1) Se a thread tiver company_id, usa.
        $cid = data_get($thread, 'company_id');
        if ($cid) return (int) $cid;

        // 2) Se houver empreendimento selecionado, puxa o company_id dele.
        $eid = (int) data_get($thread, 'selected_empreendimento_id');
        if ($eid > 0) {
            $emp = Empreendimento::select('id','company_id')->find($eid);
            if ($emp && $emp->company_id) return (int) $emp->company_id;
        }

        return null;
    }

    // ===== Listagem (sem paginação) =====

    protected function sendEmpreendimentosList(WhatsappThread $thread)
    {
        $phone = $thread->phone;

        $query = $this->getEmpreendimentosQueryForThread($thread);
        $total = (clone $query)->count();
        Log::info('WPP: total de empreendimentos', ['phone'=>$phone, 'total'=>$total]);

        if ($total === 0) {
            $thread->state = 'idle';
            $thread->save();
            return $this->sendText($phone, "Não encontrei empreendimentos disponíveis no momento.");
        }

        $emps = $query->orderBy('nome')->get(['id','nome','cidade','uf']);

        $map = [];
        $linhas = [];
        $i = 1;

        foreach ($emps as $e) {
            $idx = (string) $i;
            $map[$idx] = $e->id;

            $nome   = $this->oneLine($e->nome ?? ("Empreendimento #{$e->id}"), 64);
            $cidade = $this->oneLine($e->cidade ?? '', 36);
            $uf     = $this->oneLine($e->uf ?? '', 3);

            $sufixo = ($cidade || $uf) ? " - {$cidade}" . ($uf ? "/{$uf}" : "") : "";
            $linhas[] = "{$idx}. {$nome}{$sufixo}";
            $i++;
        }

        if (empty($linhas)) {
            Log::warning('WPP: consulta retornou, mas linhas vazias', ['phone'=>$phone, 'count'=>$emps->count()]);
            $linhas[] = "1. (sem nome)";
            $map['1'] = optional($emps->first())->id ?? 0;
        }

        // Salva mapa no contexto antes de enviar
        $ctx = $thread->context ?? [];
        $ctx['emp_map']            = $map;
        $ctx['emp_map_created_at'] = now()->toIso8601String();

        $thread->context = $ctx;
        $thread->state   = 'awaiting_emp_choice';
        $thread->save();

        $header = "Escolha o empreendimento enviando o *número*:\n";
        $body   = implode("\n", $linhas);

        Log::info('WPP: enviando lista (sem paginação)', ['phone'=>$phone, 'rendered'=>count($linhas)]);
        return $this->sendText($phone, $header . $body);
    }

    // ===== Confirmar seleção =====

    protected function finalizeSelection(WhatsappThread $thread, int $empreendimentoId)
    {
        $e = Empreendimento::find($empreendimentoId);
        if (!$e) {
            Log::warning('WPP finalizeSelection: empreendimento não encontrado', [
                'phone'             => $thread->phone,
                'empreendimento_id' => $empreendimentoId
            ]);
            return $this->sendEmpreendimentosList($thread);
        }

        // Salva nos dois campos de relacionamento com empreendimento
        $thread->selected_empreendimento_id = $empreendimentoId;
        $thread->empreendimento_id          = $empreendimentoId;

        // 🔹 Se existir company_id em whatsapp_threads, preenche
        if (Schema::hasColumn('whatsapp_threads', 'company_id')) {
            $thread->company_id = $e->company_id ?? $thread->company_id;
        }

        $thread->state = 'idle';
        $this->clearEmpMap($thread);

        // limpa catálogo também ao selecionar
        $ctx = $thread->context ?? [];
        unset($ctx['catalog_map'], $ctx['catalog_created_at']);
        $thread->context = $ctx;

        $thread->save();

        $nome = $e->nome ?? "Empreendimento #{$e->id}";
        $cidadeUf = trim(($e->cidade ? "{$e->cidade}/" : '') . ($e->uf ?? ''));

        $conf  = "✅ Empreendimento selecionado:\n\n*{$nome}*";
        if ($cidadeUf) $conf .= " — {$cidadeUf}";
        $conf .= ".\n\nO que deseja saber sobre ele?\n\n";
        $conf .= "Se quiser trocar, digite *mudar empreendimento* para voltar à lista.";

        Log::info('WPP empreend. selecionado', [
            'phone'             => $thread->phone,
            'empreendimento_id' => $empreendimentoId,
            'company_id'        => $thread->company_id ?? null,
        ]);

        return $this->sendText($thread->phone, $conf);
    }

    // ===== Envio WhatsApp (Z-API) =====
protected function sendText(string $phone, string $text): array
{
    try {
        $res = app(\App\Services\WppSender::class)->sendText($phone, $text);

        if (!($res['ok'] ?? false)) {
            Log::error('Z-API erro ao enviar texto', [
                'phone' => $phone,
                'msg'   => $text,
                'res'   => $res,
            ]);
        }

        return $res;

    } catch (\Throwable $e) {
        Log::error('Z-API exception ao enviar texto', [
            'phone' => $phone,
            'msg'   => $text,
            'err'   => $e->getMessage(),
        ]);

        return ['ok' => false, 'error' => $e->getMessage()];
    }
}


    // ===== Fluxo normal (IA) =====

    protected function handleNormalAIFlow(WhatsappThread $thread, string $text)
{
    $phone = $thread->phone;
    $empId = (int) $thread->selected_empreendimento_id;

    if ($empId <= 0) {
        return $this->sendEmpreendimentosList($thread);
    }

    $question = trim($text);
    if ($question === '') {
        return $this->sendText(
            $phone,
            "Pode me dizer sua dúvida sobre o empreendimento?" . $this->footerControls()
        );
    }

    // 🔍 IA-LOCAL: tenta reaproveitar resposta do cache (exato + similaridade)
if ($localAnswer = $this->findAnswerInLocalCacheWithSimilarity($empId, $question)) {
    return $this->sendText($phone, $localAnswer . $this->footerControls());
}


    // 📄 SUPER-GATE: pedido de PROPOSTA em PDF para uma unidade específica
    if ($this->looksLikeProposalRequest($question)) {
        $phone = $thread->phone;
        $empId = (int) $thread->selected_empreendimento_id;

        Log::info('WPP PROPOSTA: pedido de proposta detectado', [
            'phone'   => $phone,
            'empId'   => $empId,
            'question'=> $question,
        ]);

        try {
            // 1) Reaproveita a mesma leitura da planilha que você já usa
            $textoPagamento = $this->answerFromExcelPayment($thread, $question);

            if ($textoPagamento === null) {
                Log::warning('WPP PROPOSTA: answerFromExcelPayment retornou NULL', [
                    'phone'    => $phone,
                    'empId'    => $empId,
                    'question' => $question,
                ]);

                return $this->sendText(
                    $phone,
                    "Não consegui encontrar essa unidade na tabela para montar a proposta.\n" .
                    "Confere se o número da unidade e da torre estão corretos e tenta de novo." .
                    $this->footerControls()
                );
            }

            Log::info('WPP PROPOSTA: texto de pagamento gerado com sucesso', [
                'phone' => $phone,
                'empId' => $empId,
            ]);

            // 2) Gera e salva o PDF com layout da imobiliária
            $empId     = (int) $thread->selected_empreendimento_id;
$unidade   = $this->parseUnidadeFromText($question);
$grupo     = $this->parseGenericGroup($question);  // torre, bloco etc.

$pdfPath = $this->buildAndStoreProposalPdf(
    $thread,
    $empId,
    $unidade,
    $grupo,
    $textoPagamento
);

            if (!$pdfPath) {
                Log::error('WPP PROPOSTA: buildAndStoreProposalPdf retornou caminho vazio/nulo', [
                    'phone' => $phone,
                    'empId' => $empId,
                ]);

                // fallback: manda só o texto mesmo
                return $this->sendText(
                    $phone,
                    "Tive um problema ao gerar o PDF da proposta. Vou te passar os valores por aqui mesmo:\n\n" .
                    $textoPagamento .
                    $this->footerControls()
                );
            }

            Log::info('WPP PROPOSTA: PDF gerado e salvo', [
                'phone'   => $phone,
                'empId'   => $empId,
                'pdfPath' => $pdfPath,
            ]);

            // 3) Envia o PDF pelo mesmo fluxo de mídia
            $sendResult = $this->sendMediaSmart(
                $phone,
                $pdfPath,
                'Proposta unidade ' . ($this->parseUnidadeFromText($question) ?? '') .
                ' - Torre ' . ($this->parseTorreFromText($question) ?? ''),
                'application/pdf',
                's3' // agora o arquivo está no S3
            );

            Log::info('WPP PROPOSTA: resultado do sendMediaSmart', [
                'phone' => $phone,
                'empId' => $empId,
                'ok'    => $sendResult['ok'] ?? null,
                'via'   => $sendResult['via'] ?? null,
                'error' => $sendResult['error'] ?? null,
                'raw'   => $sendResult,
            ]);

            if (empty($sendResult['ok'])) {
                // se não enviou de fato, não afirma que mandou
                return $this->sendText(
                    $phone,
                    "Tentei gerar a proposta em PDF, mas tive um problema no envio pelo WhatsApp.\n" .
                    "Vou te passar os valores por aqui mesmo:\n\n" .
                    $textoPagamento .
                    $this->footerControls()
                );
            }

            // 4) Mensagem de confirmação
            $this->sendText(
                $phone,
                "Acabei de te enviar o PDF da proposta dessa unidade. Qualquer dúvida, me chama aqui 👍" .
                $this->footerControls()
            );

            return response()->noContent();
        } catch (\Throwable $e) {
            \Log::error('WPP PROPOSTA: exceção ao gerar/enviar PDF', [
                'phone'    => $phone,
                'empId'    => $empId,
                'err'      => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return $this->sendText(
                $phone,
                "Tive um problema ao gerar o PDF da proposta. Vou te passar os valores por aqui mesmo:\n\n" .
                ($textoPagamento ?? 'Não consegui ler os dados dessa unidade na tabela.') .
                $this->footerControls()
            );
        }
    }

    // --------------------------------------------------------------------
// Estado: aguardando nome da revenda
// --------------------------------------------------------------------
if ($thread->state === 'creating_revenda_nome') {

    // se não mandou texto, pede de novo
    if (trim($text) === '') {
        $this->sendText($phone, "Por favor, me envie o *nome* do novo empreendimento que você quer criar.");
        return response()->json(['ok' => true, 'handled' => 'criar_empreendimento_nome_vazio']);
    }

    $nome = trim($text);
    $corretorId = (int) $thread->corretor_id;
    $companyId  = $thread->company_id ?? null; // se você tiver esse campo no thread

    // cria o empreendimento de revenda
    $emp = new Empreendimento();
    $emp->nome             = $nome;
    $emp->is_revenda       = 1;
    $emp->dono_corretor_id = $corretorId;

    // campos opcionais, se existirem na sua tabela:
    if (property_exists($emp, 'company_id') || \Schema::hasColumn($emp->getTable(), 'company_id')) {
        $emp->company_id = $companyId;
    }
    if (\Schema::hasColumn($emp->getTable(), 'status')) {
        $emp->status = 'rascunho';
    }

    $emp->save();

    // vincula esse empreendimento ao thread
    $thread->selected_empreendimento_id = $emp->id;
    $thread->state = 'idle'; // volta pro fluxo normal
    $thread->save();

    $this->sendText(
        $phone,
        "✅ Empreendimento de revenda criado com sucesso!\n\n" .
        "🏢 *{$emp->nome}*\n" .
        "ID interno: {$emp->id}\n\n" .
        "Agora você pode enviar *fotos e vídeos* desse empreendimento aqui mesmo, " .
        "que eu vou salvar tudo na sua galeria exclusiva dele. 😉"
    );

    return response()->json(['ok' => true, 'handled' => 'criar_empreendimento_nome_ok']);
}


    /**
     * 🧮 SUPER-GATE: perguntas de pagamento / tabela (Excel)
     * Se a pergunta for algo como:
     * "quais as informações de pagamento da unidade 301, torre 5?"
     * tentamos responder APENAS pela planilha Excel do empreendimento.
     */
    if ($this->looksLikePaymentQuestion($question)) {
        try {
            $answerFromExcel = $this->answerFromExcelPayment($thread, $question);

            if ($answerFromExcel !== null) {
                // registra no histórico
                $latencyMs  = 0;
                $latencySec = 0;

                $this->storeMessage($thread, [
                    'sender'          => 'ia',
                    'type'            => 'text',
                    'body'            => $answerFromExcel,
                    'latency_ms'      => $latencyMs,
                    'latency_seconds' => $latencySec,
                    'meta'            => [
                        'emp_id' => $empId,
                        'source' => 'excel_pagamento',
                    ],
                ]);

                // opcional: salvar na “memória local” também
                if ($empId > 0) {
                    $this->storeLocalAnswer($empId, $question, $answerFromExcel, 'excel_pagamento');
                }

                return $this->sendText($phone, $answerFromExcel . $this->footerControls());
            }

        } catch (\Throwable $ex) {
            \Log::warning('Pagamento Excel: falha ao responder por planilha', [
                'empId' => $empId,
                'err'   => $ex->getMessage(),
            ]);
            // se der erro, fluxo continua normalmente na IA
        }
    }

    // 🧠 NOVO: tentar responder pela MEMÓRIA LOCAL (FAQ por empreendimento) antes de ir pra IA
    if ($empId > 0) {
        if ($localAnswer = $this->lookupLocalAnswer($empId, $question)) {
            Log::info('WPP IA-LOCAL: resposta encontrada em whatsapp_qa_cache', [
                'phone' => $phone,
                'empId' => $empId,
            ]);

            return $this->sendText($phone, $localAnswer . $this->footerControls());
        }
    }

    // 🔥 CACHE DE RESPOSTA POR EMPREENDIMENTO + PERGUNTA (cache volátil)
    $normalizedQuestion = mb_strtolower(preg_replace('/\s+/u', ' ', $question));
    $answerCacheKey = "wpp:answer:{$empId}:" . sha1($normalizedQuestion);

    if (Cache::has($answerCacheKey)) {
        $cachedAnswer = Cache::get($answerCacheKey);
        return $this->sendText($phone, $cachedAnswer . $this->footerControls());
    }

    // marca início para medir tempo da IA
    $t0 = microtime(true);

    try {
        // ================== NOVO: tenta responder usando texto_ia ==================
        $e = Empreendimento::find($empId);

        if ($e) {
            $answerFromTexto = $this->answerFromTextoIa($e, $question);

            if (!empty($answerFromTexto)) {
                // calcula latência
                $latencyMs  = (int) round((microtime(true) - $t0) * 1000);
                $latencySec = round($latencyMs / 1000, 3);

                // registra mensagem da IA
                $this->storeMessage($thread, [
                    'sender'          => 'ia',
                    'type'            => 'text',
                    'body'            => $answerFromTexto,
                    'latency_ms'      => $latencyMs,
                    'latency_seconds' => $latencySec,
                    'meta'            => [
                        'emp_id'     => $empId,
                        'source'     => 'texto_ia',
                    ],
                ]);

                // grava na memória local
                if ($empId > 0) {
                    $this->storeLocalAnswer($empId, $question, $answerFromTexto, 'texto_ia');
                }

                return $this->sendText($phone, $answerFromTexto . $this->footerControls());
            }
        }
        // ================== FIM NOVO: se não respondeu, continua pro Vector Store ==================

        /** @var VectorStoreService $svc */
        $svc  = app(VectorStoreService::class);

        $vsId   = $svc->ensureVectorStoreForEmpreendimento($empId);
        $asstId = $svc->ensureAssistantForEmpreendimento($empId, $vsId);

        $threadKey = "wpp:thread:{$phone}:{$empId}";
        $client = OpenAI::client(config('services.openai.key'));

        $assistantThreadId = Cache::get($threadKey);
        if (!$assistantThreadId) {
            $th = $client->threads()->create();
            $assistantThreadId = $th->id;
            Cache::put($threadKey, $assistantThreadId, now()->addDays(7));
        }

        $client->threads()->messages()->create($assistantThreadId, [
            'role'    => 'user',
            'content' => $question,
        ]);

        $run = $client->threads()->runs()->create($assistantThreadId, [
            'assistant_id' => $asstId,
        ]);

        $tries = 0;
        $maxTries = 40;
        $startWait = microtime(true);

        do {
            // backoff simples: começa em 200ms, depois de algumas tentativas sobe pra 500ms
            $delayMs = $tries < 10 ? 200000 : 500000;
            usleep($delayMs);

            $run = $client->threads()->runs()->retrieve($assistantThreadId, $run->id);
            $tries++;

            // 🔧 aumenta o tempo máximo de espera da IA (ex: 60s)
$maxWaitSeconds = 60;

if ((microtime(true) - $startWait) > $maxWaitSeconds) {
    break;
}

        } while (in_array($run->status, ['queued','in_progress','cancelling']) && $tries < $maxTries);

        if ($run->status !== 'completed') {
            Log::warning('IA não completou', [
                'status' => $run->status,
                'empId'  => $empId,
                'phone'  => $phone,
                'tries'  => $tries,
                'wait_s' => round(microtime(true) - $startWait, 3),
            ]);
            return $this->sendText(
                $phone,
                "Não consegui concluir sua resposta agora. Pode tentar novamente em instantes?" .
                $this->footerControls()
            );
        }

        $msgs = $client->threads()->messages()->list($assistantThreadId, ['limit' => 10]);
        $answerText = '';
        foreach ($msgs->data as $m) {
            if ($m->role === 'assistant') {
                foreach ($m->content as $c) {
                    if ($c->type === 'text') {
                        $answerText = $c->text->value ?? '';
                        if ($answerText !== '') break 2;
                    }
                }
            }
        }

        if ($answerText === '') {
            $answerText = "Não encontrei essa informação nos documentos. Pode reformular sua pergunta?";
        }

        // LIMPEZA DE CITAÇÕES E AJUSTE DE PONTUAÇÃO
        $answerText = preg_replace('/【\d+:\d+†source】/u', '', $answerText);
        $answerText = preg_replace('/\s+([.,;:!?])/u', '$1', $answerText);
        $answerText = preg_replace("/[ \t]+/u", ' ', $answerText);
        $answerText = preg_replace("/\n{3,}/u", "\n\n", $answerText);
        $answerText = trim($answerText);

        // 💾 Salva no cache volátil para perguntas repetidas
        Cache::put($answerCacheKey, $answerText, now()->addMinutes(30));

        // calcula latência total (já contando passo anterior)
        $latencyMs  = (int) round((microtime(true) - $t0) * 1000);
        $latencySec = round($latencyMs / 1000, 3);

        // registra a resposta da IA com latência
        $this->storeMessage($thread, [
            'sender'          => 'ia',
            'type'            => 'text',
            'body'            => $answerText,
            'latency_ms'      => $latencyMs,
            'latency_seconds' => $latencySec,
            'meta'            => [
                'emp_id'     => $empId,
                'thread_key' => $threadKey,
                'source'     => 'vector_store',
            ],
        ]);

        // grava também na memória local (FAQ)
        if ($empId > 0) {
            $this->storeLocalAnswer($empId, $question, $answerText, 'vector_store');
        }

        return $this->sendText($phone, $answerText . $this->footerControls());

    } catch (\Throwable $e) {
        Log::error('Erro na IA', [
            'error' => $e->getMessage(),
            'phone' => $phone,
            'empId' => $empId
        ]);

        return $this->sendText(
            $phone,
            "Tive um problema ao consultar os arquivos desse empreendimento." .
            $this->footerControls()
        );
    }
}

/**
 * Tenta extrair o "grupo genérico" (torre/bloco/quadra/etc.) do texto.
 *
 * Exemplos:
 *  - "unidade 102 torre 5"    → "Torre 5"
 *  - "casa 10 quadra b"       → "Quadra B"
 *  - "lote 12 quadra 3"       → "Quadra 3"
 */
protected function parseGenericGroup(string $msgNorm): ?string
{
    $msgNorm = mb_strtolower(trim($msgNorm));

    // procura por termos de grupo + valor
    if (preg_match(
        '/\b(torre|bloco|quadra|ala|alameda|casa|predio|prédio|condominio|condomínio|edificio|edifício)\s+([0-9a-z\.]+)/u',
        $msgNorm,
        $m
    )) {
        $label = $m[1];          // torre / bloco / quadra...
        $valor = strtoupper($m[2]); // 5, b, 3...

        // normalização bonitinha de alguns termos
        $map = [
            'predio'      => 'Prédio',
            'prédio'      => 'Prédio',
            'condominio'  => 'Condomínio',
            'condomínio'  => 'Condomínio',
            'edificio'    => 'Edifício',
            'edifício'    => 'Edifício',
        ];

        $labelFormatado = $map[$label] ?? ucfirst($label);

        return $labelFormatado . ' ' . $valor;
    }

    return null;
}



protected function parseGrupoFromText(int $empId, string $text): ?string
{
    $textNorm = mb_strtolower($text);

    // pega todos os grupos distintos desse empreendimento
    $grupos = EmpreendimentoUnidade::where('empreendimento_id', $empId)
        ->whereNotNull('grupo_unidade')
        ->pluck('grupo_unidade')
        ->unique()
        ->toArray();

    if (empty($grupos)) {
        return null;
    }

    // ordena pelo maior nome primeiro (evita "ala" pegando dentro de "alameda azul")
    usort($grupos, function ($a, $b) {
        return mb_strlen($b) <=> mb_strlen($a);
    });

    foreach ($grupos as $g) {
        $gNorm = mb_strtolower($g);

        // cria regex permissiva, aceita variações:
        // "da torre 1", "torre 1", "na torre 1", "bloco a", etc.
        $pattern = '/\b(' . preg_quote($gNorm, '/') . ')\b/u';

        if (preg_match($pattern, $textNorm)) {
            return $g; // retorna o nome EXATO do banco
        }
    }

    return null;
}


    /**
 * Detecta se a mensagem parece um pedido de PROPOSTA em PDF.
 * Ex.: "me manda a proposta da unidade 204 torre 5", "pdf de proposta 302 t5", etc.
 */
protected function looksLikeProposalRequest(string $question): bool
{
    $norm = $this->normalizeText($question);

    $hasProposalWord = Str::contains($norm, [
        'proposta',
        'proposta pdf',
        'pdf da proposta',
        'simulacao',
        'simulação',
        'simulacao de financiamento',
    ]);

    $hasUnitWord = Str::contains($norm, [
        'unidade',
        'apto',
        'apartamento',
        'apt ',
        'ap.',
    ]);

    // Ex.: "me manda a proposta da unidade 204 torre 5"
    return $hasProposalWord && $hasUnitWord;
}


    /**
     * Tenta responder usando apenas o campo texto_ia do empreendimento.
     * Se não conseguir responder com segurança, retorna null para cair no Vector Store.
     */
    protected function answerFromTextoIa(Empreendimento $e, string $question): ?string
    {
        $context = trim((string) $e->texto_ia);
        
        // Monta informações básicas do banco de dados
        $dadosBasicos = [];
        if (!empty($e->nome)) $dadosBasicos[] = "Nome: {$e->nome}";
        if (!empty($e->endereco)) $dadosBasicos[] = "Endereço: {$e->endereco}";
        if (!empty($e->cidade)) $dadosBasicos[] = "Cidade: {$e->cidade}";
        if (!empty($e->uf)) $dadosBasicos[] = "UF: {$e->uf}";
        if (!empty($e->cep)) $dadosBasicos[] = "CEP: {$e->cep}";
        if (!empty($e->tipologia)) $dadosBasicos[] = "Tipologia: {$e->tipologia}";
        if (!empty($e->metragem)) $dadosBasicos[] = "Metragem: {$e->metragem}";
        if (!empty($e->preco_base)) $dadosBasicos[] = "Preço base: R$ " . number_format($e->preco_base, 2, ',', '.');
        if (!empty($e->descricao)) $dadosBasicos[] = "Descrição: {$e->descricao}";
        
        $dadosBasicosStr = !empty($dadosBasicos) ? implode("\n", $dadosBasicos) : '';

        // Se não tem texto_ia E não tem dados básicos, deixa cair pro Vector Store
        if ($context === '' && $dadosBasicosStr === '') {
            return null;
        }

        try {
            $client = OpenAI::client(config('services.openai.key'));

            $prompt = <<<PROMPT
Você é um assistente para corretores de imóveis.

Use APENAS as informações abaixo sobre o empreendimento para responder.
Se a resposta não estiver claramente nessas informações, responda exatamente: "NAO_SEI".

### INFORMAÇÕES BÁSICAS DO EMPREENDIMENTO (do banco de dados)
{$dadosBasicosStr}

### INFORMAÇÕES ADICIONAIS DO EMPREENDIMENTO (texto_ia)
{$context}

### PERGUNTA DO CORRETOR
$question
PROMPT;

            $res = $client->chat()->create([
                'model' => 'gpt-5.1',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $answer = trim($res->choices[0]->message->content ?? '');

            if ($answer === '' || strtoupper($answer) === 'NAO_SEI') {
                return null; // sinal pra cair no Vector Store
            }

            // pequena limpeza opcional
            $answer = preg_replace("/\n{3,}/u", "\n\n", $answer);
            $answer = trim($answer);

            return $answer;

        } catch (\Throwable $ex) {
            \Log::warning('Erro em answerFromTextoIa', [
                'empId' => $e->id,
                'error' => $ex->getMessage(),
            ]);
            return null; // em caso de erro, também deixa o fluxo seguir pro Vector Store
        }
    }

    /**
     * Registra uma mensagem na tabela whatsapp_messages.
     *
     * @param  WhatsappThread  $thread
     * @param  array           $attrs ['sender','type','body','meta','latency_ms','latency_seconds']
     * @return WhatsappMessage
     */
    protected function storeMessage(WhatsappThread $thread, array $attrs): WhatsappMessage
    {
        $msg = new WhatsappMessage();

        $msg->thread_id   = $thread->id;
        $msg->company_id  = $thread->company_id ?? null;
        $msg->corretor_id = $thread->corretor_id ?? null;
        $msg->phone       = $thread->phone;

        // 'user' | 'ia' | 'system'
        $msg->sender = $attrs['sender'] ?? 'user';
        $msg->type   = $attrs['type']   ?? 'text';
        $msg->body   = $attrs['body']   ?? null;

        // latência se vier
        if (isset($attrs['latency_ms'])) {
            $msg->latency_ms = $attrs['latency_ms'];
        }
        if (isset($attrs['latency_seconds'])) {
            $msg->latency_seconds = $attrs['latency_seconds'];
        }

        if (!empty($attrs['meta'])) {
            $msg->meta = is_string($attrs['meta'])
                ? $attrs['meta']
                : json_encode($attrs['meta'], JSON_UNESCAPED_UNICODE);
        }

        $msg->save();

        return $msg;
    }

    // ===== Helpers de ARQUIVOS (S3) =====
function cacheAndBuildFilesList(string $filesKey, int $empId, ?int $companyId): string
{
    if (!$companyId) {
        Cache::put($filesKey, [], now()->addMinutes(10));
        Log::warning('WPP arquivos: companyId NULL - não dá para montar prefixo', [
            'empId' => $empId,
            'filesKey' => $filesKey,
        ]);
        return "Não encontrei arquivos para este empreendimento.";
    }

    $prefix = "documentos/tenants/{$companyId}/empreendimentos/{$empId}/";
    Log::info('WPP arquivos: listando S3', [
        'prefix'    => $prefix,
        'empId'     => $empId,
        'companyId' => $companyId,
    ]);

    $disk  = Storage::disk('s3');
    $files = $disk->files($prefix);

    Log::info('WPP arquivos: arquivos brutos do S3', [
        'prefix' => $prefix,
        'count'  => count($files),
        'files'  => $files,
    ]);

    // separa extensões
    $allowedDocs   = ['pdf','doc','docx','xls','xlsx','ppt','pptx','csv','txt'];
    $allowedImages = ['jpg','jpeg','png','gif','webp'];
    $allowedVideos = ['mp4','mov','avi','mkv'];

    $allowed = array_merge($allowedDocs, $allowedImages, $allowedVideos);

    // filtra só extensões permitidas (normalizando para lower)
    $files = array_values(array_filter(
        $files,
        function ($p) use ($allowed) {
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            return in_array($ext, $allowed, true);
        }
    ));

    Log::info('WPP arquivos: após filtro de extensões permitidas', [
        'prefix' => $prefix,
        'count'  => count($files),
        'files'  => $files,
    ]);

    if (empty($files)) {
        Cache::put($filesKey, [], now()->addMinutes(15));
        Log::info('WPP arquivos: NENHUM arquivo encontrado no S3', [
            'prefix' => $prefix,
        ]);
        return "Não encontrei arquivos para este empreendimento.";
    }

    // separar documentos x fotos
    $docFiles   = [];
    $photoFiles = [];

    foreach ($files as $path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedImages, true)) {
            $photoFiles[] = $path;
        } else {
            $docFiles[] = $path;
        }
    }

    Log::info('WPP arquivos: split docs/fotos', [
        'prefix'      => $prefix,
        'qtde_docs'   => count($docFiles),
        'qtde_fotos'  => count($photoFiles),
        'docFiles'    => $docFiles,
        'photoFiles'  => $photoFiles,
    ]);

    usort($docFiles, fn($a, $b) => strcasecmp(basename($a), basename($b)));

    $items = [];
    $lines = [];
    $i     = 1;

    // 1) Documentos normais
    foreach ($docFiles as $path) {
        $name = basename($path);
        $mime = $this->guessMimeByExt($name);

        $items[] = [
            'index' => $i,
            'name'  => $name,
            'path'  => $path,
            'mime'  => $mime,
            'type'  => 'file',
        ];

        $lines[] = "{$i}. {$name}";

        Log::info('WPP arquivos: item documento adicionado', [
            'index' => $i,
            'name'  => $name,
            'path'  => $path,
            'mime'  => $mime,
            'type'  => 'file',
        ]);

        $i++;
    }

    // 2) Se tiver fotos, adiciona UMA opção de bundle
    if (!empty($photoFiles)) {
        $items[] = [
            'index'  => $i,
            'name'   => 'Fotos do empreendimento',
            'path'   => null,
            'mime'   => null,
            'type'   => 'photos_bundle',
            'photos' => $photoFiles,
        ];

        $lines[] = "{$i}. 📷 Fotos do empreendimento";

        Log::info('WPP arquivos: item bundle de fotos adicionado', [
            'index'       => $i,
            'name'        => 'Fotos do empreendimento',
            'type'        => 'photos_bundle',
            'qtde_photos' => count($photoFiles),
            'photos'      => $photoFiles,
        ]);
    }

    Cache::put($filesKey, $items, now()->addMinutes(30));

    Log::info('WPP arquivos: lista final para cache', [
        'filesKey' => $filesKey,
        'qtde'     => count($items),
        'items'    => $items,
    ]);

    return "Arquivos disponíveis:\n\n" . implode("\n", $lines);
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

    private function fileListKey(string $phone, int $empId): string
    {
        return "wpp:filelist:{$phone}:{$empId}";
    }

    /**
     * Modo CATÁLOGO:
     * Tenta responder usando texto_ia de TODOS os empreendimentos da empresa do corretor.
     * Retorna ['answer' => string, 'map' => [idx => empId]] ou null.
     */
    protected function maybeHandleCatalogQuestion(WhatsappThread $thread, string $question): ?array
    {
        $norm = $this->normalizeText($question);

        // se não parece pergunta de catálogo, ignora
        if (!$this->looksLikeCatalogIntent($norm)) {
            return null;
        }

        // precisa descobrir a empresa (company_id)
        $companyId = $this->resolveCompanyIdForThread($thread);
        if (!$companyId) {
            \Log::info('CATALOGO: sem company_id na thread, ignorando modo catálogo', [
                'thread_id' => $thread->id,
                'phone'     => $thread->phone,
            ]);
            return null;
        }

        // busca empreendimentos dessa empresa com texto_ia preenchido
        $q = Empreendimento::query()
            ->where('company_id', $companyId)
            ->whereNotNull('texto_ia')
            ->where('texto_ia', '!=', '');

        // respeita coluna "ativo" se existir
        if (Schema::hasColumn('empreendimentos', 'ativo')) {
            $q->where(function ($qq) {
                $qq->where('ativo', 1)->orWhere('ativo', true);
            });
        }

        // limita para não explodir o prompt
        $emps = $q->orderBy('nome')
            ->limit(25)
            ->get(['id', 'nome', 'cidade', 'uf', 'texto_ia']);

        if ($emps->isEmpty()) {
            \Log::info('CATALOGO: nenhum empreendimento com texto_ia para company', [
                'company_id' => $companyId,
            ]);
            return null;
        }

        // Escolhe no máximo 5 para catálogo atual
        $maxItems = 5;
        $selected = $emps->take($maxItems);

        // monta bloco de contexto numerado
        $blocks = [];
        $map = [];
        $idx = 1;
        foreach ($selected as $e) {
            $ctx = trim(preg_replace('/\s+/', ' ', (string) $e->texto_ia));
            if (mb_strlen($ctx) > 450) {
                $ctx = mb_substr($ctx, 0, 450) . '…';
            }

            $cidadeUf = trim(
                ($e->cidade ?? '') .
                ((($e->cidade ?? '') && ($e->uf ?? '')) ? '/' : '') .
                ($e->uf ?? '')
            );

            $map[(string)$idx] = (int) $e->id;

            $blocks[] = "($idx) Empreendimento ID: {$e->id}\n"
                . "Nome: {$e->nome}\n"
                . ($cidadeUf ? "Cidade/UF: {$cidadeUf}\n" : "")
                . "Resumo:\n{$ctx}\n";

            $idx++;
        }

        $contextBlock = implode("\n-------------------------\n", $blocks);

        $prompt = <<<PROMPT
Você é uma IA que ajuda corretores a escolher quais empreendimentos oferecer para um cliente.

Você recebeu:
1. Uma LISTA de empreendimentos com algumas informações de contexto (abaixo), já numerados (1), (2), (3)...
2. Uma PERGUNTA do corretor sobre o perfil do cliente.

TAREFA:
- Entenda o perfil do cliente (valor de entrada, orçamento total, cidade, tipo de imóvel etc.).
- Avalie quais empreendimentos combinam melhor com esse perfil, usando APENAS as informações fornecidas em "Resumo".
- Responda em português do Brasil, em tom direto para WhatsApp.
- Use SEMPRE os mesmos números já definidos (1), (2), (3) etc. Não crie novos números.
- Liste no máximo 5 empreendimentos.
- Para cada item, mostre:
  - Número (por exemplo: 1)
  - Nome do empreendimento
  - Cidade/UF (se tiver)
  - Um motivo curto de encaixe (ex.: aceita entrada semelhante, faixa de preço compatível, perfil econômico, etc.).
- Se nenhum empreendimento parecer adequado, diga isso claramente e, se fizer sentido, sugira ajustar o valor de entrada ou outros critérios.
- Não invente dados que não estejam nos resumos.

LISTA DE EMPREENDIMENTOS:
$contextBlock

PERGUNTA DO CORRETOR:
$question
PROMPT;

        try {
            $client = OpenAI::client(config('services.openai.key'));

            $res = $client->chat()->create([
                'model'    => 'gpt-4.1-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $answer = trim($res->choices[0]->message->content ?? '');

            if ($answer === '') {
                return null;
            }

            // limpeza básica
            $answer = preg_replace("/\n{3,}/u", "\n\n", $answer);
            $answer = trim($answer);

            return [
                'answer' => $answer,
                'map'    => $map,
            ];
        } catch (\Throwable $e) {
            \Log::error('Erro em maybeHandleCatalogQuestion', [
                'error'     => $e->getMessage(),
                'thread_id' => $thread->id,
            ]);
            return null;
        }
    }

    /**
     * Detecta se a mensagem parece intenção de catálogo (perfil de cliente / entrada / empreendimentos).
     */
    protected function looksLikeCatalogIntent(string $norm): bool
    {
        // já vem normalizado (minúsculo, sem acento etc.)
        $hasMoneyWords = Str::contains($norm, [
            'entrada', 'sinal', 'parcel', 'financiamento', 'financiam',
            'a vista', 'á vista', 'avista', 'tem so', 'tem so ', 'so tem', 'só tem'
        ]);

        $hasClientWords = Str::contains($norm, [
            'cliente', 'meu cliente', 'minha cliente', 'perfil', 'orcamento', 'orçamento'
        ]);

        $hasAllWords = Str::contains($norm, [
            'quais empreendimentos', 'qual empreendimento',
            'algum empreendimento', 'todos empreendimentos', 'todos os empreendimentos'
        ]);

        // bem simples: ou fala de cliente + dinheiro, ou pergunta diretamente por "empreendimentos"
        return ($hasMoneyWords && $hasClientWords) || $hasAllWords;
    }

    /**
     * Envia mídia via Make (preferência) e, se não entregar, faz fallback direto na Z-API.
     */
   private function sendMediaSmart(
    string $phone,
    string $pathOrUrl,
    ?string $caption = null,
    ?string $mime = null,
    ?string $disk = null,
    ?int $companyId = null,
    ?int $empId = null
): array {

    try {
        Log::info('sendMediaSmart: start', [
            'phone'     => $phone,
            'pathOrUrl' => $pathOrUrl,
            'caption'   => $caption,
            'mime_in'   => $mime,
            'disk'      => $disk,
        ]);

        // 1) Resolve URL pública, mesmo que esteja no disco public
        $publicUrl = $this->resolvePublicUrlForMediaWithFilename($pathOrUrl, $disk);

        // Extrai fileName da URL/caminho
        $pathPart = parse_url($pathOrUrl, PHP_URL_PATH) ?: $pathOrUrl;
        $rawFileName = basename($pathPart);

        // Normaliza EXTENSÃO → sempre minúscula (resolve casos .PNG, .JPG, etc.)
        $ext  = pathinfo($rawFileName, PATHINFO_EXTENSION);
        $base = pathinfo($rawFileName, PATHINFO_FILENAME);

        if ($ext) {
            $fileName = $base . '.' . strtolower($ext);
        } else {
            // sem extensão explícita
            $fileName = $rawFileName;
        }

        Log::info('sendMediaSmart: filename normalizado', [
            'rawFileName' => $rawFileName,
            'fileName'    => $fileName,
        ]);

        // MIME
        if (!$mime || $mime === 'application/octet-stream') {
            $mime = $this->guessMimeByExt($fileName);
        }

        $caption = $this->normalizeCaptionForWhats($caption);

        Log::info('sendMediaSmart: após resolução de URL e MIME', [
            'phone'    => $phone,
            'publicUrl'=> $publicUrl,
            'fileName' => $fileName,
            'mime'     => $mime,
            'caption'  => $caption,
        ]);

        // 2) Tenta Make primeiro (se configurado)
        $hook = env('MAKE_WEBHOOK_URL');
        if ($hook) {
            $payload = [
    'phone'    => preg_replace('/\D+/', '', $phone),
    'url'      => $publicUrl,
    'fileUrl'  => $publicUrl,
    'mime'     => $mime,
    'fileName' => $fileName,
    'caption'  => $caption,
];

if ($companyId) $payload['company_id'] = $companyId;
if ($empId)     $payload['empreendimento_id'] = $empId;


            Log::info('sendMediaSmart → Make: enviando payload', [
                'to'      => $phone,
                'hook'    => $hook,
                'payload' => $payload,
            ]);

            $resp = Http::timeout(20)->post($hook, $payload);
            Log::info('sendMediaSmart → Make: resposta recebida', [
                'to'     => $phone,
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);

            if ($resp->successful()) {
                $j = $resp->json();
                $ok = false;

                if (is_array($j)) {
                    $ok = ($j['ok'] ?? false)
                        || isset($j['messageId'])
                        || isset($j['id'])
                        || (isset($j['result']) && $j['result'] === 'sent');
                }

                Log::info('sendMediaSmart → Make: parsed JSON', [
                    'to' => $phone,
                    'ok' => $ok,
                    'j'  => $j,
                ]);

                if ($ok) {
                    return [
                        'ok'      => true,
                        'via'     => 'make',
                        'url'     => $publicUrl,
                        'mime'    => $mime,
                        'caption' => $caption,
                        'resp'    => $j,
                    ];
                }

                Log::warning('sendMediaSmart: Make 200 mas sem confirmação → fallback Z-API');
            } else {
                Log::warning('sendMediaSmart: Make falhou → fallback Z-API', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
            }
        } else {
            Log::info('sendMediaSmart: MAKE_WEBHOOK_URL ausente → Z-API direto');
        }

        // 3) Fallback Z-API via URL (send-document/pdf etc)
        Log::info('sendMediaSmart → Z-API: chamando sendViaZapiMedia', [
            'phone'    => $phone,
            'publicUrl'=> $publicUrl,
            'fileName' => $fileName,
            'mime'     => $mime,
            'caption'  => $caption,
        ]);

        $z = $this->sendViaZapiMedia($phone, $publicUrl, $fileName, $mime, $caption);

        Log::info('sendMediaSmart → Z-API: resposta', [
            'to'   => $phone,
            'resp' => $z,
        ]);

        if (!empty($z['ok'])) {
            return $z + [
                'via'      => 'z-api',
                'url'      => $publicUrl,
                'mime'     => $mime,
                'fileName' => $fileName,
            ];
        }

        return [
            'ok'      => false,
            'error'   => $z['error'] ?? 'zapi_media_failed',
            'via'     => 'z-api',
            'url'     => $publicUrl,
            'mime'    => $mime,
            'fileName'=> $fileName,
        ];
    } catch (\Throwable $e) {
        Log::error('sendMediaSmart exception', [
            'msg'   => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}


    /**
     * Fallback direto na Z-API, escolhendo endpoint conforme MIME/extensão.
     * Para tua instância:
     * - Imagem  → send-image  (campo "image" com a URL)
     * - Vídeo   → send-video  (campo "video" com a URL)
     * - Documentos/PDF → send-document/pdf (campo "document" com a URL)
     */
    private function sendViaZapiMedia(string $phone, string $fileUrl, string $fileName, string $mime, ?string $caption = null): array
    {
        try {
            $instanceId  = env('ZAPI_INSTANCE_ID', '');
            $pathToken   = env('ZAPI_PATH_TOKEN', '');
            $clientToken = env('ZAPI_CLIENT_TOKEN', '');
            $base        = "https://api.z-api.io/instances/{$instanceId}/token/{$pathToken}";

            $type = $this->classifyMediaType($mime, $fileName);
            $ext  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Decide quais endpoints tentar, já com o NOME CORRETO DO CAMPO
            if ($type === 'image') {
                // Imagens → send-image, campo "image"
                $tries = [
                    ['endpoint' => 'send-image', 'payloadKey' => 'image'],
                ];
            } elseif ($type === 'video') {
                // Vídeos → send-video, campo "video"
                $tries = [
                    ['endpoint' => 'send-video', 'payloadKey' => 'video'],
                ];
            } else {
                // Documentos → usa send-document/pdf, campo "document"
                $tries = [
                    ['endpoint' => 'send-document/pdf', 'payloadKey' => 'document'],
                ];
            }

            // HEAD só para log / debug (não bloqueia o envio)
            try {
                $head = Http::timeout(10)->head($fileUrl);
                \Log::info('MEDIA HEAD', [
                    'ct'     => $head->header('Content-Type'),
                    'cl'     => $head->header('Content-Length'),
                    'status' => $head->status(),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('MEDIA HEAD exception', [
                    'e'   => $e->getMessage(),
                    'url' => $fileUrl,
                ]);
            }

            $payloadBase = [
                'phone'    => preg_replace('/\D+/', '', $phone),
                'caption'  => $caption,
                'fileName' => $fileName,
            ];

            foreach ($tries as $t) {
                $payload = $payloadBase + [
                    $t['payloadKey'] => $fileUrl,
                ];

                $resp = Http::asJson()
                    ->timeout(20)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                        'Client-Token' => $clientToken,
                    ])
                    ->post("{$base}/{$t['endpoint']}", $payload);

                \Log::info('Z-API media try', [
                    'endpoint' => $t['endpoint'],
                    'status'   => $resp->status(),
                    'body'     => $resp->body(),
                ]);

                if ($resp->successful()) {
                    $j  = $resp->json();
                    $ok = is_array($j) && (
                        ($j['ok'] ?? false) ||
                        isset($j['messageId']) ||
                        isset($j['id']) ||
                        (isset($j['status']) && in_array($j['status'], [200, 'SENT', 'OK', 'sent'], true))
                    );

                    if ($ok) {
                        return [
                            'ok'         => true,
                            'endpoint'   => $t['endpoint'],
                            'message_id' => $j['messageId'] ?? ($j['id'] ?? null),
                            'resp'       => $j,
                        ];
                    }
                }
            }

            return [
                'ok'    => false,
                'error' => 'no_zapi_endpoint_succeeded',
            ];
        } catch (\Throwable $e) {
            \Log::error('sendViaZapiMedia exception', ['e' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Classifica o tipo de mídia para escolher endpoint. */
    private function classifyMediaType(string $mime, string $fileName): string
    {
        $m = strtolower($mime);
        if (str_starts_with($m, 'image/')) return 'image';
        if (str_starts_with($m, 'video/')) return 'video';
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' :
               (in_array($ext, ['mp4','mov','avi','mkv','webm']) ? 'video' : 'document');
    }

    /**
     * Resolve URL pública para o arquivo.
     * Tenta temporaryUrl (presign) quando possível, incluindo Content-Disposition p/ nome do arquivo.
     * Controlado por env WPP_MEDIA_USE_TEMP_URL=true|false (default: true).
     */
    private function resolvePublicUrlForMediaWithFilename(string $pathOrUrl, ?string $disk = null): string
{
    // Se já for uma URL completa, retorna como está
    if (preg_match('#^https?://#i', $pathOrUrl)) {
        return $pathOrUrl;
    }

    // Ordem de discos candidatos
    $candidates = array_values(array_filter([$disk, 's3', 'public']));

    foreach ($candidates as $d) {
        try {
            $storage = Storage::disk($d);

            // SE O DISCO SUPORTA temporaryUrl → usa (URL assinada)
            if (method_exists($storage, 'temporaryUrl')) {

                $url = $storage->temporaryUrl(
                    $pathOrUrl,
                    now()->addMinutes(10), // validade curta
                    [
                        'ResponseContentDisposition' => 'inline; filename="'.basename($pathOrUrl).'"',
                    ]
                );

                \Log::info('resolvePublicUrlForMediaWithFilename: usando temporaryUrl()', [
                    'disk' => $d,
                    'path' => $pathOrUrl,
                    'url'  => $url,
                ]);

                return $url;
            }

            // Se não suporta, usa url() normal
            if (method_exists($storage, 'url')) {
                $url = $storage->url($pathOrUrl);

                \Log::info('resolvePublicUrlForMediaWithFilename: usando url() do disco', [
                    'disk' => $d,
                    'path' => $pathOrUrl,
                    'url'  => $url,
                ]);

                return $url;
            }

            // fallback para public/
            if ($d === 'public') {
                $url = url('/storage/' . $pathOrUrl);

                \Log::info('resolvePublicUrlForMediaWithFilename: usando /storage', [
                    'disk' => $d,
                    'path' => $pathOrUrl,
                    'url'  => $url,
                ]);

                return $url;
            }

        } catch (\Throwable $e) {
            \Log::warning('resolvePublicUrlForMediaWithFilename: falha no disco', [
                'disk' => $d,
                'path' => $pathOrUrl,
                'e'    => $e->getMessage(),
            ]);
        }
    }

    // Último fallback
    \Log::warning('resolvePublicUrlForMediaWithFilename: retornando path bruto', [
        'path' => $pathOrUrl,
    ]);

    return $pathOrUrl;
}


    /** Normaliza a legenda pra WhatsApp/Z-API (ou retorna null se vazia). */
    private function normalizeCaptionForWhats(?string $caption, int $limit = 900): ?string
    {
        if ($caption === null) return null;

        // remove extensão tipo ".pdf", ".png", etc. se vier como nome de arquivo
        $caption = trim($caption);
        $caption = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $caption) ?? $caption;

        // comprime espaços e quebra de linha excessiva
        $caption = preg_replace('/[ \t]+/u', ' ', $caption) ?? $caption;
        $caption = preg_replace('/\n{2,}/u', "\n", $caption) ?? $caption;
        $caption = trim($caption);

        // se ficou vazia, não manda legenda
        if ($caption === '') return null;

        // limite de segurança (evita truncamento pelo provider)
        if (mb_strlen($caption) > $limit) {
            $caption = mb_substr($caption, 0, $limit - 1) . '…';
        }

        return $caption;
    }

    /** Baixa o arquivo (S3 ou URL) e devolve [base64, mime, fileName, size] */
    private function fetchAsBase64(string $pathOrUrl, ?string $disk = null): array
    {
        try {
            // Se já for URL http(s):
            if (preg_match('#^https?://#i', $pathOrUrl)) {
                $resp = Http::timeout(30)->withOptions(['stream' => true])->get($pathOrUrl);
                if (!$resp->successful()) {
                    throw new \RuntimeException('Download HTTP falhou: '.$resp->status());
                }
                $tmp = tmpfile();
                $meta = stream_get_meta_data($tmp);
                $fp = fopen($meta['uri'], 'w+b');
                foreach ($resp->body() as $chunk) { fwrite($fp, $chunk); }
                $size = filesize($meta['uri']);
                $raw  = file_get_contents($meta['uri']);
                fclose($fp);
                $mime = $resp->header('Content-Type') ?: 'application/octet-stream';
                $name = basename(parse_url($pathOrUrl, PHP_URL_PATH) ?: ('file_'.time()));
                return ['b64'=>base64_encode($raw), 'mime'=>$mime, 'fileName'=>$name, 'size'=>$size];
            }

            // Senão, tenta discos (s3, public…)
            $name = basename($pathOrUrl);
            $candidates = array_values(array_filter([$disk, 's3', 'public']));
            foreach ($candidates as $d) {
                try {
                    if (!Storage::disk($d)->exists($pathOrUrl)) continue;
                    $raw = Storage::disk($d)->get($pathOrUrl);
                    $size = strlen($raw);
                    $mime = $this->guessMimeByExt($name);
                    return ['b64'=>base64_encode($raw), 'mime'=>$mime, 'fileName'=>$name, 'size'=>$size];
                } catch (\Throwable $e) { /* tenta o próximo */ }
            }

            throw new \RuntimeException('Não foi possível ler o arquivo para base64.');
        } catch (\Throwable $e) {
            \Log::error('fetchAsBase64 error', ['e'=>$e->getMessage(), 'path'=>$pathOrUrl]);
            return ['b64'=>'','mime'=>'','fileName'=>'','size'=>0];
        }
    }

    /**
     * Envia pela Z-API usando base64.
     * Usa endpoints típicos: send-image-base64 / send-document-base64 / send-video-base64
     * Ajuste se sua instância usar nomes diferentes.
     */
    private function sendViaZapiBase64(string $phone, string $b64, string $mime, string $fileName, ?string $caption=null): array
    {
        try {
            $instanceId  = env('ZAPI_INSTANCE_ID', '');
            $pathToken   = env('ZAPI_PATH_TOKEN', '');
            $clientToken = env('ZAPI_CLIENT_TOKEN', '');
            $base = "https://api.z-api.io/instances/{$instanceId}/token/{$pathToken}";

            $type = $this->classifyMediaType($mime, $fileName);
            $endpoint = match($type) {
                'image'  => 'send-image-base64',
                'video'  => 'send-video-base64',
                default  => 'send-document-base64',
            };

            $payload = [
                'phone'      => preg_replace('/\D+/', '', $phone),
                'base64'     => $b64,
                'caption'    => $caption,
                'fileName'   => $fileName,
                'mime'       => $mime,
                'mimeType'   => $mime,
            ];

            $resp = Http::asJson()
                ->timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'Client-Token' => $clientToken,
                ])->post("{$base}/{$endpoint}", $payload);

            \Log::info('Z-API base64 try', ['endpoint'=>$endpoint, 'status'=>$resp->status(), 'body'=>$resp->body()]);

            if ($resp->successful()) {
                $j = $resp->json();
                $ok = is_array($j) && ( ($j['ok'] ?? false) || isset($j['messageId']) || isset($j['id']) );
                if ($ok) return ['ok'=>true,'endpoint'=>$endpoint,'resp'=>$j];
            }
            return ['ok'=>false,'error'=>'zapi_base64_failed','status'=>$resp->status(),'body'=>$resp->body()];
        } catch (\Throwable $e) {
            \Log::error('sendViaZapiBase64 exception', ['e'=>$e->getMessage()]);
            return ['ok'=>false,'error'=>$e->getMessage()];
        }
    }

    /**
     * Detecta se a pergunta parece ser sobre condições de pagamento / unidade / torre.
     */
    protected function looksLikePaymentQuestion(string $question): bool
    {
        $norm = $this->normalizeText($question);

       $hasPaymentWords = Str::contains($norm, [
    // já existiam
    'pagamento', 'condicao de pagamento', 'condicoes de pagamento',
    'forma de pagamento', 'fluxo de pagamento',
    'sinal', 'entrada', 'mensais', 'semestrais', 'parcela unica', 'unica',

    // 🆕 novas formas de perguntar preço/valor
    'valor da unidade',
    'valor total da unidade',
    'valor total',
    'valor do apartamento',
    'valor do apto',
    'preco da unidade',
    'preco do apartamento',
    'preco total',
    'preço da unidade',
    'preço do apartamento',
    'preço total',
]);

        $hasUnitWords = Str::contains($norm, [
            'unidade', 'apto', 'apartamento', 'apt', 'ap.',
        ]);

        $hasTowerWords = Str::contains($norm, [
            'torre', 't1 ', 't2 ', 't3 ', 't4 ', 't5 ', 't 1', 't 2', 't 3', 't 4', 't 5',
        ]);

        // Bem permissivo: se falar de pagamento + (unidade ou torre), já tentamos o Excel
        return $hasPaymentWords && ($hasUnitWords || $hasTowerWords);
    }

   /**
 * Lê a planilha Excel do empreendimento no S3 e tenta responder
 * condições de pagamento para uma unidade/torre específica.
 *
 * Retorna string de resposta ou null se não conseguir.
 */
protected function answerFromExcelPayment(WhatsappThread $thread, string $question): ?string
{
    $empId     = (int) $thread->selected_empreendimento_id;
    $companyId = $this->resolveCompanyIdForThread($thread);

    if ($empId <= 0 || !$companyId) {
        return null;
    }

    // 1) Extrair unidade e torre do texto
    $unidade = $this->parseUnidadeFromText($question);
    $torre   = $this->parseTorreFromText($question);

    if ($unidade === null || $torre === null) {
        \Log::info('Pagamento Excel: não consegui extrair unidade/torre', [
            'phone'   => $thread->phone,
            'q'       => $question,
            'unidade' => $unidade,
            'torre'   => $torre,
        ]);
        return null;
    }

    // 2) Descobrir qual planilha Excel usar (no S3)
    $excelPath = $this->findPaymentExcelForEmpreendimento($companyId, $empId);
    if (!$excelPath) {
        \Log::info('Pagamento Excel: nenhuma planilha encontrada', [
            'companyId' => $companyId,
            'empId'     => $empId,
        ]);
        return null;
    }

    // 3) Baixar para /tmp e carregar com PhpSpreadsheet
    $disk    = Storage::disk('s3');
    $tmpPath = tempnam(sys_get_temp_dir(), 'emp_xls_');

    file_put_contents($tmpPath, $disk->get($excelPath));

    $spreadsheet = IOFactory::load($tmpPath);
    $sheet       = $spreadsheet->getActiveSheet();
    $rows        = $sheet->toArray(null, true, true, true);

    // 4) Descobrir linha de cabeçalho e mapeamento de colunas
    $headerIndex = null;
    $colMap      = []; // aqui vamos guardar as letras das colunas + extras

    foreach ($rows as $idx => $row) {
        $hasUnidade = false;
        $hasTorre   = false;

        foreach ($row as $col => $val) {
            $txt = $this->normalizeColHeader($val);

            if ($txt === 'unidade') {
                $hasUnidade = true;
            }
            if ($txt === 'torre') {
                $hasTorre = true;
            }
        }

        if ($hasUnidade && $hasTorre) {
            $headerIndex = $idx;

            foreach ($row as $col => $val) {
                $txt = $this->normalizeColHeader($val);

                if ($txt === 'unidade')        $colMap['unidade']      = $col;
                if ($txt === 'torre')          $colMap['torre']        = $col;
                if (str_starts_with($txt, 'preco') || str_starts_with($txt, 'preco_') || str_starts_with($txt, 'preço')) {
                    $colMap['preco'] = $col;
                }

                // todos os "Sinal ..."
                if (str_starts_with($txt, 'sinal')) {
                    if (!isset($colMap['sinais'])) {
                        $colMap['sinais'] = [];
                    }
                    $colMap['sinais'][$col] = [
                        'raw_header' => trim((string) $val),
                    ];
                }

                if (str_starts_with($txt, 'mensais')) {
                    $colMap['mensais'] = $col;
                }
                if (str_starts_with($txt, 'semestrais')) {
                    $colMap['semestrais'] = $col;
                }
                if (str_starts_with($txt, 'unica') || str_starts_with($txt, 'única')) {
                    $colMap['unica'] = $col;
                }
                if (str_contains($txt, 'financ')) {
                    $colMap['financiamento'] = $col;
                }
            }
            break;
        }
    }

    if (!$headerIndex || !isset($colMap['unidade']) || !isset($colMap['torre'])) {
        \Log::warning('Pagamento Excel: cabeçalho unidade/torre não encontrado', [
            'empId' => $empId,
            'path'  => $excelPath,
        ]);
        return null;
    }

    // 4.1) Linhas logo abaixo do cabeçalho – usamos como metadados (qtde / descrição)
    $rowQtd  = $rows[$headerIndex + 1] ?? [];
    $rowDesc = $rows[$headerIndex + 2] ?? [];

    // Monta labels amigáveis para cada coluna de Sinal + captura qtd de mensais/semestrais/única
    $signalLabels = [];
    if (!empty($colMap['sinais'])) {
        foreach ($colMap['sinais'] as $col => $meta) {
            $qtdRaw  = isset($rowQtd[$col])  ? trim((string) $rowQtd[$col])  : '';
            $descRaw = isset($rowDesc[$col]) ? trim((string) $rowDesc[$col]) : '';

            $label = 'Sinal';

            // número de parcelas
            $qtdInt = null;
            if ($qtdRaw !== '') {
                $qtdInt = (int) preg_replace('/\D+/', '', $qtdRaw);
                if ($qtdInt > 0) {
                    $label .= ' ' . $qtdInt . ' ' . ($qtdInt === 1 ? 'parcela' : 'parcelas');
                }
            }

            // descrição (“Ato”, “30 / 60 / 90” etc.)
            if ($descRaw !== '') {
                $desc = $descRaw;
                if (mb_strtolower($desc) === 'ato') {
                    $desc = 'no ato';
                }
                $label .= ' (' . $desc . ')';
            }

            $signalLabels[$col] = $label;
        }
    }

    // guarda qts de mensais / semestrais / única se existirem
    $mensaisQtd = null;
    if (isset($colMap['mensais'])) {
        $raw = $rowQtd[$colMap['mensais']] ?? null;
        if ($raw !== null && $raw !== '') {
            $mensaisQtd = (int) preg_replace('/\D+/', '', (string) $raw) ?: null;
        }
    }

    $semestraisQtd = null;
    if (isset($colMap['semestrais'])) {
        $raw = $rowQtd[$colMap['semestrais']] ?? null;
        if ($raw !== null && $raw !== '') {
            $semestraisQtd = (int) preg_replace('/\D+/', '', (string) $raw) ?: null;
        }
    }

    $unicaQtd = null;
    if (isset($colMap['unica'])) {
        $raw = $rowQtd[$colMap['unica']] ?? null;
        if ($raw !== null && $raw !== '') {
            $unicaQtd = (int) preg_replace('/\D+/', '', (string) $raw) ?: null;
        }
    }

    // 5) Procurar a linha da unidade/torre
    $dados = null;

    foreach ($rows as $idx => $row) {
        if ($idx <= $headerIndex) continue;

        $uCell = $row[$colMap['unidade']] ?? null;
        $tCell = $row[$colMap['torre']]   ?? null;

        if ($uCell === null || $tCell === null) continue;

        $uVal = (int) preg_replace('/\D+/', '', (string) $uCell);
        $tVal = (int) preg_replace('/\D+/', '', (string) $tCell);

        if ($uVal === $unidade && $tVal === $torre) {

            // monta array de sinais (pode ter 1, 2, 3… colunas de sinal)
            $sinais = [];
            if (!empty($colMap['sinais'])) {
                foreach ($colMap['sinais'] as $col => $meta) {
                    $rawValor = $row[$col] ?? null;
                    $valor    = $this->normalizeExcelNumber($rawValor, true);

                    if ($valor !== null) {
                        $label = $signalLabels[$col] ?? 'Sinal';
                        $sinais[] = [
                            'label' => $label,
                            'valor' => $valor,
                        ];
                    }
                }
            }

            $dados = [
                'unidade'       => $uVal,
                'torre'         => $tVal,
                'sinais'        => $sinais,
                'preco'         => isset($colMap['preco'])
                    ? $this->normalizeExcelNumber($row[$colMap['preco']], true)
                    : null,
                'mensais_valor'       => isset($colMap['mensais'])
                    ? $this->normalizeExcelNumber($row[$colMap['mensais']], false)
                    : null,
                'mensais_qtd'         => $mensaisQtd,
                'semestrais_valor'    => isset($colMap['semestrais'])
                    ? $this->normalizeExcelNumber($row[$colMap['semestrais']], true)
                    : null,
                'semestrais_qtd'      => $semestraisQtd,
                'unica_valor'         => isset($colMap['unica'])
                    ? $this->normalizeExcelNumber($row[$colMap['unica']], true)
                    : null,
                'unica_qtd'           => $unicaQtd,
                'financiamento_valor' => isset($colMap['financiamento'])
                    ? $this->normalizeExcelNumber($row[$colMap['financiamento']], true)
                    : null,
            ];

            break;
        }
    }

    if (!$dados) {
        \Log::info('Pagamento Excel: unidade/torre não encontrados na planilha', [
            'empId'   => $empId,
            'path'    => $excelPath,
            'unidade' => $unidade,
            'torre'   => $torre,
        ]);
        return null;
    }

    // 6) Formatar a resposta no estilo com ícones

    $precoFmt = $this->formatMoney($dados['preco'] ?? null);

    $txt  = "💰 *Informações de pagamento*\n";
    $txt .= "*Unidade {$unidade} – Torre {$torre}*";
    if ($precoFmt !== null) {
        $txt .= "\n\n*Valor da unidade*: {$precoFmt}";
    }
    $txt .= "\n\n";

    // 🔹 SINAIS
    $sinais = $dados['sinais'] ?? [];
    foreach ($sinais as $sinalInfo) {
        $label = $sinalInfo['label'] ?? 'Sinal';
        $valor = $this->formatMoney($sinalInfo['valor'] ?? null);
        if ($valor === null) continue;

        $txt .= "🔹 {$label}\n\n";
        $txt .= "{$valor}\n\n";
    }

    // 🔹 MENSAIS
    if (($dados['mensais_valor'] ?? null) !== null) {
        $mensaisVal = $this->formatMoney($dados['mensais_valor']);
        $qtd        = $dados['mensais_qtd'] ?? null;

        $txt .= "🔹 Mensais\n\n";
        if ($qtd && $qtd > 1) {
            $txt .= "{$qtd}x de {$mensaisVal}\n\n";
        } else {
            $txt .= "{$mensaisVal}\n\n";
        }
    }

    // 🔹 SEMESTRAIS
    if (($dados['semestrais_valor'] ?? null) !== null) {
        $semVal = $this->formatMoney($dados['semestrais_valor']);
        $qtd    = $dados['semestrais_qtd'] ?? null;

        $txt .= "🔹 Semestrais\n\n";
        if ($qtd && $qtd > 1) {
            $txt .= "{$qtd}x de {$semVal}\n\n";
        } else {
            $txt .= "{$semVal}\n\n";
        }
    }

    // 🔹 PARCELA ÚNICA
    if (($dados['unica_valor'] ?? null) !== null) {
        $unicVal = $this->formatMoney($dados['unica_valor']);

        $txt .= "🔹 Parcela Única\n\n";
        $txt .= "{$unicVal}\n\n";
    }

    // 🔹 FINANCIAMENTO
    if (($dados['financiamento_valor'] ?? null) !== null) {
        $finVal = $this->formatMoney($dados['financiamento_valor']);

        $txt .= "🔹 Financiamento bancário\n\n";
        $txt .= "{$finVal}";
    }

    return rtrim($txt);
}


    /**
     * Procura um arquivo Excel (xls/xlsx) na pasta do empreendimento no S3.
     * Ex.: documentos/tenants/{companyId}/empreendimentos/{empId}/
     */
    protected function findPaymentExcelForEmpreendimento(int $companyId, int $empId): ?string
    {
        $prefix = "documentos/tenants/{$companyId}/empreendimentos/{$empId}/";

        $disk  = Storage::disk('s3');
        $files = $disk->files($prefix);

        $excel = array_values(array_filter($files, function ($path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            return in_array($ext, ['xls','xlsx'], true);
        }));

        if (empty($excel)) {
            return null;
        }

        // Se houver mais de uma, tenta priorizar as que têm "tabela" ou "preco" no nome
        usort($excel, function ($a, $b) {
            $an = strtolower(basename($a));
            $bn = strtolower(basename($b));

            $aScore = (str_contains($an, 'tabela') || str_contains($an, 'preco') || str_contains($an, 'preço')) ? 0 : 1;
            $bScore = (str_contains($bn, 'tabela') || str_contains($bn, 'preco') || str_contains($bn, 'preço')) ? 0 : 1;

            if ($aScore === $bScore) {
                return strcmp($an, $bn);
            }
            return $aScore <=> $bScore;
        });

        return $excel[0];
    }

    /** Normaliza nome de coluna (remove acento, minúsculo) para mapear cabeçalhos da planilha. */
    protected function normalizeColHeader($val): string
    {
        $txt = trim((string) $val);
        $txt = mb_strtolower($txt);

        if (class_exists('\Normalizer')) {
            $txt = \Normalizer::normalize($txt, \Normalizer::FORM_D);
            $txt = preg_replace('/\p{Mn}+/u', '', $txt);
        }

        // substitui espaços por underline para comparações simples
        $txt = preg_replace('/\s+/', '_', $txt);

        return $txt;
    }

  /** Extrai número da unidade da pergunta (ex.: "unidade 301", "unidade 1.802", "apto 205"). */
protected function parseUnidadeFromText(string $question): ?int
{
    $q = mb_strtolower($question);

    // padrão: "unidade 1802" ou "unidade 1.802"
    if (preg_match('/unidade\s+([\d\.]{1,7})/u', $q, $m)) {
        $num = (int) preg_replace('/\D+/', '', $m[1]); // tira pontos, vírgulas etc. → "1.802" -> "1802"
        return $num > 0 ? $num : null;
    }

    // padrão: "apto 1802" ou "apto 1.802"
    if (preg_match('/apto\.?\s+([\d\.]{1,7})/u', $q, $m)) {
        $num = (int) preg_replace('/\D+/', '', $m[1]);
        return $num > 0 ? $num : null;
    }

    // padrão: "apartamento 1802" ou "apartamento 1.802"
    if (preg_match('/apartamento\s+([\d\.]{1,7})/u', $q, $m)) {
        $num = (int) preg_replace('/\D+/', '', $m[1]);
        return $num > 0 ? $num : null;
    }

    return null;
}


    /** Extrai número da torre da pergunta (ex.: "torre 5", "T5"). */
    protected function parseTorreFromText(string $question): ?int
    {
        $q = mb_strtolower($question);

        if (preg_match('/torre\s+(\d{1,2})/u', $q, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/\bt(\d{1,2})\b/u', $q, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** Formata valores numéricos em R$ (aceita número ou string). */
    protected function formatMoney($val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        // Se já vier numérico
        if (is_int($val) || is_float($val)) {
            return 'R$ ' . number_format((float) $val, 2, ',', '.');
        }

        // Se vier string, normaliza primeiro
        $num = $this->normalizeExcelNumber($val);
        if ($num === null) {
            return 'R$ ' . trim((string) $val);
        }

        return 'R$ ' . number_format($num, 2, ',', '.');
    }

    /**
     * Converte valor vindo do Excel (string ou número) em float.
     * Não faz nenhum ajuste de escala (/1000 ou *1000).
     * Apenas interpreta corretamente milhar e decimal nos formatos
     * "2.190,49", "2190,49", "2190.49", "2 190,49" etc.
     */
   /**
 * Converte valor vindo do Excel (string ou número) em float.
 * Não faz nenhum ajuste de escala (/1000 ou *1000).
 * Interpreta corretamente:
 * - "2.190,49" (BR – ponto milhar, vírgula decimal)
 * - "2,190.49" (US – vírgula milhar, ponto decimal)
 * - "2190,49"
 * - "2190.49"
 * - "2190"
 */
protected function normalizeExcelNumber($val, bool $expectBig = false): ?float
{
    if ($val === null || $val === '') {
        return null;
    }

    // Já é número
    if (is_int($val) || is_float($val)) {
        return (float) $val;
    }

    $str = (string) $val;
    $str = trim($str);
    $str = str_replace(['R$', ' '], '', $str);

    // mantém só dígitos, ponto e vírgula
    $str = preg_replace('/[^\d\.,]/', '', $str);

    if ($str === '' || $str === null) {
        return null;
    }

    $hasComma = str_contains($str, ',');
    $hasDot   = str_contains($str, '.');

    if ($hasComma && $hasDot) {
        $posComma = strpos($str, ',');
        $posDot   = strpos($str, '.');

        if ($posComma < $posDot) {
            // "2,179.84" → vírgula milhar, ponto decimal
            $str = str_replace(',', '', $str);   // -> "2179.84"
        } else {
            // "2.190,49" → ponto milhar, vírgula decimal
            $str = str_replace('.', '', $str);   // -> "2190,49"
            $str = str_replace(',', '.', $str);  // -> "2190.49"
        }
    } elseif ($hasComma && !$hasDot) {
        // "2190,49"
        $str = str_replace('.', '', $str);
        $str = str_replace(',', '.', $str);
    }
    // Se só tem ponto, "2190.49" - deixa como está

    if (!is_numeric($str)) {
        return null;
    }

    $num = (float) $str;

    // 🔴 IMPORTANTE: aqui NÃO fazemos mais nenhum ajuste de escala (nem *1000, nem /1000)
    return $num;
}
protected function buildAndStoreProposalPdf(
    WhatsappThread $thread,
    int $empId,
    string $unidade,
    ?string $grupo,
    string $textoPagamento
): ?string {
    try {
        $emp = Empreendimento::find($empId);
        if (!$emp) {
            Log::warning('buildAndStoreProposalPdf: empreendimento não encontrado', [
                'empId' => $empId,
            ]);
            return null;
        }

        // === NOME DO EMPREENDIMENTO ===
        $empreendimentoNome = $emp->nome
            ?? $emp->titulo
            ?? $emp->name
            ?? 'Empreendimento';

        // === TORRE (sua Blade usa "torre", não "grupo") ===
        $torre = $grupo ?: null;

        // === CIDADE / UF do empreendimento ===
        $cidadeUf = trim(($emp->cidade ?? '') . ' / ' . ($emp->uf ?? ''));
        if ($cidadeUf === '/')
            $cidadeUf = null;

        // === IMOBILIÁRIA (logo, nome, site) ===
        $imobiliariaNome  = $emp->imobiliaria_nome  ?? $emp->nome_imobiliaria  ?? null;
        $imobiliariaSite  = $emp->imobiliaria_site  ?? null;
        $imobiliariaLogo  = $emp->imobiliaria_logo  ?? null;

        // === Corretor responsável ===
        $corretor         = $thread->user ?? null;
        $corretorNome     = $corretor->name     ?? null;
        $corretorTelefone = $corretor->phone    ?? $corretor->whatsapp ?? null;

        // === Data ===
        $hoje = now()->format('d/m/Y H:i');

       // limpa texto para PDF (remove emojis e lixo de charset)
$textoPagamentoPdf = $this->sanitizePaymentTextForPdf($textoPagamento);

// 1) Monta o HTML do PDF
$html = view('pdf.proposta_unidade', [
    'empreendimentoNome' => $emp->nome ?? 'Empreendimento',
    'unidade'            => $unidade,
    'torre'              => $grupo,
    'cidadeUf'           => $emp->cidade_uf ?? null,
    'textoPagamento'     => $textoPagamentoPdf,
    'hoje'               => now()->format('d/m/Y'),
    'imobiliariaNome'    => $emp->imobiliaria_nome ?? 'Imobiliária',
    'imobiliariaSite'    => $emp->imobiliaria_site ?? null,
    'imobiliariaLogo'    => $emp->imobiliaria_logo_url ?? null,
    'corretorNome'       => optional($thread->user)->name ?? null,
    'corretorTelefone'   => optional($thread->user)->phone ?? null,
])->render();


        // === Gera o PDF ===
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 0)
            ->setOption('margin-right', 0)
            ->setOption('margin-bottom', 0)
            ->setOption('margin-left', 0);
        $pdfContent = $pdf->output();

        // === Caminho no S3 ===
        $companyId = $thread->tenant_id ?? 1;

        $folder = "documentos/tenants/{$companyId}/empreendimentos/{$empId}/propostas/";
        $ts     = time();
        $slugTorre = $torre ? preg_replace('/\s+/', '_', mb_strtolower($torre)) : 'sem_torre';
        $fileName  = "proposta_{$empId}_u{$unidade}_{$slugTorre}_{$ts}.pdf";

        $relativePath = $folder . $fileName;

        Storage::disk('s3')->put($relativePath, $pdfContent, 'private');

        Log::info('WPP PROPOSTA: PDF gerado e salvo', [
            'phone'   => $thread->phone ?? null,
            'empId'   => $empId,
            'path'    => $relativePath,
            'company' => $companyId,
        ]);

        return $relativePath;

    } catch (\Throwable $e) {
        Log::error('buildAndStoreProposalPdf: erro ao gerar/salvar PDF', [
            'empId' => $empId,
            'e'     => $e->getMessage(),
        ]);
        return null;
    }
}

protected function lookupLocalAnswer(int $empId, string $question): ?string
{
    $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($question)));
    $hash = sha1($normalized);

    // 1) tenta cache em memória (rápido)
    $cacheKey = "wpp:answer_local:{$empId}:{$hash}";
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }

    // 2) tenta banco
    $row = WhatsappQaCache::where('empreendimento_id', $empId)
        ->where('question_hash', $hash)
        ->first();

    if ($row) {
        $row->increment('hits', 1, ['last_hit_at' => now()]);
        Cache::put($cacheKey, $row->answer, now()->addHours(12));
        return $row->answer;
    }

    return null;
}

/**
 * Normaliza pergunta para uso em cache/similaridade.
 * (minúsculo, sem acento, espaços comprimidos)
 */
protected function normalizeQuestionForCache(string $q): string
{
    $t = mb_strtolower(trim($q));
    $t = preg_replace('/\s+/', ' ', $t);

    if (class_exists('\Normalizer')) {
        $t = \Normalizer::normalize($t, \Normalizer::FORM_D);
        $t = preg_replace('/\p{Mn}+/u', '', $t);
    }

    return trim($t);
}

/**
 * Gera embedding para texto usando OpenAI.
 */
protected function embedQuestion(string $text): ?array
{
    try {
        $client = OpenAI::client(config('services.openai.key'));

        $res = $client->embeddings()->create([
            'model' => 'text-embedding-3-small', // baratinho
            'input' => $text,
        ]);

        $vec = $res->data[0]->embedding ?? null;
        if (!is_array($vec) || empty($vec)) {
            return null;
        }

        return $vec;
    } catch (\Throwable $e) {
        \Log::warning('IA-LOCAL: erro ao gerar embedding', [
            'err' => $e->getMessage(),
        ]);
        return null;
    }
}

/**
 * Calcula similaridade de cosseno entre dois vetores.
 */
protected function cosineSimilarity(array $a, array $b): float
{
    $lenA = count($a);
    $lenB = count($b);
    if ($lenA === 0 || $lenB === 0 || $lenA !== $lenB) {
        return 0.0;
    }

    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;

    for ($i = 0; $i < $lenA; $i++) {
        $va = (float) $a[$i];
        $vb = (float) $b[$i];
        $dot   += $va * $vb;
        $normA += $va * $va;
        $normB += $vb * $vb;
    }

    if ($normA == 0.0 || $normB == 0.0) {
        return 0.0;
    }

    return $dot / (sqrt($normA) * sqrt($normB));
}


protected function storeLocalAnswer(int $empId, string $question, string $answer, string $source): void
{
    $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($question)));
    $hash = sha1($normalized);

    $row = WhatsappQaCache::updateOrCreate(
        [
            'empreendimento_id' => $empId,
            'question_hash'     => $hash,
        ],
        [
            'question_norm' => $normalized,
            'answer'        => $answer,
            'source'        => $source,
            'last_hit_at'   => now(),
        ]
    );

    Cache::put("wpp:answer_local:{$empId}:{$hash}", $row->answer, now()->addHours(12));
}
/**
 * Salva uma pergunta/resposta no cache local (whatsapp_qa_cache)
 * com embedding para similaridade futura.
 *
 * - $empId    → ID do empreendimento
 * - $question → pergunta original do corretor
 * - $answer   → resposta que foi enviada pra ele
 * - $meta     → metadados opcionais (ex.: ['source' => 'excel_pagamento'])
 */
protected function saveQaToLocalCache(int $empId, string $question, string $answer, array $meta = []): void
{
    $question = trim($question);
    $answer   = trim($answer);

    if ($empId <= 0 || $question === '' || $answer === '') {
        \Log::info('WPP IA-LOCAL: saveQaToLocalCache ignorado (dados insuficientes)', [
            'empId'    => $empId,
            'question' => $question,
            'answer'   => mb_substr($answer, 0, 80),
        ]);
        return;
    }

    // Normaliza a pergunta (minúsculo, sem acento, etc.) para usar como chave
    $norm = $this->normalizeText($question);

    // Gera embedding da pergunta normalizada
    $embedding = null;

    try {
        $client = OpenAI::client(config('services.openai.key'));

        $res = $client->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $norm,
        ]);

        $embedding = $res->data[0]->embedding ?? null;
    } catch (\Throwable $e) {
        \Log::warning('WPP IA-LOCAL: falha ao gerar embedding para cache', [
            'empId' => $empId,
            'error' => $e->getMessage(),
        ]);
    }

    try {
        $payload = [
            'empreendimento_id' => $empId,
            'question'          => $question,
            'question_norm'     => $norm,
            'answer'            => $answer,
            'meta'              => !empty($meta)
                                    ? json_encode($meta, JSON_UNESCAPED_UNICODE)
                                    : null,
            'updated_at'        => now(),
        ];

        if ($embedding !== null) {
            // salva como JSON; o getLocalAnswerFromCache faz json_decode
            $payload['embedding'] = json_encode($embedding);
        }

        // Usa question_norm + empreendimento_id como chave de upsert
        DB::table('whatsapp_qa_cache')->updateOrInsert(
            [
                'empreendimento_id' => $empId,
                'question_norm'     => $norm,
            ],
            $payload
        );

        \Log::info('WPP IA-LOCAL: saveQaToLocalCache gravado/atualizado', [
            'empId'         => $empId,
            'q_norm'        => $norm,
            'has_embedding' => $embedding !== null,
        ]);
    } catch (\Throwable $e) {
        \Log::error('WPP IA-LOCAL: erro ao salvar no whatsapp_qa_cache', [
            'empId' => $empId,
            'error' => $e->getMessage(),
        ]);
    }
}


/**
 * Tenta encontrar resposta em whatsapp_qa_cache.
 *
 * 1) Match EXATO por pergunta_normalizada
 * 2) Se não achar, usa similaridade por embedding
 *
 * Retorna string da resposta ou null.
 */
protected function findAnswerInLocalCacheWithSimilarity(int $empId, string $question, float $threshold = 0.90): ?string
{
    $norm = $this->normalizeQuestionForCache($question);

    // 1) Match exato
    $rowExact = DB::table('whatsapp_qa_cache')
        ->where('empreendimento_id', $empId)
        ->where('pergunta_normalizada', $norm)
        ->orderByDesc('id')
        ->first();

    if ($rowExact && !empty($rowExact->resposta)) {
        \Log::info('WPP IA-LOCAL: resposta encontrada em whatsapp_qa_cache (exata)', [
            'empId' => $empId,
        ]);
        return $rowExact->resposta;
    }

    // 2) Similaridade (se embedding funcionar)
    $queryVec = $this->embedQuestion($norm);
    if (!$queryVec) {
        return null;
    }

    // pega até N últimas perguntas desse empreendimento com embedding
    $candidates = DB::table('whatsapp_qa_cache')
        ->where('empreendimento_id', $empId)
        ->whereNotNull('pergunta_embedding')
        ->orderByDesc('id')
        ->limit(50)
        ->get();

    $bestSim   = 0.0;
    $bestRow   = null;

    foreach ($candidates as $row) {
        $embJson = $row->pergunta_embedding ?? null;
        if (!$embJson) continue;

        $emb = json_decode($embJson, true);
        if (!is_array($emb) || empty($emb)) continue;

        $sim = $this->cosineSimilarity($queryVec, $emb);

        if ($sim > $bestSim) {
            $bestSim = $sim;
            $bestRow = $row;
        }
    }

    if ($bestRow && $bestSim >= $threshold && !empty($bestRow->resposta)) {
        \Log::info('WPP IA-LOCAL: resposta encontrada em whatsapp_qa_cache (similar)', [
            'empId'      => $empId,
            'similarity' => $bestSim,
        ]);
        return $bestRow->resposta;
    }

    return null;
}

protected function looksLikeStatusChangeIntent(string $msg): bool
{
    $msg = mb_strtolower($msg);

    return Str::contains($msg, [
        'reservar', 'reserve', 'deixar reservado',
        'fechar', 'vendido', 'fechada', 'fechado', 'vender',
        'liberar', 'deixar livre', 'tornar livre',
        'mudar status', 'alterar status'
    ]);
}
// ia responde sobre as unidades do empreendimento
protected function handleUnidadesPergunta(int $empreendimentoId, string $msg): ?string
{
    $msgNorm = mb_strtolower(trim($msg));

    // 🚫 Se a pergunta for de PROPOSTA ou PAGAMENTO,
    // não usa a tabela empreendimento_unidades.
    // Deixa cair no fluxo normal (Excel + PDF / IA).
    if ($this->looksLikeProposalRequest($msg) || $this->looksLikePaymentQuestion($msg)) {
        return null;
    }

    // 0) INTENÇÃO DE ALTERAR STATUS
    // Ex.: "reservar unidade 102", "mudar a unidade 301 para fechado", "deixar a unidade 205 livre"
    if ($this->looksLikeStatusChangeIntent($msgNorm)) {

        // extrai unidade (102, 201A, etc.)
        if (preg_match('/(unidade|apto|apartamento|casa|lote)\s+([0-9]+[a-zA-Z0-9]*)/u', $msgNorm, $m)) {
            $codigo = strtoupper($m[2]);
        } else {
            return "Qual unidade você quer alterar o status? Ex.: *reservar unidade 102*";
        }

        // extrai o novo status (livre / reservado / fechado)
        $novoStatus = $this->extractStatusFromText($msgNorm);
        if (!$novoStatus) {
            return "Para alterar o status, preciso saber se deve ficar *livre*, *reservado* ou *fechado*.";
        }

        // atualiza no banco e devolve mensagem pronta
        return $this->changeUnitStatus($empreendimentoId, $codigo, $novoStatus);
    }

    // 1) Perguntas do tipo: "a unidade 102 está disponível?"
    if (preg_match('/(unidade|apto|apartamento|casa|lote)\s+([0-9]+[a-zA-Z0-9]*)/u', $msgNorm, $m)) {
        $codigo = strtoupper($m[2]); // 102, 201A, etc

        $unidades = EmpreendimentoUnidade::where('empreendimento_id', $empreendimentoId)
            ->whereRaw('LOWER(unidade) = ?', [mb_strtolower($codigo)])
            ->orderBy('grupo_unidade')
            ->get();

        if ($unidades->isEmpty()) {
            return "Não encontrei a unidade {$codigo} nesse empreendimento. 🤔";
        }

        // Se tiver em mais de um grupo (Torre 1 / Torre 2), lista todos
        if ($unidades->count() > 1) {
            $linhas = [];
            foreach ($unidades as $u) {
                $g = $u->grupo_unidade ?: 'Sem grupo';
                $linhas[] = "- {$g} — {$u->unidade}: " . $this->statusHumano($u->status);
            }

            $txt  = "Encontrei a unidade {$codigo} em mais de um grupo:\n";
            $txt .= implode("\n", $linhas);
            $txt .= "\n\nSe quiser, me diga também a torre/bloco/quadra da unidade 😉";

            return $txt;
        }

        // Só uma unidade encontrada
        $u = $unidades->first();
        $g = $u->grupo_unidade ?: 'sem grupo específico';
        $statusTxt = $this->statusHumano($u->status);

        return "A unidade {$u->unidade} ({$g}) está atualmente: *{$statusTxt}*.";
    }

    // 2) Perguntas do tipo: "quais unidades disponíveis?", "quais estão livres?"
    $perguntaDisponiveis = false;
    if (
        str_contains($msgNorm, 'unidades disponiveis') ||
        str_contains($msgNorm, 'unidades disponíveis') ||
        str_contains($msgNorm, 'unidades livres') ||
        (str_contains($msgNorm, 'quais') && (str_contains($msgNorm, 'livres') || str_contains($msgNorm, 'disponiveis')))
    ) {
        $perguntaDisponiveis = true;
    }

    if ($perguntaDisponiveis) {
        $query = EmpreendimentoUnidade::where('empreendimento_id', $empreendimentoId)
            ->where('status', EmpreendimentoUnidade::STATUS_LIVRE);

        // Tenta detectar grupo/torre/bloco na pergunta:
        // ex: "quais unidades livres na torre 1"
        if (preg_match('/(torre|bloco|quadra|ala|alameda)\s+([a-z0-9]+)/u', $msgNorm, $m)) {
            $grupoBusca = trim($m[1] . ' ' . $m[2]);
            $query->whereRaw('LOWER(grupo_unidade) LIKE ?', ['%' . mb_strtolower($grupoBusca) . '%']);
        }

        $livres = $query->orderBy('grupo_unidade')
                        ->orderBy('unidade')
                        ->limit(80) // evita resposta gigante
                        ->get();

        if ($livres->isEmpty()) {
            return "No momento não encontrei nenhuma unidade livre nesse empreendimento.";
        }

        $porGrupo = $livres->groupBy(function ($u) {
            return $u->grupo_unidade ?: 'Sem grupo';
        });

        $linhas = [];
        foreach ($porGrupo as $grupoNome => $its) {
            $codigos = $its->pluck('unidade')->toArray();
            $linhas[] = "*{$grupoNome}*: " . implode(', ', $codigos);
        }

        $txt  = "Estas são algumas unidades *livres* neste empreendimento:\n";
        $txt .= implode("\n", $linhas);

        if ($livres->count() >= 80) {
            $txt .= "\n\n(Existe um número maior de unidades livres. Se quiser, posso filtrar por torre/bloco).";
        }

        return $txt;
    }

    // 3) Se não reconheci como pergunta de unidade, deixa a IA normal responder
    return null;
}


/**
 * Comandos para mudar status de unidade via WhatsApp.
 *
 * Exemplos aceitos:
 * - "reservar unidade 102"
 * - "reservar unidade 102 da torre 1"
 * - "colocar 102 como livre"
 * - "deixar a 304 livre na torre 2"
 * - "vende a 402 t2"
 * - "fechar unidade 305 bloco b"
 * - "tirar a 101 da reserva"
 */
protected function handleUnidadesStatusComando(int $empreendimentoId, string $msgNorm, bool $canAlter): ?string
{
    // $msgNorm já vem minúsculo e sem acento (veio de normalizeText)
    $txt = $msgNorm;

    // 1) Descobrir QUAL status o corretor quer aplicar
    $statusTarget = null;

  // ➤ Reservado
if (Str::contains($txt, [
    'reservar',
    'reserva ',
    'reservado',      // ← novo
    'reservada',      // ← novo
    'deixar reservado', // se quiser reforçar
    'bloquear',
    'bloqueia',
    'segurar',
    'segura ',
])) {
    $statusTarget = EmpreendimentoUnidade::STATUS_RESERVADO;
}


    // ➤ Fechado / vendido
    if ($statusTarget === null && Str::contains($txt, [
        'fechar',
        'fechou',
        'vendida',
        'vendido',
        'vender',
        'vende ',
        'assinou',
        'assinaram',
    ])) {
        $statusTarget = EmpreendimentoUnidade::STATUS_FECHADO;
    }

    // ➤ Livre (mas tomando cuidado pra não confundir com "quais unidades livres?")
    if ($statusTarget === null) {
        $temLivre = Str::contains($txt, 'livre');
        $temFraseUnidadesLivres =
            Str::contains($txt, 'unidades livres') ||
            Str::contains($txt, 'unidades disponiveis') ||
            Str::contains($txt, 'unidades disponíveis');

        if (
            ($temLivre && !$temFraseUnidadesLivres) ||
            Str::contains($txt, [
                'colocar como livre',
                'deixar livre',
                'deixa livre',
                'voltar pra livre',
                'voltar para livre',
                'tirar da reserva',
                'tirar reserva',
                'liberar',
                'libera ',
                'desbloquear',
            ])
        ) {
            $statusTarget = EmpreendimentoUnidade::STATUS_LIVRE;
        }
    }

    // Se não achou nenhuma intenção clara de mudança de status, não entra nesse fluxo
    if ($statusTarget === null) {
        return null;
    }

    // 🔐 Permissão: só DIRETOR pode alterar
    if (!$canAlter) {
        return "Esses comandos de alterar status de unidade só podem ser usados por usuários com papel *DIRETOR*.";
    }

    // 2) Descobrir qual unidade (código: 102, 402A, 1501 etc.)
    $codigo = null;

    // padrão explícito: "unidade 102", "apto 304", "apartamento 305", "casa 10"
    if (preg_match('/(unidade|apto|apartamento|casa|lote)\s+([0-9]+[a-z0-9]*)/u', $txt, $m)) {
        $codigo = strtoupper($m[2]);
    }

    // fallback: "reservar 102 t1", "vende 402 t2", etc. (primeiro número de 2+ dígitos)
    if ($codigo === null && preg_match('/\b(\d{2,5}[a-z0-9]*)\b/u', $txt, $m2)) {
        $codigo = strtoupper($m2[1]);
    }

    if ($codigo === null) {
        return "Não entendi qual unidade você quer atualizar. Me manda algo como: *reservar unidade 102 da torre 1*.";
    }

   // 3) Descobrir torre/bloco/grupo, se veio na frase
// exemplos capturados:
// - "torre 1", "torre1"
// - "bloco a", "quadra b", "ala 2", "alameda azul"
$grupoBusca = null;
if (preg_match('/(torre|bloco|quadra|ala|alameda|grupo|setor|modulo)\s+([a-z0-9]+)/u', $txt, $mg)) {
    $grupoBusca = trim($mg[1] . ' ' . $mg[2]); // "torre 1", "bloco a", "grupo 2"
}


    // 4) Buscar unidade(s) no banco
    $query = EmpreendimentoUnidade::where('empreendimento_id', $empreendimentoId)
        ->whereRaw('LOWER(unidade) = ?', [mb_strtolower($codigo)]);

    if ($grupoBusca) {
        $query->whereRaw('LOWER(grupo_unidade) LIKE ?', ['%' . mb_strtolower($grupoBusca) . '%']);
    }

    $unidades = $query->get();

    if ($unidades->isEmpty()) {
        if ($grupoBusca) {
            return "Não encontrei a unidade {$codigo} em {$grupoBusca} nesse empreendimento. 🤔";
        }
        return "Não encontrei a unidade {$codigo} nesse empreendimento. 🤔";
    }

    // Se vierem várias unidades e o corretor NÃO informou torre/bloco,
    // evita sair mudando tudo sem querer
    if ($unidades->count() > 1 && !$grupoBusca) {
        $linhas = [];
        foreach ($unidades as $u) {
            $g = $u->grupo_unidade ?: 'Sem grupo';
            $linhas[] = "- {$g} — {$u->unidade}: " . $this->statusHumano($u->status);
        }

        $txt  = "Encontrei a unidade {$codigo} em mais de um grupo:\n";
        $txt .= implode("\n", $linhas);
        $txt .= "\n\nMe diga também a *torre/bloco/quadra* pra eu atualizar o status certinho 😉";

        return $txt;
    }

    // 5) Atualizar status das unidades encontradas (normalmente será 1 registro)
    $atualizadas = 0;
    foreach ($unidades as $u) {
        $u->status = $statusTarget;
        $u->save();
        $atualizadas++;
    }

    $statusHum = $this->statusHumano($statusTarget);

    if ($atualizadas === 1) {
        $u = $unidades->first();
        $g = $u->grupo_unidade ?: 'sem grupo específico';

        return "Status da unidade {$u->unidade} ({$g}) foi atualizado para: *{$statusHum}*.";
    }

    // mais de uma (mas aqui só acontece se ele mandou com grupo e mesmo assim existem várias iguais)
    return "Status das unidades {$codigo} em {$atualizadas} registro(s) foi atualizado para: *{$statusHum}*.";
}


protected function statusHumano(string $status): string
{
    return match ($status) {
        EmpreendimentoUnidade::STATUS_LIVRE      => 'Livre / disponível',
        EmpreendimentoUnidade::STATUS_RESERVADO  => 'Reservado',
        EmpreendimentoUnidade::STATUS_FECHADO    => 'Fechado / vendido',
        default                                  => ucfirst($status),
    };
}


protected function extractStatusFromText(string $msg): ?string
{
    $msg = mb_strtolower($msg);

    if (Str::contains($msg, ['livre','disponivel','disponível'])) {
        return EmpreendimentoUnidade::STATUS_LIVRE;
    }
    if (Str::contains($msg, ['reservar','reservado','reserve'])) {
        return EmpreendimentoUnidade::STATUS_RESERVADO;
    }
    if (Str::contains($msg, ['fechar','fechado','vendido','vender'])) {
        return EmpreendimentoUnidade::STATUS_FECHADO;
    }

    return null;
}
/**
 * Muda o status de uma unidade com base em frases do tipo:
 * - "reservar unidade 102"
 * - "reservar unidade 102 da torre 1"
 * - "liberar a unidade 101 bloco A"
 * - "marcar unidade 301 como vendida"
 */
protected function changeUnitStatus(int $empreendimentoId, string $msg): ?string
{
    $msgNorm = mb_strtolower(trim($msg));

    // 1) Descobrir qual status o corretor quer aplicar
    $targetStatus = null;

    // Reservar
    if (str_contains($msgNorm, 'reservar') || str_contains($msgNorm, 'reservada') || str_contains($msgNorm, 'reserva') || str_contains($msgNorm, 'bloquear')) {
        $targetStatus = EmpreendimentoUnidade::STATUS_RESERVADO;
    }

    // Liberar / deixar livre
    if (
        str_contains($msgNorm, 'liberar') ||
        str_contains($msgNorm, 'deixar livre') ||
        str_contains($msgNorm, 'colocar livre') ||
        str_contains($msgNorm, 'disponivel') ||
        str_contains($msgNorm, 'disponível')
    ) {
        $targetStatus = EmpreendimentoUnidade::STATUS_LIVRE;
    }

    // Fechar / vender
    if (
        str_contains($msgNorm, 'fechar') ||
        str_contains($msgNorm, 'vendida') ||
        str_contains($msgNorm, 'vendido') ||
        str_contains($msgNorm, 'fechada')
    ) {
        $targetStatus = EmpreendimentoUnidade::STATUS_FECHADO;
    }

    // Se não detectei intenção de mudar status, devolve null para cair no fluxo normal
    if (!$targetStatus) {
        return null;
    }

    // 2) Extrair o código da unidade: "unidade 102", "apto 301", etc.
    if (!preg_match('/(unidade|apto|apartamento|casa|lote)\s+([0-9]+[a-z0-9]*)/u', $msgNorm, $m)) {
        // Sem unidade clara → deixa o fluxo normal continuar
        return null;
    }

    $codigo = strtoupper($m[2]); // 102, 201A etc.

    // 3) Tentar extrair torre/bloco/quadra da frase (opcional)
    $grupoBusca = null;
    if (preg_match('/(torre|bloco|quadra|ala|alameda)\s+([a-z0-9]+)/u', $msgNorm, $g)) {
        // ex: "torre 1", "bloco a"
        $grupoBusca = trim($g[1] . ' ' . $g[2]);
    }

    // 4) Montar consulta
    $q = EmpreendimentoUnidade::where('empreendimento_id', $empreendimentoId)
        ->whereRaw('LOWER(unidade) = ?', [mb_strtolower($codigo)]);

    if ($grupoBusca !== null) {
        // filtra por grupo (torre/bloco/etc) se vier na frase
        $q->whereRaw('LOWER(grupo_unidade) LIKE ?', ['%' . mb_strtolower($grupoBusca) . '%']);
    }

    $unidades = $q->get();

    if ($unidades->isEmpty()) {
        // mensagem amigável se não achou
        $extra = $grupoBusca
            ? " em {$grupoBusca}"
            : '';
        return "Não encontrei a unidade {$codigo}{$extra} nesse empreendimento. 🤔";
    }

    // 5) Atualiza status de todas as unidades encontradas (normalmente 1, mas pode ser >1)
    foreach ($unidades as $u) {
        $u->status = $targetStatus;
        $u->save();
    }

    $statusTxt = $this->statusHumano($targetStatus);

    // Se só achou 1 unidade
    if ($unidades->count() === 1) {
        $u = $unidades->first();
        $g = $u->grupo_unidade ?: 'sem grupo específico';

        return "Status da unidade {$u->unidade} ({$g}) atualizado para: *{$statusTxt}*.";
    }

    // Se mesmo com torre/bloco veio mais de uma (muito raro, mas seguro)
    if ($grupoBusca !== null) {
        return "Status das unidades {$codigo} em {$grupoBusca} atualizado para: *{$statusTxt}*.";
    }

    return "Status das unidades {$codigo} (em múltiplas torres/grupos) atualizado para: *{$statusTxt}*.";
}



//MENU 
protected function isShortcutMenuCommand(string $norm): bool
{
    // $norm já vem minúsculo e sem acento
    // Verifica se é EXATAMENTE "menu" (com ou sem espaços extras)
    // Isso evita que "resumo" ou outras palavras contenham "menu" sejam capturadas
    $trimmed = trim($norm);
    return $trimmed === 'menu';
}

protected function buildShortcutMenuText(WhatsappThread $thread): string
{
    $hasEmp = !empty($thread->selected_empreendimento_id);

    $txt  = "📋 *Menu de Atalhos*\n\n";
    
    // Opções sempre disponíveis
    $txt .= "🔄 *Mudar empreendimento*\n";
    $txt .= "➕ *Criar empreendimento*\n\n";
    
    if ($hasEmp) {
        // Opções que requerem empreendimento selecionado
        $txt .= "📁 *Arquivos e Documentos*\n";
        $txt .= "1️⃣ Ver arquivos do empreendimento\n";
        $txt .= "2️⃣ Solicitar arquivos por número (ex: 1,2,5)\n\n";
        
        $txt .= "🏢 *Unidades*\n";
        $txt .= "3️⃣ Consultar unidades livres\n";
        $txt .= "4️⃣ Consultar informações de pagamento de unidade\n";
        $txt .= "5️⃣ Gerar proposta em PDF de unidade\n";
        $txt .= "6️⃣ Atualizar status de unidades\n\n";
        
        $txt .= "📸 *Galeria*\n";
        $txt .= "7️⃣ Enviar fotos/vídeos para galeria\n\n";
        
        $txt .= "❓ *Perguntas*\n";
        $txt .= "8️⃣ Fazer pergunta sobre o empreendimento\n\n";
        
        $txt .= "💡 *Dicas*\n";
        $txt .= "• Digite o número da opção (ex: 1, 3, 5)\n";
        $txt .= "• Ou escreva o comando diretamente\n";
        $txt .= "• Exemplos:\n";
        $txt .= "  - *ver arquivos*\n";
        $txt .= "  - *quais unidades livres?*\n";
        $txt .= "  - *pagamento unidade 301 torre 5*\n";
        $txt .= "  - *proposta unidade 2201*\n";
        $txt .= "  - *qual endereço do empreendimento?*";
    } else {
        $txt .= "_⚠️ Para usar as opções abaixo, primeiro selecione um empreendimento._\n\n";
        $txt .= "📁 *Arquivos e Documentos*\n";
        $txt .= "1️⃣ Ver arquivos do empreendimento\n";
        $txt .= "2️⃣ Solicitar arquivos por número\n\n";
        
        $txt .= "🏢 *Unidades*\n";
        $txt .= "3️⃣ Consultar unidades livres\n";
        $txt .= "4️⃣ Consultar informações de pagamento\n";
        $txt .= "5️⃣ Gerar proposta em PDF\n";
        $txt .= "6️⃣ Atualizar status de unidades\n\n";
        
        $txt .= "📸 *Galeria*\n";
        $txt .= "7️⃣ Enviar fotos/vídeos\n\n";
        
        $txt .= "❓ *Perguntas*\n";
        $txt .= "8️⃣ Fazer pergunta sobre empreendimento";
    }

    return $txt . $this->footerControls();
}

/**
 * Detecta se é comando de resumo
 */
protected function isResumoCommand(string $norm): bool
{
    return Str::contains($norm, 'resumo');
}

/**
 * Monta texto de resumo (visão geral do sistema)
 */
protected function buildResumoText(WhatsappThread $thread): string
{
    $hasEmp = !empty($thread->selected_empreendimento_id);
    $empId = (int) $thread->selected_empreendimento_id;
    
    $txt = "📊 *Resumo do Sistema*\n\n";
    
    if ($hasEmp && $empId > 0) {
        $e = Empreendimento::find($empId);
        if ($e) {
            $txt .= "🏢 *Empreendimento Selecionado:*\n";
            $txt .= "• {$e->nome}\n";
            if ($e->cidade) $txt .= "• {$e->cidade}";
            if ($e->uf) $txt .= "/{$e->uf}";
            if ($e->cidade || $e->uf) $txt .= "\n";
            if ($e->endereco) $txt .= "• {$e->endereco}\n";
            $txt .= "\n";
        }
    } else {
        $txt .= "⚠️ *Nenhum empreendimento selecionado*\n\n";
    }
    
    $txt .= "📋 *Comandos Disponíveis:*\n\n";
    $txt .= "🔄 *Mudar empreendimento* - Ver lista de empreendimentos\n";
    $txt .= "➕ *Criar empreendimento* - Criar novo empreendimento de revenda\n";
    $txt .= "📋 *Menu* - Ver menu completo (requer empreendimento selecionado)\n\n";
    
    if ($hasEmp) {
        $txt .= "📁 *Ver arquivos* - Listar arquivos do empreendimento\n";
        $txt .= "🏢 *Unidades livres* - Consultar disponibilidade\n";
        $txt .= "💰 *Pagamento unidade X* - Ver informações de pagamento\n";
        $txt .= "📄 *Proposta unidade X* - Gerar PDF da proposta\n";
        $txt .= "📸 *Enviar fotos* - Adicionar mídias na galeria\n";
        $txt .= "❓ *Perguntas* - Fazer perguntas sobre o empreendimento\n\n";
        
        $txt .= "💡 Digite *menu* para ver todas as opções detalhadas.";
    } else {
        $txt .= "💡 Selecione um empreendimento para ver mais opções.\n";
        $txt .= "Digite *mudar empreendimento* para começar.";
    }
    
    return $txt . $this->footerControls();
}

/**
 * Limpa o texto de pagamento para uso em PDF:
 * - remove emojis (que viram "?" no DomPDF)
 * - normaliza quebras de linha
 * - remove cabeçalho duplicado "Informações de pagamento"
 */
protected function sanitizePaymentTextForPdf(string $txt): string
{
    // remove emojis (faixa básica de emojis Unicode)
    $txt = preg_replace('/[\x{1F000}-\x{1FAFF}]/u', '', $txt);

    // normaliza quebras de linha
    $txt = preg_replace("/\r\n|\r/", "\n", $txt);
    $txt = preg_replace("/\n{3,}/", "\n\n", $txt);

    // remove primeira linha se for só "Informações de pagamento"
    $lines = explode("\n", $txt);
    if (!empty($lines)) {
        $first = trim(mb_strtolower($lines[0]));
        if (str_contains($first, 'informações de pagamento') || str_contains($first, 'informacoes de pagamento')) {
            array_shift($lines);
            $txt = implode("\n", $lines);
        }
    }

    return trim($txt);
}


/**
 * Rota de debug para visualizar a proposta no browser.
 * Exemplo de acesso:
 * /debug/proposta/3/102/5
 */
public function debugProposta(int $emp, string $unidade, string $grupo)
{
    $empreendimento = \App\Models\Empreendimento::find($emp);

    if (!$empreendimento) {
        return "Empreendimento não encontrado.";
    }

    // 1) Mock / usuário logado como corretor
    $corretor = auth()->user() ?? (object)[
        'id'         => null,
        'name'       => 'Corretor Teste',
        'phone'      => '(62) 99999-9999',
        'company_id' => $empreendimento->company_id ?? null,
    ];

    // 2) Tenta descobrir a empresa (companies)
    $company = null;

    if (!empty($corretor->company_id)) {
        $company = \App\Models\Company::find($corretor->company_id);
    } elseif (!empty($empreendimento->company_id)) {
        // fallback: empresa ligada ao empreendimento, se existir
        $company = \App\Models\Company::find($empreendimento->company_id);
    }

    // 3) Monta dados da imobiliária com fallback seguro
    $imobiliariaNome = $company->name
        ?? $empreendimento->nome
        ?? 'Imobiliária';

    $imobiliariaSite = $company->site ?? null;
    $imobiliariaLogo = $company->logo_url ?? null; // ajuste se o campo tiver outro nome

    // 4) FAKE de texto de pagamento (só pra debug)
    $textoPagamento = "
Valor da unidade: R$ 707.920,22
Sinal 1 parcela: R$ 21.237,61
Sinal 3x: R$ 11.798,67
Mensais: 49x de R$ 866,84
Semestrais: 8x de R$ 11.503,70
Parcela única: R$ 35.396,01
Financiamento: R$ 481.385,75
";

    // sanitiza igual no PDF
    $textoPagamento = $this->sanitizePaymentTextForPdf($textoPagamento);

    return view('pdf.proposta_unidade', [
        'empreendimentoNome' => $empreendimento->nome,
        'unidade'            => $unidade,
        'torre'              => $grupo,
        'cidadeUf'           => $empreendimento->cidade_uf ?? 'GO',
        'textoPagamento'     => $textoPagamento,
        'hoje'               => now()->format('d/m/Y'),
        'imobiliariaNome'    => $imobiliariaNome,
        'imobiliariaSite'    => $imobiliariaSite,
        'imobiliariaLogo'    => $imobiliariaLogo,
        'corretorNome'       => $corretor->name,
        'corretorTelefone'   => $corretor->phone,
    ]);
}

/**
 * Lista todas as propostas de um empreendimento
 */
public function listarPropostas(int $empId)
{
    $empreendimento = \App\Models\Empreendimento::find($empId);
    
    if (!$empreendimento) {
        abort(404, 'Empreendimento não encontrado');
    }

    // Verifica permissão (mesmo middleware que outras rotas admin)
    abort_unless(auth()->check(), 403);

    $companyId = $empreendimento->company_id ?? auth()->user()->company_id ?? 1;
    $prefix = "documentos/tenants/{$companyId}/empreendimentos/{$empId}/propostas/";
    
    $disk = \Illuminate\Support\Facades\Storage::disk('s3');
    $files = $disk->files($prefix);
    
    // Filtra apenas PDFs
    $propostas = array_filter($files, function($path) {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    });
    
    // Ordena por data (mais recente primeiro) e formata
    $propostasFormatadas = [];
    foreach ($propostas as $path) {
        $fileName = basename($path);
        // Extrai informações do nome: proposta_{empId}_u{unidade}_{torre}_{timestamp}.pdf
        if (preg_match('/proposta_(\d+)_u(\d+)_(.+?)_(\d+)\.pdf$/', $fileName, $matches)) {
            $propostasFormatadas[] = [
                'path' => $path,
                'nome' => $fileName,
                'unidade' => $matches[2],
                'torre' => str_replace('_', ' ', $matches[3]),
                'timestamp' => (int)$matches[4],
                'data' => date('d/m/Y H:i', (int)$matches[4]),
            ];
        } else {
            // Fallback para arquivos com formato diferente
            $propostasFormatadas[] = [
                'path' => $path,
                'nome' => $fileName,
                'unidade' => '?',
                'torre' => '?',
                'timestamp' => filemtime($path) ?? time(),
                'data' => date('d/m/Y H:i'),
            ];
        }
    }
    
    // Ordena por timestamp (mais recente primeiro)
    usort($propostasFormatadas, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return view('admin.propostas.index', [
        'empreendimento' => $empreendimento,
        'propostas' => $propostasFormatadas,
    ]);
}

/**
 * Visualiza ou baixa uma proposta específica
 */
public function visualizarProposta(int $empId, string $fileName)
{
    $empreendimento = \App\Models\Empreendimento::find($empId);
    
    if (!$empreendimento) {
        abort(404, 'Empreendimento não encontrado');
    }

    // Verifica permissão
    abort_unless(auth()->check(), 403);

    $companyId = $empreendimento->company_id ?? auth()->user()->company_id ?? 1;
    $path = "documentos/tenants/{$companyId}/empreendimentos/{$empId}/propostas/{$fileName}";
    
    $disk = \Illuminate\Support\Facades\Storage::disk('s3');
    
    if (!$disk->exists($path)) {
        abort(404, 'Proposta não encontrada');
    }
    
    // Gera URL temporária (válida por 10 minutos)
    $url = $disk->temporaryUrl($path, now()->addMinutes(10));
    
    return redirect()->away($url);
}


//enviar fotos em grupo
protected function sendEmpreendimentoAssetsMenu($thread, int $empId, int $tenantId, string $empreendimentoNome, string $phone)
{
    // 2.1 Buscar arquivos “documentos” (sem fotos)
    $arquivos = Asset::where('empreend_id', $empId)
        ->where('tenant_id', $tenantId)
        ->where(function ($q) {
            // Tudo que NÃO for imagem entra como “arquivo normal”
            $q->whereNull('mime')
              ->orWhere('mime', 'not like', 'image/%');
        })
        ->orderBy('original_name')
        ->get();

    // 2.2 Ver se existem fotos do empreendimento
    $hasFotos = Asset::where('empreend_id', $empId)
        ->where('tenant_id', $tenantId)
        ->where('mime', 'like', 'image/%')
        ->exists();

    // 2.3 Montar a lista pro WhatsApp
    $linhas = [];
    $mapIndexToAssetId = [];
    $idx = 1;

    // 1) Arquivos “normais”
    foreach ($arquivos as $asset) {
        $nome = $asset->original_name ?: basename($asset->path);

        $linhas[] = "{$idx}. {$nome}";
        $mapIndexToAssetId[$idx] = $asset->id;
        $idx++;
    }

    // 2) Se tiver fotos, adiciona UMA opção única
    $indexFotos = null;
    if ($hasFotos) {
        $linhas[] = "{$idx}. 📷 Fotos do empreendimento";
        $indexFotos = $idx;
    }

    if (empty($linhas)) {
        $this->sendWhatsAppText($phone, "Ainda não encontrei arquivos cadastrados para esse empreendimento.");
        return;
    }

    $mensagem  = "Encontrei esses arquivos para o empreendimento {$empreendimentoNome}:\n\n";
    $mensagem .= implode("\n", $linhas);
    $mensagem .= "\n\nResponda com o número do item que você quer abrir.";

    // Aqui você guarda o mapeamento no thread (ajuste para o campo que você usa)
    // Estou assumindo uma coluna JSON chamada 'meta'. Se o nome for outro, ajusta aqui.
    $meta = $thread->meta ?? [];
    $meta['map_index_to_asset_id'] = $mapIndexToAssetId;
    $meta['index_fotos']           = $indexFotos;
    $meta['empreend_id']           = $empId;
    $meta['tenant_id']             = $tenantId;

    $thread->meta  = $meta;
    $thread->state = 'awaiting_asset_choice'; // importante pra próxima etapa
    $thread->save();

    $this->sendWhatsAppText($phone, $mensagem);
}

protected function handleEmpreendimentoAssetChoice($thread, string $text, string $phone)
{
    $numeroDigitado = (int) trim($text); // ex: "1", "2", "3"

    $meta = $thread->meta ?? [];

    $map      = $meta['map_index_to_asset_id'] ?? [];
    $indexFotos = $meta['index_fotos'] ?? null;
    $empId    = $meta['empreend_id'] ?? null;
    $tenantId = $meta['tenant_id'] ?? null;

    if (!$empId || !$tenantId) {
        $this->sendWhatsAppText($phone, "Não consegui localizar os arquivos desse empreendimento, pode tentar de novo digitando *ver arquivos*?");
        $thread->state = 'idle';
        $thread->save();
        return;
    }

    // 1) Se o número escolhido é a opção de FOTOS:
    if ($indexFotos && $numeroDigitado === (int) $indexFotos) {
        $urlFotos = route('empreendimentos.fotos', [
            'empreend' => $empId,
            'tenant'   => $tenantId,
        ]);

        $msg = "Vou te mandar o link com todas as fotos do empreendimento:\n"
             . "🔗 {$urlFotos}";

        $this->sendWhatsAppText($phone, $msg);

        // volta pra idle ou outro estado que você use
        $thread->state = 'idle';
        $thread->save();
        return;
    }

    // 2) Caso contrário, é algum arquivo normal
    if (!empty($map[$numeroDigitado])) {
        $assetId = $map[$numeroDigitado];

        $asset = Asset::where('id', $assetId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$asset) {
            $this->sendWhatsAppText($phone, "Não encontrei esse arquivo, pode tentar de novo?");
            return;
        }

        $url = Storage::disk('s3')->url($asset->path);

        $msg = "Segue o arquivo:\n{$url}";
        $this->sendWhatsAppText($phone, $msg);

        $thread->state = 'idle';
        $thread->save();
        return;
    }

    // Se cair aqui, número inválido
    $this->sendWhatsAppText($phone, "Opção inválida. Me manda o número do arquivo que você quer abrir.");
}

/**
 * Baixa uma mídia da Z-API e salva no S3,
 * registrando na tabela empreendimento_midias.
 */
private function saveEmpreendimentoMediaFromUrl(string $url, int $empreendimentoId, int $corretorId): string
{
    Log::info('WPP galeria: baixando mídia da Z-API', [
        'url'            => $url,
        'empreendimento' => $empreendimentoId,
        'corretor'       => $corretorId,
    ]);

    $resp = Http::timeout(40)->get($url);

    if (!$resp->successful()) {
        throw new \RuntimeException('Falha ao baixar mídia da Z-API: HTTP ' . $resp->status());
    }

    $binary = $resp->body();
    $mime   = $resp->header('Content-Type', 'application/octet-stream');

    $pathFromUrl = parse_url($url, PHP_URL_PATH) ?? '';
    $ext         = pathinfo($pathFromUrl, PATHINFO_EXTENSION);

    if ($ext === '' || strlen($ext) > 5) {
        if (str_starts_with($mime, 'image/')) {
            $ext = 'jpg';
        } elseif (str_starts_with($mime, 'video/')) {
            $ext = 'mp4';
        } else {
            $ext = 'bin';
        }
    }

    if (str_starts_with($mime, 'image/')) {
        $tipo = 'foto';
    } elseif (str_starts_with($mime, 'video/')) {
        $tipo = 'video';
    } else {
        $tipo = 'outro';
    }

    $fileName = 'wpp_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
    $pasta    = "midias/empreendimentos/{$empreendimentoId}/corretores/{$corretorId}/";
    $path     = $pasta . $fileName;

    Storage::disk('s3')->put($path, $binary);

    EmpreendimentoMidia::create([
        'empreendimento_id' => $empreendimentoId,
        'corretor_id'       => $corretorId,
        'arquivo_path'      => $path,
        'arquivo_tipo'      => $tipo,
    ]);

    $urlPublica = Storage::disk('s3')->url($path);

    Log::info('WPP galeria: mídia salva com sucesso', [
        'path'       => $path,
        'urlPublica' => $urlPublica,
        'mime'       => $mime,
        'tipo'       => $tipo,
    ]);

    return $urlPublica;
}


private function sendWppMessage(string $phone, string $text): void
{
    // só delega pro método que você já usa hoje
    $this->sendText($phone, $text);
}


}
