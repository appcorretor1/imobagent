<?php

namespace App\Services\Crm;

use App\DTOs\Crm\IntentDTO;
use App\Models\WhatsappThread;
use App\Models\User;
use App\Models\CrmLead;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmInteraction;
use App\Models\CrmAudit;
use App\Enums\CrmLeadStatus;
use App\Enums\CrmDealStatus;
use App\Enums\CrmActivityStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssistenteService
{
    public function __construct(
        private NlpParserInterface $parser,
        private CommandRouter $router,
    ) {}

    public function processar(WhatsappThread $thread, User $corretor, string $texto): string
    {
        $context = $thread->context ?? [];
        $intent = $this->parser->parse($texto, $context);

        // Tenta roteamento determinístico primeiro
        $resposta = $this->router->route($intent, $thread, $corretor);
        if ($resposta !== null) {
            return $resposta;
        }

        // Processa a intenção
        return match($intent->intent) {
            'menu' => $this->mostrarMenu(),
            'nova_visita' => $this->criarVisita($intent, $thread, $corretor),
            'listar_visitas' => $this->listarVisitas($intent, $thread, $corretor),
            'nova_proposta' => $this->criarProposta($intent, $thread, $corretor),
            'listar_propostas' => $this->listarPropostas($intent, $thread, $corretor),
            'listar_propostas_fechadas' => $this->listarPropostasFechadas($intent, $thread, $corretor),
            'fechar_venda' => $this->fecharVenda($intent, $thread, $corretor),
            'novo_followup' => $this->criarFollowup($intent, $thread, $corretor),
            'listar_followups' => $this->listarFollowups($intent, $thread, $corretor),
            'nova_anotacao' => $this->criarAnotacao($intent, $thread, $corretor),
            'listar_anotacoes' => $this->listarAnotacoes($intent, $thread, $corretor),
            'resumo' => $this->gerarResumo($intent, $thread, $corretor),
            default => $this->criarAnotacao($intent, $thread, $corretor), // Fallback: qualquer texto vira anotação
        };
    }

    private function criarVisita(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        try {
            DB::beginTransaction();

            // Se não tiver nome, tenta extrair do texto original
            $nome = $intent->getEntity('nome');
            if (!$nome) {
                // Tenta extrair nome do texto original como fallback
                if (preg_match('/com\s+([A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+)/iu', $intent->rawText ?? '', $matches)) {
                    $nome = trim($matches[1]);
                }
            }
            
            if (!$nome) {
                return "Para agendar uma visita, preciso do *nome do cliente*.\n\n" .
                       "Exemplo: *visita amanhã 15h com João no Paradizzo*";
            }

            $lead = $this->buscarOuCriarLead($intent, $thread, $corretor);
            
            $data = $intent->getEntity('data') 
                ? Carbon::parse($intent->getEntity('data'), 'America/Sao_Paulo')
                : Carbon::tomorrow('America/Sao_Paulo');
            
            $hora = $intent->getEntity('hora', '09:00');
            $data->setTimeFromTimeString($hora);

            $activity = CrmActivity::create([
                'company_id' => $corretor->company_id,
                'lead_id' => $lead->id,
                'corretor_id' => $corretor->id,
                'empreendimento_id' => $intent->getEntity('empreendimento_id') ?? $thread->selected_empreendimento_id,
                'tipo' => 'visita',
                'titulo' => "Visita com {$lead->nome}",
                'descricao' => $intent->rawText,
                'agendado_para' => $data,
                'status' => CrmActivityStatus::PENDENTE,
                'prioridade' => 'normal',
                'origem' => 'whatsapp',
            ]);

            $this->registrarInteraction($thread, $corretor, $lead, 'mensagem', $intent->rawText, 'saida');
            $this->auditar($activity, 'created', [], $activity->toArray(), 'whatsapp', $corretor->id);

            DB::commit();

            return "✅ *Visita agendada!*\n\n" .
                   "👤 Cliente: {$lead->nome}\n" .
                   "📅 Data: {$data->format('d/m/Y')}\n" .
                   "🕐 Horário: {$data->format('H:i')}\n\n" .
                   "Digite *listar visitas* para ver todas.";

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar visita', ['error' => $e->getMessage()]);
            return "❌ Erro ao agendar visita. Tente novamente.";
        }
    }

    private function listarVisitas(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        $query = CrmActivity::porCompany($corretor->company_id)
            ->porCorretor($corretor->id)
            ->where('tipo', 'visita')
            ->orderBy('agendado_para');

        // Filtro por data
        if ($intent->hasEntity('data')) {
            $data = Carbon::parse($intent->getEntity('data'), 'America/Sao_Paulo');
            $query->whereDate('agendado_para', $data);
        } elseif (str_contains($intent->rawText ?? '', 'hoje')) {
            $query->hoje();
        } elseif (str_contains($intent->rawText ?? '', 'semana')) {
            $query->estaSemana();
        }

        $visitas = $query->with('lead', 'empreendimento')->get();

        if ($visitas->isEmpty()) {
            return "📅 Nenhuma visita encontrada.";
        }

        $texto = "📅 *Visitas Agendadas* (" . $visitas->count() . ")\n\n";
        foreach ($visitas as $visita) {
            $dataFormatada = $visita->agendado_para->format('d/m/Y H:i');
            $cliente = $visita->lead->nome ?? 'Sem cliente';
            $emp = $visita->empreendimento->nome ?? '';
            $status = $visita->status->label();
            
            $texto .= "• {$dataFormatada} - {$cliente}";
            if ($emp) $texto .= " ({$emp})";
            $texto .= "\n   Status: {$status}\n\n";
        }

        return $texto;
    }

    private function criarProposta(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        try {
            DB::beginTransaction();

            $lead = $this->buscarOuCriarLead($intent, $thread, $corretor);
            
            $deal = CrmDeal::create([
                'company_id' => $corretor->company_id,
                'lead_id' => $lead->id,
                'corretor_id' => $corretor->id,
                'empreendimento_id' => $intent->getEntity('empreendimento_id') ?? $thread->selected_empreendimento_id,
                'unidade' => $intent->getEntity('unidade'),
                'torre' => $intent->getEntity('torre'),
                'tipo' => 'proposta',
                'status' => $intent->getEntity('status', 'aguardando_resposta') === 'aguardando_resposta' 
                    ? CrmDealStatus::AGUARDANDO_RESPOSTA 
                    : CrmDealStatus::ENVIADA,
                'valor' => $intent->getEntity('valor'),
                'observacoes' => $intent->rawText,
                'enviado_em' => Carbon::now('America/Sao_Paulo'),
                'origem' => 'whatsapp',
            ]);

            $lead->status = CrmLeadStatus::PROPOSTA_ENVIADA;
            $lead->save();

            $this->registrarInteraction($thread, $corretor, $lead, 'mensagem', $intent->rawText, 'saida', $deal);
            $this->auditar($deal, 'created', [], $deal->toArray(), 'whatsapp', $corretor->id);

            DB::commit();

            $valorFormatado = $deal->valor ? 'R$ ' . number_format($deal->valor, 2, ',', '.') : 'Não informado';
            return "✅ *Proposta registrada!*\n\n" .
                   "👤 Cliente: {$lead->nome}\n" .
                   "💰 Valor: {$valorFormatado}\n" .
                   "📊 Status: {$deal->status->label()}\n\n" .
                   "Digite *listar propostas* para ver todas.";

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar proposta', ['error' => $e->getMessage()]);
            return "❌ Erro ao registrar proposta. Tente novamente.";
        }
    }

    private function listarPropostas(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        $query = CrmDeal::porCompany($corretor->company_id)
            ->porCorretor($corretor->id)
            ->propostas()
            ->orderBy('created_at', 'desc');

        if (str_contains($intent->rawText ?? '', 'aguardando')) {
            $query->aguardandoResposta();
        }

        $propostas = $query->with('lead', 'empreendimento')->limit(10)->get();

        if ($propostas->isEmpty()) {
            return "📄 Nenhuma proposta encontrada.";
        }

        $texto = "📄 *Propostas* (" . $propostas->count() . ")\n\n";
        foreach ($propostas as $proposta) {
            $cliente = $proposta->lead->nome ?? 'Sem cliente';
            $valor = $proposta->valor ? 'R$ ' . number_format($proposta->valor, 2, ',', '.') : 'Não informado';
            $status = $proposta->status->label();
            $data = $proposta->created_at->format('d/m/Y');
            
            $texto .= "• {$cliente} - {$valor}\n";
            $texto .= "  Status: {$status} | {$data}\n\n";
        }

        return $texto;
    }

    private function listarPropostasFechadas(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        $propostas = CrmDeal::porCompany($corretor->company_id)
            ->porCorretor($corretor->id)
            ->propostas()
            ->where('status', CrmDealStatus::FECHADA->value)
            ->with('lead', 'empreendimento')
            ->orderBy('fechado_em', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($propostas->isEmpty()) {
            return "📄 Nenhuma proposta fechada encontrada.";
        }

        $total = CrmDeal::porCompany($corretor->company_id)
            ->porCorretor($corretor->id)
            ->propostas()
            ->where('status', CrmDealStatus::FECHADA->value)
            ->count();

        $texto = "✅ *Propostas Fechadas* (Total: {$total})\n\n";
        foreach ($propostas as $proposta) {
            $cliente = $proposta->lead->nome ?? 'Sem cliente';
            $valor = $proposta->valor ? 'R$ ' . number_format($proposta->valor, 2, ',', '.') : 'Não informado';
            $emp = $proposta->empreendimento_nome ?? ($proposta->empreendimento->nome ?? '');
            $data = $proposta->fechado_em ? $proposta->fechado_em->format('d/m/Y') : $proposta->created_at->format('d/m/Y');
            
            $texto .= "• {$cliente}\n";
            $texto .= "  💰 {$valor}";
            if ($emp) {
                $texto .= " | 🏢 {$emp}";
            }
            $texto .= "\n  📅 {$data}\n\n";
        }

        return $texto;
    }

    private function fecharVenda(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        try {
            DB::beginTransaction();

            $lead = $this->buscarOuCriarLead($intent, $thread, $corretor);
            
            // Extrai nome do empreendimento do texto se não foi extraído
            $empreendimentoNome = $intent->getEntity('empreendimento_nome');
            if (!$empreendimentoNome && $intent->rawText) {
                // Tenta extrair do texto original
                if (preg_match('/(?:empreendimento|no|em|na)\s+([A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+(?:\s+[A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+)*)/iu', $intent->rawText, $matches)) {
                    $empreendimentoNome = trim($matches[1]);
                    // Remove palavras comuns
                    $empreendimentoNome = preg_replace('/\b(no|em|na|do|da|dos|das|unidade|torre|cliente)\b/iu', '', $empreendimentoNome);
                    $empreendimentoNome = trim($empreendimentoNome);
                }
            }
            
            // Se ainda não tem nome, tenta buscar do empreendimento_id
            $empreendimentoId = $intent->getEntity('empreendimento_id') ?? $thread->selected_empreendimento_id;
            if (!$empreendimentoNome && $empreendimentoId) {
                $emp = \App\Models\Empreendimento::find($empreendimentoId);
                if ($emp) {
                    $empreendimentoNome = $emp->nome;
                }
            }
            
            // Normaliza o nome para comparação
            $empreendimentoNomeNormalizado = $empreendimentoNome 
                ? \App\Services\Crm\SimpleNlpParser::normalizarNomeEmpreendimento($empreendimentoNome)
                : null;
            
            // Extrai valor se não foi extraído
            $valor = $intent->getEntity('valor');
            if (!$valor && $intent->rawText) {
                // Tenta extrair do texto original
                if (preg_match('/(\d{1,3})\s*k\b/i', $intent->rawText, $matches)) {
                    $valor = (float)$matches[1] * 1000;
                } elseif (preg_match('/R\$\s*([\d.,]+)/', $intent->rawText, $matches)) {
                    $valorStr = str_replace(['.', ','], ['', '.'], $matches[1]);
                    $valor = (float)$valorStr;
                } elseif (preg_match('/(\d{4,})/', $intent->rawText, $matches)) {
                    $valor = (float)$matches[1];
                }
            }
            
            $deal = CrmDeal::create([
                'company_id' => $corretor->company_id,
                'lead_id' => $lead->id,
                'corretor_id' => $corretor->id,
                'empreendimento_id' => $empreendimentoId,
                'empreendimento_nome' => $empreendimentoNome,
                'empreendimento_nome_normalizado' => $empreendimentoNomeNormalizado,
                'unidade' => $intent->getEntity('unidade'),
                'torre' => $intent->getEntity('torre'),
                'tipo' => 'venda',
                'status' => CrmDealStatus::FECHADA,
                'valor' => $valor,
                'fechado_em' => Carbon::now('America/Sao_Paulo'),
                'origem' => 'whatsapp',
            ]);

            $lead->status = CrmLeadStatus::FECHADO;
            $lead->save();

            $this->registrarInteraction($thread, $corretor, $lead, 'mensagem', $intent->rawText, 'saida', $deal);
            $this->auditar($deal, 'created', [], $deal->toArray(), 'whatsapp', $corretor->id);

            DB::commit();

            $valorFormatado = $deal->valor ? 'R$ ' . number_format($deal->valor, 2, ',', '.') : 'Não informado';
            $empNome = $deal->empreendimento_nome ? "🏢 Empreendimento: {$deal->empreendimento_nome}\n" : "";
            return "🎉 *Venda fechada!*\n\n" .
                   "👤 Cliente: {$lead->nome}\n" .
                   "💰 Valor: {$valorFormatado}\n" .
                   ($empNome ?: "") .
                   "✅ Parabéns pela venda!";

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao fechar venda', ['error' => $e->getMessage()]);
            return "❌ Erro ao registrar venda. Tente novamente.";
        }
    }

    private function criarFollowup(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        try {
            DB::beginTransaction();

            $lead = $this->buscarOuCriarLead($intent, $thread, $corretor);
            
            $data = $intent->hasEntity('data')
                ? Carbon::parse($intent->getEntity('data'), 'America/Sao_Paulo')
                : Carbon::tomorrow('America/Sao_Paulo');

            $activity = CrmActivity::create([
                'company_id' => $corretor->company_id,
                'lead_id' => $lead->id,
                'corretor_id' => $corretor->id,
                'tipo' => 'follow_up',
                'titulo' => $intent->rawText ?? "Follow-up com {$lead->nome}",
                'descricao' => $intent->rawText,
                'agendado_para' => $data,
                'status' => CrmActivityStatus::PENDENTE,
                'prioridade' => 'normal',
                'origem' => 'whatsapp',
            ]);

            $this->registrarInteraction($thread, $corretor, $lead, 'nota', $intent->rawText, 'saida');
            $this->auditar($activity, 'created', [], $activity->toArray(), 'whatsapp', $corretor->id);

            DB::commit();

            return "✅ *Follow-up criado!*\n\n" .
                   "📅 Data: {$data->format('d/m/Y')}\n" .
                   "📝 Assunto: {$activity->titulo}\n\n" .
                   "Digite *minhas pendências* para ver todos.";

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar follow-up', ['error' => $e->getMessage()]);
            return "❌ Erro ao criar follow-up. Tente novamente.";
        }
    }

    private function listarFollowups(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        $query = CrmActivity::porCompany($corretor->company_id)
            ->porCorretor($corretor->id)
            ->pendentes()
            ->orderBy('agendado_para');

        $followups = $query->with('lead')->limit(10)->get();

        if ($followups->isEmpty()) {
            return "✅ Nenhuma pendência encontrada.";
        }

        $texto = "📋 *Pendências* (" . $followups->count() . ")\n\n";
        foreach ($followups as $followup) {
            $dataFormatada = $followup->agendado_para 
                ? $followup->agendado_para->format('d/m/Y')
                : 'Sem data';
            $titulo = $followup->titulo;
            
            $texto .= "• {$dataFormatada} - {$titulo}\n";
            if ($followup->isAtrasada()) {
                $texto .= "  ⚠️ Atrasada\n";
            }
            $texto .= "\n";
        }

        return $texto;
    }

    private function gerarResumo(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        $texto = strtolower($intent->rawText ?? '');
        $periodo = 'mes'; // padrão: mês
        
        // Detecta o período solicitado
        if (str_contains($texto, 'total') || str_contains($texto, 'completo') || str_contains($texto, 'tudo')) {
            $periodo = 'total';
        } elseif (str_contains($texto, 'semana')) {
            $periodo = 'semana';
        } elseif (str_contains($texto, 'mês') || str_contains($texto, 'mes') || str_contains($texto, 'mensal')) {
            $periodo = 'mes';
        }
        
        $inicio = null;
        $fim = null;
        $titulo = '';
        
        switch ($periodo) {
            case 'semana':
                $inicio = Carbon::now('America/Sao_Paulo')->startOfWeek();
                $fim = Carbon::now('America/Sao_Paulo')->endOfWeek();
                $titulo = 'Resumo da Semana';
                break;
            case 'mes':
                $inicio = Carbon::now('America/Sao_Paulo')->startOfMonth();
                $fim = Carbon::now('America/Sao_Paulo')->endOfMonth();
                $titulo = 'Resumo do Mês';
                break;
            case 'total':
                $inicio = null;
                $fim = null;
                $titulo = 'Resumo Total';
                break;
        }

        // Visitas
        $queryVisitas = CrmActivity::where('corretor_id', $corretor->id)
            ->where('tipo', 'visita');
        if ($inicio && $fim) {
            $queryVisitas->whereBetween('agendado_para', [$inicio, $fim]);
        }
        $visitas = $queryVisitas->count();

        // Propostas aguardando (sempre todas, não filtra por período)
        $propostasAguardando = CrmDeal::where('corretor_id', $corretor->id)
            ->propostas()
            ->aguardandoResposta()
            ->count();
        
        // Propostas fechadas (inclui propostas fechadas E vendas fechadas)
        $queryPropostasFechadas = CrmDeal::where('corretor_id', $corretor->id)
            ->where('status', CrmDealStatus::FECHADA->value)
            ->where(function($query) {
                // Propostas fechadas (tipo = proposta, status = FECHADA)
                $query->where('tipo', 'proposta')
                // OU vendas fechadas (tipo = venda, status = FECHADA)
                  ->orWhere('tipo', 'venda');
            });
        if ($inicio && $fim) {
            $queryPropostasFechadas->where(function($q) use ($inicio, $fim) {
                $q->whereBetween('fechado_em', [$inicio, $fim])
                  ->orWhereBetween('created_at', [$inicio, $fim]);
            });
        }
        $propostasFechadas = $queryPropostasFechadas->count();

        // Vendas
        $queryVendas = CrmDeal::where('corretor_id', $corretor->id)
            ->vendas()
            ->fechadas();
        if ($inicio && $fim) {
            $queryVendas->where(function($q) use ($inicio, $fim) {
                $q->whereBetween('fechado_em', [$inicio, $fim])
                  ->orWhereBetween('created_at', [$inicio, $fim]);
            });
        }
        $vendas = $queryVendas->sum('valor') ?? 0;

        // Pendências (sempre todas)
        $pendencias = CrmActivity::where('corretor_id', $corretor->id)
            ->pendentes()
            ->count();

        $periodoTexto = $periodo === 'total' ? ' (todos os tempos)' : '';
        
        return "📊 *{$titulo}{$periodoTexto}*\n\n" .
               "📅 Visitas: {$visitas}\n" .
               "📄 Propostas aguardando: {$propostasAguardando}\n" .
               "✅ Propostas fechadas: {$propostasFechadas}\n" .
               "💰 Vendas: R$ " . number_format($vendas, 2, ',', '.') . "\n" .
               "📋 Pendências: {$pendencias}";
    }

    private function buscarOuCriarLead(IntentDTO $intent, WhatsappThread $thread, User $corretor): CrmLead
    {
        $nome = $intent->getEntity('nome');
        
        // Fallback: tenta extrair do texto original
        if (!$nome && $intent->rawText) {
            if (preg_match('/com\s+([A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+)/iu', $intent->rawText, $matches)) {
                $nome = trim($matches[1]);
            }
        }
        
        if (!$nome) {
            throw new \Exception('Nome do cliente é obrigatório');
        }

        // Tenta buscar lead existente
        $lead = CrmLead::porCompany($corretor->company_id)
            ->porCorretor($corretor->id)
            ->where('nome', 'like', "%{$nome}%")
            ->first();

        if (!$lead) {
            $lead = CrmLead::create([
                'company_id' => $corretor->company_id,
                'corretor_id' => $corretor->id,
                'nome' => $nome,
                'phone' => $intent->getEntity('phone'),
                'whatsapp' => $intent->getEntity('whatsapp'),
                'status' => CrmLeadStatus::NOVO,
            ]);

            $this->auditar($lead, 'created', [], $lead->toArray(), 'whatsapp', $corretor->id);
        }

        // Atualiza último contato
        $lead->atualizarUltimoContato();

        // Salva no contexto
        $context = $thread->context ?? [];
        $context['crm_last_lead'] = $lead->id;
        $thread->context = $context;
        $thread->save();

        return $lead;
    }

    private function registrarInteraction(
        WhatsappThread $thread,
        User $corretor,
        ?CrmLead $lead = null,
        string $tipo = 'mensagem',
        string $conteudo = '',
        string $direcao = 'saida',
        ?CrmDeal $deal = null
    ): void {
        CrmInteraction::create([
            'company_id' => $corretor->company_id,
            'lead_id' => $lead?->id,
            'corretor_id' => $corretor->id,
            'deal_id' => $deal?->id,
            'tipo' => $tipo,
            'direcao' => $direcao,
            'conteudo' => $conteudo,
            'origem' => 'whatsapp',
            'ocorrido_em' => Carbon::now('America/Sao_Paulo'),
        ]);
    }

    private function mostrarMenu(): string
    {
        return "📋 *Menu do Assistente*\n\n" .
               "1️⃣ *Visitas*\n" .
               "   • Agendar: *visita amanhã 15h com João*\n" .
               "   • Listar: *listar visitas hoje*\n\n" .
               "2️⃣ *Propostas*\n" .
               "   • Nova: *proposta 520k para Maria*\n" .
               "   • Listar: *propostas aguardando*\n" .
               "   • Fechadas: *propostas fechadas*\n\n" .
               "3️⃣ *Vendas*\n" .
               "   • Fechar: *venda fechada 480k cliente Ana*\n\n" .
               "4️⃣ *Follow-ups*\n" .
               "   • Criar: *lembrar de ligar para João*\n" .
               "   • Listar: *minhas pendências*\n\n" .
               "5️⃣ *Anotações*\n" .
               "   • Criar: *anotar qualquer texto aqui*\n" .
               "   • Listar: *listar anotações*\n\n" .
               "6️⃣ *Resumo*\n" .
               "   • *resumo do mês*\n" .
               "   • *resumo total*\n\n" .
               "Digite *sair* para voltar ao menu principal.";
    }

    private function criarAnotacao(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        try {
            DB::beginTransaction();

            $conteudo = trim($intent->rawText ?? '');
            
            // Remove comandos de anotação do início
            $conteudo = preg_replace('/^(anotar|anotação|anotacao|nota|salvar|guardar)\s*/i', '', $conteudo);
            $conteudo = trim($conteudo);

            if (empty($conteudo)) {
                return "📝 Para criar uma anotação, digite o texto que deseja salvar.\n\n" .
                       "Exemplo: *anotar Cliente João interessado em unidade 203*";
            }

            $leadId = null;
            $empreendimentoId = $thread->selected_empreendimento_id;

            // Tenta encontrar lead pelo nome se mencionado
            $nome = $intent->getEntity('nome');
            if ($nome) {
                $lead = CrmLead::porCompany($corretor->company_id)
                    ->porCorretor($corretor->id)
                    ->where('nome', 'like', "%{$nome}%")
                    ->first();
                if ($lead) {
                    $leadId = $lead->id;
                }
            }

            $note = \App\Models\CrmNote::create([
                'company_id' => $corretor->company_id,
                'corretor_id' => $corretor->id,
                'lead_id' => $leadId,
                'empreendimento_id' => $empreendimentoId,
                'conteudo' => $conteudo,
                'origem' => 'whatsapp',
            ]);

            if ($leadId) {
                $lead = CrmLead::find($leadId);
                $this->registrarInteraction($thread, $corretor, $lead, 'mensagem', $conteudo, 'saida');
            }
            
            DB::commit();

            return "✅ *Anotação salva!*\n\n" .
                   "📝 {$conteudo}\n\n" .
                   "Digite *listar anotações* para ver todas.";

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar anotação', ['error' => $e->getMessage()]);
            return "❌ Erro ao salvar anotação. Tente novamente.";
        }
    }

    private function listarAnotacoes(IntentDTO $intent, WhatsappThread $thread, User $corretor): string
    {
        $notes = \App\Models\CrmNote::porCompany($corretor->company_id)
            ->porCorretor($corretor->id)
            ->recentes(10)
            ->with(['lead', 'empreendimento'])
            ->get();

        if ($notes->isEmpty()) {
            return "📝 Você ainda não tem anotações.\n\n" .
                   "Digite qualquer texto para criar uma anotação, ou use *anotar [texto]*.";
        }

        $texto = "📝 *Suas Anotações* (últimas 10)\n\n";
        foreach ($notes as $index => $note) {
            $data = $note->created_at->format('d/m/Y H:i');
            $lead = $note->lead ? "👤 {$note->lead->nome} - " : "";
            $emp = $note->empreendimento ? "🏢 {$note->empreendimento->nome} - " : "";
            $texto .= ($index + 1) . ". {$lead}{$emp}{$data}\n";
            $texto .= "   {$note->conteudo}\n\n";
        }

        return $texto;
    }

    private function auditar($model, string $action, array $oldValues, array $newValues, string $origem, ?int $userId): void
    {
        CrmAudit::create([
            'company_id' => $model->company_id,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'user_id' => $userId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'origem' => $origem,
        ]);
    }
}
