<?php

namespace App\Services\Crm;

use App\DTOs\Crm\IntentDTO;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Parser simples baseado em regex e palavras-chave
 * Pode ser substituído por um parser com IA no futuro
 */
class SimpleNlpParser implements NlpParserInterface
{
    private const INTENTS = [
        'menu' => ['assistente', 'menu', 'ajuda', 'comandos', 'opções', 'crm', 'pipeline'],
        'nova_visita' => ['visita', 'agendar visita', 'marcar visita', 'visita com', 'visita para'],
        'listar_visitas' => ['listar visitas', 'visitas hoje', 'visitas amanhã', 'minhas visitas', 'visitas da semana'],
        'nova_proposta' => ['proposta', 'enviar proposta', 'registrar proposta', 'criar proposta', 'proposta para'],
        'listar_propostas' => ['listar propostas', 'propostas aguardando', 'propostas pendentes', 'minhas propostas'],
        'listar_propostas_fechadas' => ['propostas fechadas', 'propostas aceitas', 'propostas aprovadas', 'quantas propostas fechadas', 'quantas propostas aceitas'],
        'fechar_venda' => ['venda fechada', 'fechar venda', 'venda para', 'vender para', 'venda de'],
        'novo_followup' => ['follow up', 'follow-up', 'lembrar', 'lembrete', 'tarefa', 'lembrar de'],
        'listar_followups' => ['follow ups', 'follow-ups', 'lembretes', 'tarefas', 'pendências', 'minhas pendências'],
        'nova_anotacao' => ['anotar', 'anotação', 'anotacao', 'nota', 'salvar', 'guardar'],
        'listar_anotacoes' => ['listar anotações', 'listar anotacoes', 'listar anotacao', 'minhas anotações', 'minhas anotacoes', 'ver anotações', 'ver anotacoes', 'mostrar anotações', 'mostrar anotacoes'],
        'resumo' => ['resumo', 'resumo da semana', 'resumo do mês', 'resumo do mes', 'resumo total', 'resumo completo', 'me mostra o resumo', 'mostra o resumo', 'dashboard', 'kpis', 'estatísticas', 'estatisticas'],
        'status_lead' => ['status', 'atualizar status', 'mudar status'],
        'sair' => ['sair', 'voltar', 'menu principal', 'sair do assistente'],
    ];

    public function parse(string $text, array $context = []): IntentDTO
    {
        // Mantém o texto original para extração de entidades (precisa de maiúsculas para nomes)
        $textOriginal = trim($text);
        $text = Str::lower(trim($text));
        $entities = [];
        $intent = 'desconhecido';
        $confidence = 0.0;

        // Detecta intenção
        // Ordem importa: comandos de listagem primeiro, depois navegação, depois ações
        $priorityIntents = ['listar_visitas', 'listar_propostas', 'listar_followups', 'listar_anotacoes', 'menu', 'sair'];
        foreach ($priorityIntents as $priorityIntent) {
            if (isset(self::INTENTS[$priorityIntent])) {
                foreach (self::INTENTS[$priorityIntent] as $pattern) {
                    // Normaliza o padrão também para comparação (remove acentos se necessário)
                    $patternLower = Str::lower($pattern);
                    if (Str::contains($text, $patternLower)) {
                        $intent = $priorityIntent;
                        $confidence = 0.9;
                        break 2;
                    }
                }
            }
        }
        
        // Se não encontrou intent prioritário, procura nos demais
        if ($intent === 'desconhecido') {
            foreach (self::INTENTS as $intentKey => $patterns) {
                if (in_array($intentKey, $priorityIntents)) {
                    continue; // Já processou
                }
                foreach ($patterns as $pattern) {
                    // Normaliza o padrão também para comparação
                    $patternLower = Str::lower($pattern);
                    if (Str::contains($text, $patternLower)) {
                        $intent = $intentKey;
                        $confidence = 0.8;
                        break 2;
                    }
                }
            }
        }

        // Extrai entidades (ordem importa: data e hora primeiro, depois nome)
        // Usa texto original para extrair nome (precisa de maiúsculas)
        $entities = [];
        $entities = array_merge($entities, $this->extractDate($text));
        $entities = array_merge($entities, $this->extractTime($text));
        $entities = array_merge($entities, $this->extractName($textOriginal)); // Usa original para nomes
        $entities = array_merge($entities, $this->extractValue($text));
        $entities = array_merge($entities, $this->extractEmpreendimento($text, $context));
        $entities = array_merge($entities, $this->extractUnidade($text));
        $entities = array_merge($entities, $this->extractStatus($text));

        return new IntentDTO($intent, $entities, $confidence, $textOriginal);
    }

