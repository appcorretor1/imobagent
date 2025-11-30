@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;

    $role = auth()->user()->role ?? null;
    $isGestor = in_array($role, ['super_admin', 'diretor', 'gerente']);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl">
                    {{ $isGestor ? 'Dashboard do Gestor' : 'Seu painel de uso da IA' }}
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    De {{ $dateFrom }} até {{ $dateTo }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <form method="GET" class="flex items-center gap-2">
                    <label class="text-sm text-slate-600">Período:</label>
                    <select name="days" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                        @foreach([7,14,30,60,90] as $opt)
                            <option value="{{ $opt }}" @selected($opt == $days)>
                                Últimos {{ $opt }} dias
                            </option>
                        @endforeach
                    </select>
                </form>

                {{-- PDF faz mais sentido pro gestor, mas se quiser pode proteger pela role também --}}
                <a href="{{ route('admin.dashboard.pdf', ['days' => $days]) }}"
                   class="inline-flex items-center gap-1 text-xs px-3 py-2 rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50">
                    Baixar PDF
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @unless($hasData)
            <div class="bg-yellow-50 text-yellow-800 p-4 rounded">
                Nenhum dado encontrado ainda. Assim que as conversas acontecerem, os gráficos aparecem aqui.
            </div>
        @endunless

   

    {{-- KPIs --}}
<div class="dashboard-grid" style="--cols: {{ $isGestor ? 4 : 3 }}">
    {{-- Total de mensagens --}}
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-sm text-slate-600">
            {{ $isGestor ? 'Total de mensagens' : 'Suas mensagens com a IA' }}
        </div>
        <div class="text-3xl font-semibold mt-2">
            {{ number_format($kpis['totalConversas'] ?? 0, 0, ',', '.') }}
        </div>
    </div>

    {{-- Corretores ativos (só gestor) --}}
    @if($isGestor)
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-sm text-slate-600">Corretores ativos</div>
            <div class="text-3xl font-semibold mt-2">
                {{ $kpis['corretoresAtivos'] ?? 0 }}
            </div>
        </div>
    @endif

    {{-- Tempo médio de resposta da IA --}}
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-sm text-slate-600">
            Tempo médio de resposta da IA
        </div>
        <div class="text-3xl font-semibold mt-2">
            @if(!empty($kpis['tempoMedioIA']))
                {{ number_format($kpis['tempoMedioIA'], 1, ',', '.') }}s
            @else
                —
            @endif
        </div>
    </div>

    {{-- Média de perguntas --}}
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-sm text-slate-600">
            {{ $isGestor ? 'Média de perguntas por corretor' : 'Média diária de perguntas (você)' }}
        </div>
        <div class="text-3xl font-semibold mt-2">
            {{ number_format($kpis['mediaPerguntasCorretor'] ?? 0, 1, ',', '.') }}
        </div>
    </div>
</div>


        {{-- Evolução de conversas --}}
        <div class="bg-white rounded-xl shadow p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold">Evolução de conversas por dia</h3>
            </div>
            <div class="mt-2">
                <div id="chartTimeline" class="w-full" style="height: 260px;"></div>
            </div>
        </div>

        {{-- Empreendimentos + (opcional) ranking de corretores --}}
        <div class="grid grid-cols-1 {{ $isGestor ? 'lg:grid-cols-2' : 'lg:grid-cols-1' }} gap-4">
            {{-- Empreendimentos mais falados --}}
            <div class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-4">Empreendimentos mais falados</h3>

                @if($topEmp->isEmpty())
                    <p class="text-slate-500 text-sm">Nenhum dado disponível no período.</p>
                @else
                    <div id="chartEmp" class="w-full" style="height: 260px;"></div>

                    <ul class="mt-4 text-sm text-slate-700 space-y-1">
                        @foreach($topEmp as $i => $row)
                            <li>
                                {{ $i+1 }}º — {{ $row->nome }}
                                <span class="text-slate-500">({{ $row->total }})</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Corretores mais ativos (só gestor) --}}
            @if($isGestor)
                <div class="bg-white rounded-xl shadow p-4">
                    <h3 class="font-semibold mb-4">Corretores que mais conversaram com a IA</h3>

                    @php
                        $corretoresTop5 = $topCorretores->take(5);
                    @endphp

                    @if($corretoresTop5->isEmpty())
                        <p class="text-sm text-slate-500">Ainda não há interações suficientes para montar o ranking.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($corretoresTop5 as $i => $row)
                                @php
                                    $nome = $row->nome ?? 'Corretor';
                                    $iniciais = Str::upper(
                                        mb_substr(Str::of($nome)->trim(), 0, 1) .
                                        mb_substr(Str::of($nome)->after(' ')->trim(), 0, 1)
                                    );

                                    $avatarUrl = null;
                                    if (!empty($row->avatar_url)) {
                                        try {
                                            $avatarUrl = Storage::disk('s3')->temporaryUrl(
                                                $row->avatar_url,
                                                now()->addMinutes(10)
                                            );
                                        } catch (\Throwable $e) {
                                            $avatarUrl = null;
                                        }
                                    }
                                @endphp

                                <li class="flex items-center justify-between 
                                           bg-gray-100/25 border border-gray-100 
                                           p-2 rounded-lg
                                           hover:bg-gray-100/50 transition">
                                    <div class="flex items-center gap-3">
                                        @if($avatarUrl)
                                            <img src="{{ $avatarUrl }}"
                                                 class="h-10 w-10 rounded-full object-cover"
                                                 alt="{{ $nome }}">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-semibold text-indigo-700">
                                                {{ $iniciais }}
                                            </div>
                                        @endif

                                        <div>
                                            <div class="text-sm font-medium text-slate-800">
                                                {{ $i+1 }}º — {{ $nome }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{ $row->total }} interações
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>

        {{-- Heatmap de uso --}}
        <div class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-4">Horários de maior uso (heatmap)</h3>
            @php
                $dias = ['D','S','T','Q','Q','S','S']; // 1=Dom, 7=Sáb
                $maxV = 0;
                foreach ($heatmap as $dow => $hours) {
                    foreach ($hours as $h => $c) $maxV = max($maxV, $c);
                }
            @endphp

            @if($maxV === 0)
                <p class="text-sm text-slate-500">Ainda não há dados suficientes para o mapa de calor.</p>
            @else
                <div id="heatmapWrapper" class="relative">
                    <div class="grid grid-cols-[auto_repeat(24,minmax(0,1fr))] gap-1 text-xs overflow-x-auto">
                        <div></div>
                        @for($h = 0; $h < 24; $h++)
                            <div class="text-center text-slate-500">{{ $h }}</div>
                        @endfor

                        @for($d = 1; $d <= 7; $d++)
                            <div class="text-right pr-2 text-slate-600">{{ $dias[$d-1] }}</div>
                            @for($h = 0; $h < 24; $h++)
                                @php
                                    $val = $heatmap[$d][$h] ?? 0;
                                    $alpha = $maxV ? min(1, $val / $maxV) : 0;
                                    $bg = $alpha == 0 ? 'bg-slate-100' : 'bg-indigo-500';
                                    $opacity = $alpha == 0
                                        ? 'opacity-30'
                                        : ($alpha < 0.3 ? 'opacity-40' : ($alpha < 0.6 ? 'opacity-70' : 'opacity-100'));
                                @endphp
                                <div
                                    class="h-5 rounded {{ $bg }} {{ $opacity }} cursor-pointer"
                                    data-heat-cell="1"
                                    data-dia="{{ $dias[$d-1] }}"
                                    data-hora="{{ $h }}"
                                    data-val="{{ $val }}"
                                ></div>
                            @endfor
                        @endfor
                    </div>

                    {{-- Tooltip custom --}}
                    <div
                        id="heatmapTooltip"
                        class="hidden pointer-events-none absolute z-50 bg-slate-900 text-white text-xs px-2 py-1 rounded shadow-lg"
                    ></div>
                </div>
            @endif
        </div>
        
             {{-- Ranking de perguntas por empreendimento --}}
