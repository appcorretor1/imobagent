<?php

namespace App\Services\Crm;

use App\DTOs\Crm\IntentDTO;
use App\Models\WhatsappThread;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Roteador de comandos determinísticos (fallback quando NLP não funciona)
 */
class CommandRouter
{
    public function route(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        $handler = match($intent->intent) {
            'nova_visita' => fn() => $this->handleNovaVisita($intent, $thread, $corretor),
            'listar_visitas' => fn() => $this->handleListarVisitas($intent, $thread, $corretor),
            'nova_proposta' => fn() => $this->handleNovaProposta($intent, $thread, $corretor),
            'listar_propostas' => fn() => $this->handleListarPropostas($intent, $thread, $corretor),
            'listar_propostas_fechadas' => fn() => $this->handleListarPropostasFechadas($intent, $thread, $corretor),
            'fechar_venda' => fn() => $this->handleFecharVenda($intent, $thread, $corretor),
            'novo_followup' => fn() => $this->handleNovoFollowup($intent, $thread, $corretor),
            'listar_followups' => fn() => $this->handleListarFollowups($intent, $thread, $corretor),
            'nova_anotacao' => fn() => $this->handleNovaAnotacao($intent, $thread, $corretor),
            'listar_anotacoes' => fn() => $this->handleListarAnotacoes($intent, $thread, $corretor),
            'resumo' => fn() => $this->handleResumo($intent, $thread, $corretor),
            'menu' => fn() => $this->handleMenu(),
            'sair' => fn() => $this->handleSair($thread),
            default => null,
        };

        if ($handler === null) {
            return null;
        }

        $result = $handler();
        return $result;
    }

    private function handleNovaVisita(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        // Se tiver pelo menos nome OU data, deixa o AssistenteService processar
        // O AssistenteService tem fallbacks (amanhã se não tiver data, 09:00 se não tiver hora)
        if ($intent->hasEntity('nome') || $intent->hasEntity('data')) {
            // Tudo ok, retorna null para o AssistenteService processar
            return null;
        }

        // Se não tiver nada, pede informações
        return "Para agendar uma visita, preciso de:\n" .
               "• Nome do cliente\n" .
               "• Data e horário (opcional, uso amanhã 09:00 se não informar)\n\n" .
               "Exemplo: *visita amanhã 15h com João no Paradizzo*";
    }

    private function handleListarVisitas(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        // Retorna null para o AssistenteService processar
        return null;
    }

    private function handleNovaProposta(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        if (!$intent->hasEntity('nome') || !$intent->hasEntity('valor')) {
            return "Para registrar uma proposta, preciso de:\n" .
                   "• Nome do cliente\n" .
                   "• Valor da proposta\n\n" .
                   "Exemplo: *proposta 520k para Maria, aguardando*";
        }

        return null;
    }

    private function handleListarPropostas(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        return null;
    }

    private function handleListarPropostasFechadas(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        return null;
    }

    private function handleFecharVenda(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        // Tenta extrair nome e valor do texto original se não foram extraídos
        $nome = $intent->getEntity('nome');
        $valor = $intent->getEntity('valor');
        
        // Se não tem nome, tenta extrair do texto
        if (!$nome && $intent->rawText) {
            if (preg_match('/cliente\s+([A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+)/iu', $intent->rawText, $matches)) {
                $nome = trim($matches[1]);
            }
        }
        
        // Se não tem valor, tenta extrair do texto
        if (!$valor && $intent->rawText) {
            if (preg_match('/(\d{1,3})\s*k\b/i', $intent->rawText, $matches)) {
                $valor = (float)$matches[1] * 1000;
            }
        }
        
        if (!$nome || !$valor) {
            return "Para fechar uma venda, preciso de:\n" .
                   "• Nome do cliente\n" .
                   "• Valor da venda\n\n" .
                   "Exemplo: *venda fechada 480k cliente Ana, unidade 203*";
        }

        return null;
    }

    private function handleNovoFollowup(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        if (!$intent->hasEntity('nome') && !$intent->hasEntity('data')) {
            return "Para criar um follow-up, preciso de:\n" .
                   "• Nome do cliente ou assunto\n" .
                   "• Data (opcional)\n\n" .
                   "Exemplo: *lembrar de ligar para João amanhã*";
        }

        return null;
    }

    private function handleListarFollowups(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        return null;
    }

    private function handleResumo(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        return null;
    }

    private function handleNovaAnotacao(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        // Sempre retorna null para o AssistenteService processar
        return null;
    }

    private function handleListarAnotacoes(IntentDTO $intent, WhatsappThread $thread, User $corretor): ?string
    {
        return null;
    }

    private function handleMenu(): string
    {
        return "📋 *Menu do Assistente*\n\n" .
               "1️⃣ *Visitas*\n" .
               "   • Agendar: *visita amanhã 15h com João*\n" .
               "   • Listar: *listar visitas hoje*\n\n" .
               "2️⃣ *Propostas*\n" .
               "   • Nova: *proposta 520k para Maria*\n" .
               "   • Listar: *propostas aguardando*\n\n" .
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

    private function handleSair(WhatsappThread $thread): string
    {
        $context = $thread->context ?? [];
        unset($context['crm_mode'], $context['crm_last_lead'], $context['crm_last_deal']);
        $thread->context = $context;
        $thread->state = 'idle';
        $thread->save();

        return "✅ Saiu do assistente. Digite *menu* para ver opções principais.";
    }
}