    private function extractDate(string $text): array
    {
        $entities = [];
        $today = Carbon::today('America/Sao_Paulo');
        
        // Hoje
        if (preg_match('/\bhoje\b/i', $text)) {
            $entities['data'] = $today->format('Y-m-d');
            return $entities;
        }

        // Amanhã
        if (preg_match('/\bamanh[ãa]\b/i', $text)) {
            $entities['data'] = $today->copy()->addDay()->format('Y-m-d');
            return $entities;
        }

        // Dias da semana
        $diasSemana = [
            'segunda' => 1, 'terça' => 2, 'terca' => 2, 'quarta' => 3,
            'quinta' => 4, 'sexta' => 5, 'sábado' => 6, 'sabado' => 6, 'domingo' => 0
        ];
        foreach ($diasSemana as $dia => $diaNum) {
            if (preg_match("/\b{$dia}\b/i", $text)) {
                $diff = ($diaNum - $today->dayOfWeek + 7) % 7;
                if ($diff === 0) $diff = 7; // Próxima semana
                $entities['data'] = $today->copy()->addDays($diff)->format('Y-m-d');
                return $entities;
            }
        }

        // Data no formato DD/MM ou DD/MM/YYYY
        if (preg_match('/(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?/', $text, $matches)) {
            $dia = (int)$matches[1];
            $mes = (int)$matches[2];
            $ano = isset($matches[3]) ? (int)$matches[3] : $today->year;
            
            if ($ano === $today->year && $mes < $today->month) {
                $ano++;
            }
            
            try {
                $data = Carbon::create($ano, $mes, $dia, 0, 0, 0, 'America/Sao_Paulo');
                $entities['data'] = $data->format('Y-m-d');
            } catch (\Exception $e) {
                // Data inválida, ignora
            }
        }

        return $entities;
    }

    private function extractTime(string $text): array
    {
        $entities = [];
        
        // Formato HH:MM
        if (preg_match('/(\d{1,2}):(\d{2})/', $text, $matches)) {
            $entities['hora'] = sprintf('%02d:%02d', $matches[1], $matches[2]);
            return $entities;
        }

        // Formato Xh ou X horas (com ou sem espaço)
        if (preg_match('/(\d{1,2})\s*h(?:oras?)?/i', $text, $matches)) {
            $hora = (int)$matches[1];
            if ($hora >= 0 && $hora <= 23) {
                $entities['hora'] = sprintf('%02d:00', $hora);
                return $entities;
            }
        }
        
        // Formato Xh sem espaço (ex: "15h")
        if (preg_match('/(\d{1,2})h(?!\w)/i', $text, $matches)) {
            $hora = (int)$matches[1];
            if ($hora >= 0 && $hora <= 23) {
                $entities['hora'] = sprintf('%02d:00', $hora);
                return $entities;
            }
        }

        return $entities;
    }

