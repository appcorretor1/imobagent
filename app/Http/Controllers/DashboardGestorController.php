<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class DashboardGestorController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();

        // Multi-tenant: pega company_id do usuário logado
        $companyId = optional($user)->company_id ?? null;

        // Se for corretor, guardamos o id dele para filtrar tudo
        $corretorId = ($user && $user->role === 'corretor') ? $user->id : null;

        // Período padrão (últimos 14 dias)
        $days = (int) $r->input('days', 14);
        $dateFrom = Carbon::now()->subDays($days)->startOfDay();
        $dateTo   = Carbon::now()->endOfDay();

        // Cache para não sobrecarregar (inclui corretorId pra separar visão)
        $cacheKey = "dashboard_gestor:company:{$companyId}:corretor:{$corretorId}:days:{$days}";

        $data = Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            fn () => $this->buildDashboardData($companyId, $dateFrom, $dateTo, $corretorId)
        );

        return view('admin.dashboard', $data + [
            'days' => $days,
        ]);
    }

    /**
     * Exporta o dashboard em PDF para o diretor da imobiliária.
     * (usa a mesma base de dados do dashboard normal)
     */
    public function exportPdf(Request $r)
    {
        $user = $r->user();
        $companyId = optional($user)->company_id ?? null;
        $corretorId = ($user && $user->role === 'corretor') ? $user->id : null;

        $days = (int) $r->input('days', 14);
        $dateFrom = Carbon::now()->subDays($days)->startOfDay();
        $dateTo   = Carbon::now()->endOfDay();

        $cacheKey = "dashboard_gestor:company:{$companyId}:corretor:{$corretorId}:days:{$days}";

        $data = Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            fn () => $this->buildDashboardData($companyId, $dateFrom, $dateTo, $corretorId)
        );

        $viewData = $data + [
            'days' => $days,
        ];

        $pdf = Pdf::loadView('admin.dashboard-pdf', $viewData);

        $fileName = 'relatorio-ia-imobiliaria_' .
            ($viewData['dateFrom'] ?? $dateFrom->toDateString()) . '_' .
            ($viewData['dateTo'] ?? $dateTo->toDateString()) . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Função central que monta todos os dados do dashboard (para tela e para PDF).
     */
    protected function buildDashboardData(
        ?int $companyId,
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $corretorId = null
    ): array {
        $hasThreads      = Schema::hasTable('whatsapp_threads');
        $hasMessages     = Schema::hasTable('whatsapp_messages');
        $hasCorretorCol  = $hasThreads && Schema::hasColumn('whatsapp_threads', 'corretor_id');
        $isCorretor      = !is_null($corretorId);

        // 🔹 SE A TABELA whatsapp_messages EXISTE MAS NÃO TEM DADOS NO PERÍODO,
        //     FORÇAMOS A USAR APENAS THREADS (COMPORTAMENTO ANTIGO)
        if ($hasMessages && $hasThreads) {
            $hasMessagesInRange = DB::table('whatsapp_messages as m')
                ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('t.corretor_id', $corretorId))
                ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                ->exists();

            if (!$hasMessagesInRange) {
                $hasMessages = false;
            }
        }

        // Verifica colunas de latência
        $hasLatencyMs  = $hasMessages && Schema::hasColumn('whatsapp_messages', 'latency_ms');
        $hasLatencySec = $hasMessages && Schema::hasColumn('whatsapp_messages', 'latency_seconds');

        // ----- Timeline: total de conversas por dia -----
        $timeline = collect();
        if ($hasMessages) {
            $q = DB::table('whatsapp_messages as m')
                ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('t.corretor_id', $corretorId))
                ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(m.created_at) as d, COUNT(*) as total')
                ->groupBy('d')
                ->orderBy('d');

            $timeline = collect($q->get());
        } elseif ($hasThreads) {
            $q = DB::table('whatsapp_threads as t')
                ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('t.corretor_id', $corretorId))
                ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(t.created_at) as d, COUNT(*) as total')
                ->groupBy('d')
                ->orderBy('d');

            $timeline = collect($q->get());
        }

        // =====================================================================
        //  Empreendimentos mais falados
        //  Regra: conta respostas da IA que possuem emp_id no meta (JSON string)
        // =====================================================================
        $topEmp = collect();

        if ($hasMessages) {
            // Subquery incluindo company_id e corretor_id (via threads)
            $topEmp = DB::table(DB::raw("
                (
                    SELECT
                        m.id,
                        m.sender,
                        m.created_at,
                        t.company_id,
                        " . ($hasCorretorCol ? "t.corretor_id," : "NULL as corretor_id,") . "
                        CAST(
                            JSON_UNQUOTE(
                                JSON_EXTRACT(
                                    JSON_UNQUOTE(m.meta),
                                    '$.emp_id'
                                )
                            ) AS UNSIGNED
                        ) AS emp_id
                    FROM whatsapp_messages m
                    JOIN whatsapp_threads t ON t.id = m.thread_id
                ) as m
            "))
                ->leftJoin('empreendimentos as e', 'e.id', '=', 'm.emp_id')
                ->where('m.sender', 'ia') // só respostas da IA
                ->when($companyId, fn ($qq) => $qq->where('m.company_id', $companyId))
                ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('m.corretor_id', $corretorId))
                ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                ->whereNotNull('m.emp_id')
                ->selectRaw("
                    m.emp_id as empreendimento_id,
                    COALESCE(e.nome, CONCAT('Empreendimento #', m.emp_id)) as nome,
                    COUNT(*) as total
                ")
                ->groupBy('m.emp_id', 'e.nome')
                ->orderByDesc('total')
                ->limit(5) // TOP 5
                ->get();

        } elseif ($hasThreads) {
            // Fallback antigo baseado em whatsapp_threads, se não tiver messages
            $topEmp = DB::table('whatsapp_threads as t')
                ->leftJoin('empreendimentos as e', 'e.id', '=', 't.empreendimento_id')
                ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('t.corretor_id', $corretorId))
                ->whereNotNull('t.empreendimento_id')
                ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                ->selectRaw(
                    't.empreendimento_id, ' .
                    'COALESCE(e.nome, CONCAT("Empreendimento #", t.empreendimento_id)) as nome, ' .
                    'COUNT(*) as total'
                )
                ->groupBy('t.empreendimento_id', 'e.nome')
                ->orderByDesc('total')
                ->limit(5)
                ->get();
        }

       // =====================================================================
//  Ranking de perguntas por empreendimento
// =====================================================================
$rankingPerguntas = [];

if ($hasMessages) {
    $rowsFaq = DB::table('whatsapp_messages as m')
        ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
        ->leftJoin('empreendimentos as e', 'e.id', '=', 't.empreendimento_id')
        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
        ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('t.corretor_id', $corretorId))
        ->whereBetween('m.created_at', [$dateFrom, $dateTo])
        ->where('m.sender', 'user') // só perguntas
        ->whereNotNull('t.empreendimento_id')
        ->whereNotNull('m.body')
        ->whereRaw("TRIM(m.body) <> ''")
        ->selectRaw("
            t.empreendimento_id as empreendimento_id,
            COALESCE(e.nome, CONCAT('Empreendimento #', t.empreendimento_id)) as empreendimento_nome,
            LOWER(TRIM(m.body)) as pergunta_norm,
            COUNT(*) as total
        ")
        ->groupBy('empreendimento_id', 'empreendimento_nome', 'pergunta_norm')
        ->orderBy('empreendimento_id')
        ->orderByDesc('total')
        ->limit(200)
        ->get();

    // DEBUG 1: ver se está vindo algo da query
    // dd(['qtde_rowsFaq' => $rowsFaq->count(), 'amostra' => $rowsFaq->take(10)]);

    $ignoredExact = [
        'oi','oii','oiii','ola','olá','bom dia','boa tarde','boa noite','boa madrugada',
        'listar unidades','reservar','proposta','bomdia','boanoite','ok','ok.','ok!',
        'blz','td bem','tudo bem','e aí','eai','valeu','obrigado','obrigada',
    ];

    // vamos guardar tudo que for considerado "ação" pra inspecionar no dd()
    $debugAcoesFiltradas = [];

    foreach ($rowsFaq as $row) {
        $empId  = $row->empreendimento_id;
        $texto  = $row->pergunta_norm ?? '';
        $texto  = trim($texto);
        $len    = mb_strlen($texto, 'UTF-8');

        // 1) ignora muito curto
        if ($len < 4) {
            continue;
        }

        // 2) ignora mensagem genérica exata
        if (in_array($texto, $ignoredExact, true)) {
            continue;
        }

        // 3) ignora se não tiver nenhuma letra
        if (!preg_match('/[a-zà-ú]/iu', $texto)) {
            continue;
        }

       // 4) ignora se tiver só 1 palavra
$wordCount = str_word_count($texto, 0, 'àáâãéèêíïóôõöúüç');
if ($wordCount === 1) {
    continue;
}

// NOVO 4.1) só considera mensagens que pareçam dúvida de verdade
$textoLower = mb_strtolower($texto, 'UTF-8');

// se tiver ponto de interrogação, já consideramos como dúvida
$temInterrogacao = str_contains($textoLower, '?');

// começa com alguma palavra típica de pergunta?
$comecaComPergunta = (bool) preg_match(
    '/^(qual|quais|quando|como|onde|que|quanto|quantos|tem|possui|pode|consegue|poderia|me fala|me informa)\b/u',
    $textoLower
);

// se não tiver "?" e também não começar com palavra de pergunta, ignora
if (!$temInterrogacao && !$comecaComPergunta) {
    continue;
}

// 5) ignora comandos de ação (proposta, reserva, etc.)
$acaoKeywords = [
    'proposta',
    'reservar',
    'reserva',
];

$ehAcao = false;
foreach ($acaoKeywords as $kw) {
    if (mb_strpos($textoLower, $kw) !== false) {
        $ehAcao = true;
        break;
    }
}

if ($ehAcao) {
    continue;
}


        // Se passou pelos filtros, entra no ranking
        if (!isset($rankingPerguntas[$empId])) {
            $rankingPerguntas[$empId] = [
                'empreendimento_id'   => $empId,
                'empreendimento_nome' => $row->empreendimento_nome,
                'perguntas'           => [],
            ];
        }

        if (count($rankingPerguntas[$empId]['perguntas']) < 10) {
            $rankingPerguntas[$empId]['perguntas'][] = [
                'pergunta' => $texto,
                'total'    => $row->total,
            ];
        }
    }

   
}


        // ----- Corretores (com avatar quando tiver corretor_id) -----
        $topCorretores = collect();

        // Só faz ranking de corretores para o gestor
        if (!$isCorretor) {
            if ($hasMessages) {
                if ($hasCorretorCol) {
                    // Usa corretor_id + nome + avatar_url
                    $topCorretores = DB::table('whatsapp_messages as m')
                        ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                        ->leftJoin('users as u', 'u.id', '=', 't.corretor_id')
                        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                        ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('t.corretor_id')
                        ->selectRaw(
                            't.corretor_id,
                             COALESCE(u.name, CONCAT("Corretor #", t.corretor_id)) as nome,
                             u.avatar_url,
                             COUNT(*) as total'
                        )
                        ->groupBy('t.corretor_id', 'u.name', 'u.avatar_url')
                        ->orderByDesc('total')
                        ->limit(10)
                        ->get();
                } else {
                    // Fallback: agrupa por telefone e não tem avatar
                    $topCorretores = DB::table('whatsapp_messages as m')
                        ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                        ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('t.phone')
                        ->selectRaw(
                            't.phone as phone,
                             CONCAT("Corretor ", t.phone) as nome,
                             NULL as avatar_url,
                             COUNT(*) as total'
                        )
                        ->groupBy('t.phone')
                        ->orderByDesc('total')
                        ->limit(10)
                        ->get();
                }
            } elseif ($hasThreads) {
                if ($hasCorretorCol) {
                    $topCorretores = DB::table('whatsapp_threads as t')
                        ->leftJoin('users as u', 'u.id', '=', 't.corretor_id')
                        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                        ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('t.corretor_id')
                        ->selectRaw(
                            't.corretor_id,
                             COALESCE(u.name, CONCAT("Corretor #", t.corretor_id)) as nome,
                             u.avatar_url,
                             COUNT(*) as total'
                        )
                        ->groupBy('t.corretor_id', 'u.name', 'u.avatar_url')
                        ->orderByDesc('total')
                        ->limit(10)
                        ->get();
                } else {
                    $topCorretores = DB::table('whatsapp_threads as t')
                        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                        ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('t.phone')
                        ->selectRaw(
                            't.phone as phone,
                             CONCAT("Corretor ", t.phone) as nome,
                             NULL as avatar_url,
                             COUNT(*) as total'
                        )
                        ->groupBy('t.phone')
                        ->orderByDesc('total')
                        ->limit(10)
                        ->get();
                }
            }
        }

        // ----- KPI cards -----
        $totalMsgs        = (int) $timeline->sum('total');
        $corretoresAtivos = 0;
        $tempoMedioIA     = null; // calculado se existir coluna de latência
        $mediaPerguntas   = 0.0;

        // Corretores ativos
        if ($isCorretor) {
            // para corretor, a visão é individual; a Blade nem exibe esse KPI
            $corretoresAtivos = 1;
        } else {
            if ($hasMessages) {
                if ($hasCorretorCol) {
                    $corretoresAtivos = DB::table('whatsapp_messages as m')
                        ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                        ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('t.corretor_id')
                        ->distinct('t.corretor_id')
                        ->count('t.corretor_id');
                } else {
                    $corretoresAtivos = DB::table('whatsapp_messages as m')
                        ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                        ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('t.phone')
                        ->distinct('t.phone')
                        ->count('t.phone');
                }
            } elseif ($hasThreads) {
                if ($hasCorretorCol) {
                    $corretoresAtivos = DB::table('whatsapp_threads as t')
                        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                        ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('t.corretor_id')
                        ->distinct('t.corretor_id')
                        ->count('t.corretor_id');
                } else {
                    $corretoresAtivos = DB::table('whatsapp_threads as t')
                        ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                        ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('t.phone')
                        ->distinct('t.phone')
                        ->count('t.phone');
                }
            }
        }

        // Tempo médio de resposta da IA (se houver coluna de latência)
        if ($hasMessages && ($hasLatencyMs || $hasLatencySec)) {
            $col = $hasLatencyMs ? 'latency_ms' : 'latency_seconds';

            $avgLatency = DB::table('whatsapp_messages as m')
                ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('t.corretor_id', $corretorId))
                ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                ->avg("m.$col");

            if (!is_null($avgLatency)) {
                $tempoMedioIA = $hasLatencyMs
                    ? ($avgLatency / 1000.0)   // ms -> segundos
                    : $avgLatency;            // já em segundos
            }
        }

        // -----------------------------------------------------------------
        //  Média de perguntas
        //  Gestor: média por corretor
        //  Corretor: média diária de perguntas (dele)
        // -----------------------------------------------------------------
        if ($hasMessages) {
            if ($isCorretor && $corretorId && $hasCorretorCol) {
                // Total de perguntas do corretor logado
                $totalPerguntas = DB::table('whatsapp_messages as m')
                    ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                    ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                    ->where('m.sender', 'user')
                    ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                    ->where('t.corretor_id', $corretorId)
                    ->count();

                $periodDays = max(1, $dateFrom->diffInDays($dateTo) + 1);
                if ($totalPerguntas > 0) {
                    $mediaPerguntas = $totalPerguntas / $periodDays;
                }
            } elseif (!$isCorretor && $corretoresAtivos > 0) {
                // visão do gestor: média de perguntas por corretor
                $totalPerguntas = DB::table('whatsapp_messages as m')
                    ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                    ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                    ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                    ->where('m.sender', 'user')
                    ->count();

                if ($totalPerguntas > 0) {
                    $mediaPerguntas = $totalPerguntas / max(1, $corretoresAtivos);
                }
            }
        }

        // ----- Heatmap (hora x dia) -----
        $heatmap = [];
        if ($hasMessages) {
            $rows = DB::table('whatsapp_messages as m')
                ->join('whatsapp_threads as t', 't.id', '=', 'm.thread_id')
                ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('t.corretor_id', $corretorId))
                ->whereBetween('m.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DAYOFWEEK(m.created_at) as dow, HOUR(m.created_at) as h, COUNT(*) as c')
                ->groupBy('dow', 'h')
                ->get();

            foreach ($rows as $row) {
                $dow = (int) $row->dow;
                $h   = (int) $row->h;
                $heatmap[$dow][$h] = (int) $row->c;
            }
        } elseif ($hasThreads) {
            // Fallback: usa created_at das threads como proxy de atividade
            $rows = DB::table('whatsapp_threads as t')
                ->when($companyId, fn ($qq) => $qq->where('t.company_id', $companyId))
                ->when($corretorId && $hasCorretorCol, fn ($qq) => $qq->where('t.corretor_id', $corretorId))
                ->whereBetween('t.created_at', [$dateFrom, $dateTo])
                ->selectRaw('DAYOFWEEK(t.created_at) as dow, HOUR(t.created_at) as h, COUNT(*) as c')
                ->groupBy('dow', 'h')
                ->get();

            foreach ($rows as $row) {
                $dow = (int) $row->dow;
                $h   = (int) $row->h;
                $heatmap[$dow][$h] = (int) $row->c;
            }
        }

        return [
            'dateFrom'         => $dateFrom->toDateString(),
            'dateTo'           => $dateTo->toDateString(),
            'timeline'         => $timeline,
            'topEmp'           => $topEmp,
            'topCorretores'    => $topCorretores,
            'kpis'             => [
                'totalConversas'         => $totalMsgs,
                'corretoresAtivos'       => $corretoresAtivos,
                'tempoMedioIA'           => $tempoMedioIA,
                'mediaPerguntasCorretor' => $mediaPerguntas,
            ],
            'heatmap'          => $heatmap,
            'hasData'          => $hasThreads || $hasMessages,
            'rankingPerguntas' => $rankingPerguntas,
        ];
    }
}