@if(!empty($rankingPerguntas) && count($rankingPerguntas))

    @php
        $firstKey   = array_key_first($rankingPerguntas);
        $firstEmpId = $firstKey !== null
            ? ($rankingPerguntas[$firstKey]['empreendimento_id'] ?? null)
            : null;
    @endphp

    <div
        x-data="{ activeEmp: '{{ $firstEmpId }}' }"
        class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 md:p-5"
    >
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="font-semibold text-slate-900">
                    Perguntas dos corretores por empreendimento
                </h3>
                <p class="text-xs text-slate-500 mt-1">
                    Veja quais dúvidas mais se repetem em cada empreendimento.
                </p>
            </div>

            {{-- Badge com total de empreendimentos --}}
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-50 text-slate-500 border border-slate-100">
                {{ count($rankingPerguntas) }} empreendimentos
            </span>
        </div>

        {{-- Abas (empreendimentos) --}}
        <div class="flex gap-2 overflow-x-auto pb-1 border-b border-slate-100 mb-3 scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent">
            @foreach($rankingPerguntas as $emp)
                @php
                    $empId   = $emp['empreendimento_id'];
                    $empNome = $emp['empreendimento_nome'];
                    $qtdePerguntas = count($emp['perguntas'] ?? []);
                @endphp

                <button
                    type="button"
                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs md:text-sm border transition-all whitespace-nowrap
                           hover:bg-slate-50 hover:border-slate-300"
                    :class="activeEmp === '{{ $empId }}'
                        ? 'bg-slate-900 text-white border-slate-900 shadow-sm'
                        : 'bg-white text-slate-600 border-slate-200'"
                    @click="activeEmp = '{{ $empId }}'"
                >
                    <span class="font-medium">
                        {{ \Illuminate\Support\Str::limit($empNome, 26) }}
                    </span>

                    @if($qtdePerguntas > 0)
                        <span class="inline-flex items-center justify-center text-[10px] px-1.5 py-0.5 rounded-full"
                            :class="activeEmp === '{{ $empId }}'
                                ? 'bg-white/15 text-slate-100'
                                : 'bg-slate-100 text-slate-500'">
                            {{ $qtdePerguntas }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Conteúdo da aba selecionada --}}
        <div class="mt-3">
            @foreach($rankingPerguntas as $emp)
                @php
                    $empId   = $emp['empreendimento_id'];
                    $perguntas = $emp['perguntas'] ?? [];
                @endphp

                <div
                    x-show="activeEmp === '{{ $empId }}'"
                    x-transition
                    x-cloak
                    class="space-y-1.5 text-sm text-slate-700"
                >
                    @if(!empty($perguntas))
                        <ol class="space-y-1.5 list-decimal list-inside">
                            @foreach($perguntas as $item)
                                <li class="flex items-start justify-between gap-3">
                                    <span class="text-slate-700">
                                        “{{ $item['pergunta'] }}”
                                    </span>

                                    <span class="text-[11px] text-slate-400 whitespace-nowrap mt-0.5">
                                        {{ $item['total'] }}x
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-xs text-slate-500">
                            Ainda não há perguntas suficientes para esse empreendimento.
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif


        {{-- Insights rápidos --}}
        <div class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-2">Insights</h3>
            <ul class="list-disc pl-5 text-slate-700 space-y-1">
                @if(count($topEmp))
                    <li>
                        🏆 <strong>{{ $topEmp[0]->nome }}</strong>
                        é o empreendimento mais citado
                        {{ $isGestor ? 'no período.' : 'por você neste período.' }}
                    </li>
                @endif

                @if($isGestor && count($topCorretores))
                    <li>
                        🥇 <strong>{{ $topCorretores[0]->nome }}</strong> é o corretor mais engajado com a IA.
                    </li>
                @endif

                <li>
                    📈 Total de conversas
                    {{ $isGestor ? 'da equipe' : 'suas' }}:
                    <strong>{{ number_format($kpis['totalConversas'] ?? 0, 0, ',', '.') }}</strong>.
                </li>

                @if($isGestor)
                    <li>
                        👥 Corretores ativos:
                        <strong>{{ $kpis['corretoresAtivos'] ?? 0 }}</strong>.
                    </li>
                    <li>
                        💬 Média de perguntas por corretor:
                        <strong>{{ number_format($kpis['mediaPerguntasCorretor'] ?? 0, 1, ',', '.') }}</strong>.
                    </li>
                @else
                    <li>
                        💬 Sua média diária de perguntas:
                        <strong>{{ number_format($kpis['mediaPerguntasCorretor'] ?? 0, 1, ',', '.') }}</strong>.
                    </li>
                @endif
            </ul>
        </div>
    </div>

    {{-- ECharts CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>

    <script>
        const timelineLabels = @json(collect($timeline)->pluck('d'));
        const timelineData   = @json(collect($timeline)->pluck('total'));
        const empLabels      = @json(collect($topEmp)->pluck('nome'));
        const empData        = @json(collect($topEmp)->pluck('total'));

        // Timeline - linha
        if (document.getElementById('chartTimeline')) {
            const chartTimeline = echarts.init(document.getElementById('chartTimeline'));
            chartTimeline.setOption({
                tooltip: { trigger: 'axis' },
                xAxis: {
                    type: 'category',
                    data: timelineLabels,
                    axisLine: { lineStyle: { color: '#64748b' } },
                    axisLabel: { color: '#64748b' }
                },
                yAxis: {
                    type: 'value',
                    minInterval: 1,
                    axisLine: { lineStyle: { color: '#64748b' } },
                    axisLabel: { color: '#64748b' },
                    splitLine: { lineStyle: { color: '#e2e8f0' } }
                },
                series: [{
                    data: timelineData,
                    type: 'line',
                    smooth: true,
                    areaStyle: { opacity: 0.08 },
                }]
            });
            window.addEventListener('resize', () => chartTimeline.resize());
        }

        // Empreendimentos - barras verticais
        if (document.getElementById('chartEmp')) {
            const chartEmp = echarts.init(document.getElementById('chartEmp'));

            const colors = [
                '#93C5FD', // azul claro
                '#60A5FA',
                '#3B82F6',
                '#2563EB',
                '#1E40AF'  // azul escuro
            ];

            chartEmp.setOption({
                animation: true,
                animationDuration: 900,
                animationEasing: 'cubicOut', // entrada suave

                tooltip: { trigger: 'axis' },
                grid: { left: '3%', right: '3%', bottom: '8%', containLabel: true },

                xAxis: {
                    type: 'category',
                    data: empLabels,
                    axisLabel: {
                        color: '#64748b',
                        formatter: (value) => value.length > 18 ? value.substring(0, 18) + '…' : value
                    },
                    axisLine: { lineStyle: { color: '#cbd5f5' } }
                },

                yAxis: {
                    type: 'value',
                    minInterval: 1,
                    axisLabel: { color: '#64748b' },
                    axisLine: { lineStyle: { color: '#cbd5f5' } },
                    splitLine: { lineStyle: { color: '#e2e8f0' } }
                },

                series: [{
                    type: 'bar',
                    data: empData,
                    barWidth: '45%',

                    itemStyle: {
                        borderRadius: [6, 6, 0, 0],
                        color: function (params) {
                            return colors[params.dataIndex % colors.length];
                        }
                    },

                    emphasis: {
                        itemStyle: {
                            opacity: 0.9
                        }
                    },

                    animationDelay: function (idx) {
                        return idx * 120; // efeito cascata
                    }
                }]
            });

            window.addEventListener('resize', () => chartEmp.resize());
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tooltip = document.getElementById('heatmapTooltip');
            const wrapper = document.getElementById('heatmapWrapper');
            if (!tooltip || !wrapper) return;

            const cells = wrapper.querySelectorAll('[data-heat-cell]');

            cells.forEach(cell => {
                cell.addEventListener('mouseenter', (e) => {
                    const dia  = cell.getAttribute('data-dia');
                    const hora = cell.getAttribute('data-hora');
                    const val  = cell.getAttribute('data-val');

                    tooltip.textContent = `${dia} • ${hora}h — ${val} conversa${val === '1' ? '' : 's'}`;
                    tooltip.classList.remove('hidden');
                });

                cell.addEventListener('mousemove', (e) => {
                    const rect = wrapper.getBoundingClientRect();
                    const x = e.clientX - rect.left + 8;
                    const y = e.clientY - rect.top - 30;

                    tooltip.style.left = x + 'px';
                    tooltip.style.top  = y + 'px';
                });

                cell.addEventListener('mouseleave', () => {
                    tooltip.classList.add('hidden');
                });
            });
        });
    </script>

</x-app-layout>