    private function extractName(string $text): array
    {
        $entities = [];
        
        // Padrões comuns: "com João", "para Maria", "cliente Ana"
        // Tenta primeiro padrões com preposição "com" ou "para"
        // Regex melhorado: captura nome após "com", "para", "cliente" até encontrar "no", "em", "empreendimento", "unidade" ou fim da string
        if (preg_match('/(?:com|para|cliente|lead)\s+([A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+(?:\s+[A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+)*?)(?:\s+(?:no|em|na|do|da|dos|das|empreendimento|unidade)\s+|\s*$|,)/iu', $text, $matches)) {
            $nome = trim($matches[1]);
            // Remove palavras comuns que não são nomes
            $nome = preg_replace('/\b(no|em|na|do|da|dos|das|Paradizzo|jardins|berlin)\b/iu', '', $nome);
            $nome = trim($nome);
            if (strlen($nome) >= 2) {
                $entities['nome'] = $nome;
                return $entities;
            }
        }
        
        // Fallback: procura por "com" seguido de nome próprio simples
        if (preg_match('/com\s+([A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+)/iu', $text, $matches)) {
            $nome = trim($matches[1]);
            // Remove palavras que não são nomes
            $excluir = ['Paradizzo', 'Amanhã', 'Hoje', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo', 'Visita'];
            if (!in_array($nome, $excluir) && strlen($nome) >= 2) {
                $entities['nome'] = $nome;
                return $entities;
            }
        }

        return $entities;
    }

    private function extractValue(string $text): array
    {
        $entities = [];
        
        // Valores em R$ ou números grandes
        if (preg_match('/R\$\s*([\d.,]+)/', $text, $matches)) {
            $valor = str_replace(['.', ','], ['', '.'], $matches[1]);
            $entities['valor'] = (float)$valor;
        } elseif (preg_match('/(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*(?:mil|k)/i', $text, $matches)) {
            $valor = str_replace(['.', ','], ['', '.'], $matches[1]);
            $entities['valor'] = (float)$valor * 1000;
        } elseif (preg_match('/(\d{1,3})\s*k\b/i', $text, $matches)) {
            // Formato "480k" sem ponto
            $entities['valor'] = (float)$matches[1] * 1000;
        } elseif (preg_match('/(\d{4,})/', $text, $matches)) {
            // Número grande pode ser valor
            $entities['valor'] = (float)$matches[1];
        }

        return $entities;
    }

    private function extractEmpreendimento(string $text, array $context): array
    {
        $entities = [];
        
        // Tenta pegar do contexto primeiro
        if (isset($context['selected_empreendimento_id'])) {
            $entities['empreendimento_id'] = (int)$context['selected_empreendimento_id'];
        }

        // Extrai nome do empreendimento do texto
        // Padrões: "empreendimento X", "no X", "em X", "jardins berlin", etc
        if (preg_match('/(?:empreendimento|no|em|na)\s+([A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+(?:\s+[A-ZÁÉÍÓÚÂÊÔÇ][a-záéíóúâêôçãõ]+)*)/iu', $text, $matches)) {
            $nome = trim($matches[1]);
            // Remove palavras comuns que não são nomes
            $nome = preg_replace('/\b(no|em|na|do|da|dos|das|unidade|torre|cliente)\b/iu', '', $nome);
            $nome = trim($nome);
            if (strlen($nome) >= 3) {
                $entities['empreendimento_nome'] = $nome;
            }
        }

        return $entities;
    }
    
    /**
     * Normaliza nome de empreendimento para comparação (remove acentos, espaços, etc)
     */
    public static function normalizarNomeEmpreendimento(string $nome): string
    {
        $normalizado = mb_strtolower(trim($nome));
        
        // Remove acentos
        $normalizado = str_replace(
            ['á', 'à', 'ã', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'õ', 'ô', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ'],
            ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'],
            $normalizado
        );
        
        // Remove espaços extras e caracteres especiais
        $normalizado = preg_replace('/[^a-z0-9\s]/', '', $normalizado);
        $normalizado = preg_replace('/\s+/', ' ', $normalizado);
        $normalizado = trim($normalizado);
        
        return $normalizado;
    }

    private function extractUnidade(string $text): array
    {
        $entities = [];
        
        // Padrão: "unidade 103", "apto 205", "103", "torre 5"
        if (preg_match('/(?:unidade|apto|apartamento|apt)\s*(\d+)/i', $text, $matches)) {
            $entities['unidade'] = $matches[1];
        } elseif (preg_match('/\b(\d{3,4})\b/', $text, $matches)) {
            // Número de 3-4 dígitos pode ser unidade
            $entities['unidade'] = $matches[1];
        }

        if (preg_match('/torre\s*(\d+|[A-Z])/i', $text, $matches)) {
            $entities['torre'] = $matches[1];
        }

        return $entities;
    }

    private function extractStatus(string $text): array
    {
        $entities = [];
        
        $statusMap = [
            'aguardando' => 'aguardando_resposta',
            'pendente' => 'pendente',
            'fechado' => 'fechada',
            'aceito' => 'aceita',
            'recusado' => 'recusada',
        ];

        foreach ($statusMap as $palavra => $status) {
            if (Str::contains($text, $palavra)) {
                $entities['status'] = $status;
                break;
            }
        }

        return $entities;
    }
}
