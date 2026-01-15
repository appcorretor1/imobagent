<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmInteraction;
use App\Enums\CrmDealStatus;
use App\Enums\CrmActivityStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CrmDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        
        // Busca o corretor_id correto
        // Primeiro tenta pelo número de telefone/whatsapp do usuário logado
        $corretorId = $user->id;
        
        // Se o usuário tem whatsapp/phone cadastrado, busca threads dele
        // O corretor_id usado nos dados CRM é o mesmo da thread
        $phone = null;
        if ($user->whatsapp) {
            $phone = preg_replace('/\D+/', '', $user->whatsapp);
        } elseif ($user->phone) {
            $phone = preg_replace('/\D+/', '', $user->phone);
        }
        
        if ($phone) {
            $thread = \App\Models\WhatsappThread::where('phone', $phone)->first();
            if ($thread && $thread->corretor_id) {
                // Usa o corretor_id da thread (mesmo usado ao criar dados via WhatsApp)
                $corretorId = $thread->corretor_id;
            } else {
                // Se não tem thread, cria uma e vincula o corretor
                // Isso garante que dados futuros usem o mesmo ID
                $thread = \App\Models\WhatsappThread::firstOrCreate(
                    ['phone' => $phone],
                    ['thread_id' => 'thread_' . \Illuminate\Support\Str::random(24)]
                );
                if (empty($thread->corretor_id)) {
                    $thread->corretor_id = $user->id;
                    if ($user->company_id) {
                        $thread->company_id = $user->company_id;
                    }
                    $thread->save();
                }
                $corretorId = $thread->corretor_id;
            }
        }

        // Filtros
        $periodo = $request->get('periodo', 'semana'); // semana, mes
        $status = $request->get('status');

        $inicio = match($periodo) {
            'semana' => Carbon::now('America/Sao_Paulo')->startOfWeek(),
            'mes' => Carbon::now('America/Sao_Paulo')->startOfMonth(),
            default => Carbon::now('America/Sao_Paulo')->startOfWeek(),
        };
        $fim = match($periodo) {
            'semana' => Carbon::now('America/Sao_Paulo')->endOfWeek(),
            'mes' => Carbon::now('America/Sao_Paulo')->endOfMonth(),
            default => Carbon::now('America/Sao_Paulo')->endOfWeek(),
        };

        // Debug: verificar se há dados
        $totalAtividades = CrmActivity::where('corretor_id', $corretorId)->count();
        $totalDeals = CrmDeal::where('corretor_id', $corretorId)->count();
        $totalNotes = \App\Models\CrmNote::where('corretor_id', $corretorId)->count();
        $totalAtividadesAll = CrmActivity::count();
        $totalDealsAll = CrmDeal::count();
        $totalNotesAll = \App\Models\CrmNote::count();
        
        // KPIs
        $kpis = [
            'visitas_semana' => CrmActivity::where('corretor_id', $corretorId)
                ->where('tipo', 'visita')
                ->where(function($query) use ($inicio, $fim) {
                    $query->whereBetween('agendado_para', [$inicio, $fim])
                          ->orWhereBetween('created_at', [$inicio, $fim]);
                })
                ->count(),
            
            'propostas_aguardando' => CrmDeal::where('corretor_id', $corretorId)
                ->propostas()
                ->aguardandoResposta()
                ->count(),
            
            'propostas_fechadas' => CrmDeal::where('corretor_id', $corretorId)
                ->where('status', CrmDealStatus::FECHADA->value)
                ->where(function($query) {
                    // Propostas fechadas (tipo = proposta) OU vendas fechadas (tipo = venda)
                    $query->where('tipo', 'proposta')
                          ->orWhere('tipo', 'venda');
                })
                ->count(),
            
            'vendas_mes' => CrmDeal::where('corretor_id', $corretorId)
                ->vendas()
                ->fechadas()
                ->sum('valor') ?? 0,
            
            'pendencias' => CrmActivity::where('corretor_id', $corretorId)
                ->pendentes()
                ->count(),
            
            // Debug info
            'debug_total_atividades' => $totalAtividades,
            'debug_total_deals' => $totalDeals,
            'debug_total_notes' => $totalNotes,
            'debug_total_atividades_all' => $totalAtividadesAll,
            'debug_total_deals_all' => $totalDealsAll,
            'debug_total_notes_all' => $totalNotesAll,
            'debug_corretor_id' => $corretorId,
            'debug_user_id' => $user->id,
            'debug_company_id' => $companyId,
            'debug_phone' => $phone ?? 'N/A',
        ];

        // Atividades recentes (visitas, follow-ups, etc)
        $atividades = CrmActivity::where('corretor_id', $corretorId)
            ->with(['lead', 'empreendimento'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        // Anotações recentes (para mostrar junto)
        $anotacoes = \App\Models\CrmNote::where('corretor_id', $corretorId)
            ->with(['lead', 'empreendimento'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Propostas/Vendas
        $deals = CrmDeal::where('corretor_id', $corretorId)
            ->with(['lead', 'empreendimento'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('admin.crm.dashboard', compact('kpis', 'atividades', 'anotacoes', 'deals', 'periodo'));
    }
}
