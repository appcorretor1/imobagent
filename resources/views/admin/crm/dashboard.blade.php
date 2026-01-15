<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Assistente do Corretor - Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(config('app.debug'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4 text-sm">
                <strong>Debug Info:</strong><br>
                <strong>Filtrado (corretor_id={{ $kpis['debug_corretor_id'] ?? 'N/A' }}):</strong>
                Atividades: {{ $kpis['debug_total_atividades'] ?? 0 }}, 
                Deals: {{ $kpis['debug_total_deals'] ?? 0 }}, 
                Notes: {{ $kpis['debug_total_notes'] ?? 0 }}<br>
                <strong>Total (sem filtro):</strong>
                Atividades: {{ $kpis['debug_total_atividades_all'] ?? 0 }}, 
                Deals: {{ $kpis['debug_total_deals_all'] ?? 0 }}, 
                Notes: {{ $kpis['debug_total_notes_all'] ?? 0 }}<br>
                <strong>IDs:</strong>
                User ID: {{ $kpis['debug_user_id'] ?? 'N/A' }}, 
                Corretor ID: {{ $kpis['debug_corretor_id'] ?? 'N/A' }}, 
                Company ID: {{ $kpis['debug_company_id'] ?? 'N/A' }}, 
                Phone: {{ $kpis['debug_phone'] ?? 'N/A' }}
            </div>
            @endif
            
            {{-- KPIs --}}
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 mb-1">Visitas da Semana</div>
                    <div class="text-3xl font-bold text-blue-600">{{ $kpis['visitas_semana'] }}</div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 mb-1">Propostas Aguardando</div>
                    <div class="text-3xl font-bold text-yellow-600">{{ $kpis['propostas_aguardando'] }}</div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 mb-1">Propostas Fechadas</div>
                    <div class="text-3xl font-bold text-purple-600">{{ $kpis['propostas_fechadas'] }}</div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 mb-1">Vendas do Mês</div>
                    <div class="text-3xl font-bold text-green-600">
                        R$ {{ number_format($kpis['vendas_mes'], 2, ',', '.') }}
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 mb-1">Pendências</div>
                    <div class="text-3xl font-bold text-red-600">{{ $kpis['pendencias'] }}</div>
                </div>
            </div>

            {{-- Atividades --}}
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold">Atividades Recentes</h3>
                </div>
                <div class="p-6">
                    @if($atividades->isEmpty() && $anotacoes->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-500 mb-4">Nenhuma atividade encontrada.</p>
                            <p class="text-sm text-gray-400">
                                Use o WhatsApp para criar visitas, propostas e anotações.<br>
                                Exemplo: <code class="bg-gray-100 px-2 py-1 rounded">visita amanhã 15h com João no Paradizzo</code>
                            </p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($atividades as $atividade)
                                <div class="border-b pb-4 last:border-0">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium">{{ $atividade->titulo }}</div>
                                            <div class="text-sm text-gray-500">
                                                Cliente: {{ $atividade->lead->nome ?? 'N/A' }}
                                                @if($atividade->empreendimento)
                                                    | {{ $atividade->empreendimento->nome }}
                                                @endif
                                            </div>
                                            @if($atividade->agendado_para)
                                                <div class="text-sm text-gray-400">
                                                    {{ $atividade->agendado_para->format('d/m/Y H:i') }}
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="px-2 py-1 text-xs rounded {{ $atividade->status->value === 'pendente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                                {{ $atividade->status->label() }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            
                            @foreach($anotacoes as $anotacao)
                                <div class="border-b pb-4 last:border-0">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium">📝 Anotação</div>
                                            <div class="text-sm text-gray-500">
                                                @if($anotacao->lead)
                                                    Cliente: {{ $anotacao->lead->nome }}
                                                @endif
                                                @if($anotacao->empreendimento)
                                                    | {{ $anotacao->empreendimento->nome }}
                                                @endif
                                            </div>
                                            <div class="text-sm text-gray-700 mt-1">
                                                {{ $anotacao->conteudo }}
                                            </div>
                                            <div class="text-sm text-gray-400 mt-1">
                                                {{ $anotacao->created_at->format('d/m/Y H:i') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Propostas/Vendas --}}
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold">Propostas e Vendas</h3>
                </div>
                <div class="p-6">
                    @if($deals->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-500 mb-4">Nenhuma proposta ou venda encontrada.</p>
                            <p class="text-sm text-gray-400">
                                Use o WhatsApp para criar propostas e registrar vendas.<br>
                                Exemplo: <code class="bg-gray-100 px-2 py-1 rounded">proposta 520k para Maria</code>
                            </p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($deals as $deal)
                                <div class="border-b pb-4 last:border-0">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium">
                                                {{ $deal->lead->nome ?? 'N/A' }}
                                                <span class="text-sm text-gray-500">({{ $deal->tipo }})</span>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                @if($deal->empreendimento_nome)
                                                    {{ $deal->empreendimento_nome }}
                                                @elseif($deal->empreendimento)
                                                    {{ $deal->empreendimento->nome }}
                                                @endif
                                                @if($deal->unidade)
                                                    | Unidade {{ $deal->unidade }}
                                                    @if($deal->torre)
                                                        - Torre {{ $deal->torre }}
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="text-sm font-medium text-green-600">
                                                @if($deal->valor)
                                                    R$ {{ number_format($deal->valor, 2, ',', '.') }}
                                                @endif
                                            </div>
                                        </div>
                                        <div>
                                            <span class="px-2 py-1 text-xs rounded 
                                                {{ $deal->status->value === 'aguardando_resposta' ? 'bg-yellow-100 text-yellow-800' : 
                                                   ($deal->status->value === 'fechada' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') }}">
                                                {{ $deal->status->label() }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
