<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl">Dashboard do Gestor</h2>
      <form method="GET" class="flex items-center gap-2">
        <label class="text-sm text-slate-600">Período:</label>
        <select name="days" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
          @foreach([7,14,30,60,90] as $opt)
            <option value="{{ $opt }}" @selected($opt==$days)>Últimos {{ $opt }} dias</option>
          @endforeach
        </select>
      </form>
    </div>
    <p class="text-xs text-slate-500 mt-1">De {{ $dateFrom }} até {{ $dateTo }}</p>
  </x-slot>

  <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
    @unless($hasData)
      <div class="bg-yellow-50 text-yellow-800 p-4 rounded">
        Nenhum dado encontrado ainda. Assim que as conversas acontecerem, os gráficos aparecem aqui.
      </div>
    @endunless

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white rounded shadow p-4">
        <div class="text-sm text-slate-600">Total de conversas</div>
        <div class="text-3xl font-semibold mt-2">{{ number_format($kpis['totalConversas'] ?? 0, 0, ',', '.') }}</div>
      </div>
      <div class="bg-white rounded shadow p-4">
        <div class="text-sm text-slate-600">Corretores ativos</div>
        <div class="text-3xl font-semibold mt-2">{{ $kpis['corretoresAtivos'] ?? 0 }}</div>
      </div>
      <div class="bg-white rounded shadow p-4">
        <div class="text-sm text-slate-600">Tempo médio de resposta da IA</div>
        <div class="text-3xl font-semibold mt-2">
          @if(!empty($kpis['tempoMedioIA']))
            {{ round($kpis['tempoMedioIA'],1) }}s
          @else
            —
          @endif
        </div>
      </div>
    </div>

    {{-- Linhas: Evolução de conversas --}}
    <div class="bg-white rounded shadow p-4">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold">Evolução de conversas por dia</h3>
      </div>
      <div class="mt-4">
        <canvas id="chartTimeline" height="90"></canvas>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      {{-- Empreendimentos mais falados --}}
      <div class="bg-white rounded shadow p-4">
        <h3 class="font-semibold">Empreendimentos mais falados</h3>
        <div class="mt-4">
          <canvas id="chartEmp" height="140"></canvas>
        </div>
        <ul class="mt-4 text-sm text-slate-700 space-y-1">
          @foreach($topEmp as $i => $row)
            <li>{{ $i+1 }}º — {{ $row->nome }} <span class="text-slate-500">({{ $row->total }})</span></li>
          @endforeach
        </ul>
      </div>

      {{-- Corretores mais ativos --}}
      <div class="bg-white rounded shadow p-4">
        <h3 class="font-semibold">Corretores que mais conversaram com a IA</h3>
        <div class="mt-4">
          <canvas id="chartCorretores" height="140"></canvas>
        </div>
        <ul class="mt-4 text-sm text-slate-700 space-y-1">
          @foreach($topCorretores as $i => $row)
            <li>{{ $i+1 }}º — {{ $row->nome }} <span class="text-slate-500">({{ $row->total }})</span></li>
          @endforeach
        </ul>
      </div>
    </div>

    {{-- Heatmap simples (dia x hora) --}}
    <div class="bg-white rounded shadow p-4">
      <h3 class="font-semibold mb-4">Horários de maior uso (heatmap)</h3>
      @php
        // monta uma grade 7 x 24 (1=Domingo ... 7=Sábado)
        $dias = ['D','S','T','Q','Q','S','S'];
        $maxV = 0;
        foreach ($heatmap as $dow => $hours) {
          foreach ($hours as $h => $c) $maxV = max($maxV, $c);
        }
      @endphp
      <div class="grid grid-cols-[auto_repeat(24,minmax(0,1fr))] gap-1 text-xs">
        <div></div>
        @for($h=0;$h<24;$h++)
          <div class="text-center text-slate-500">{{ $h }}</div>
        @endfor
        @for($d=1;$d<=7;$d++)
          <div class="text-right pr-2 text-slate-600">{{ $dias[$d-1] }}</div>
          @for($h=0;$h<24;$h++)
            @php
              $val = $heatmap[$d][$h] ?? 0;
              // intensidade: 0 -> bg-slate-100, alto -> bg-indigo-600
              $alpha = $maxV ? min(1, $val / $maxV) : 0;
              $bg = $alpha==0 ? 'bg-slate-100' : 'bg-indigo-500';
              $opacity = $alpha==0 ? 'opacity-30' : ($alpha<0.3?'opacity-40':($alpha<0.6?'opacity-70':'opacity-100'));
            @endphp
            <div class="h-5 rounded {{ $bg }} {{ $opacity }}" title="{{ $val }}"></div>
          @endfor
        @endfor
      </div>
    </div>

    {{-- Insights rápidos --}}
    <div class="bg-white rounded shadow p-4">
      <h3 class="font-semibold mb-2">Insights</h3>
      <ul class="list-disc pl-5 text-slate-700 space-y-1">
        @if(count($topEmp))
          <li>🏆 <strong>{{ $topEmp[0]->nome }}</strong> é o empreendimento mais citado no período.</li>
        @endif
        @if(count($topCorretores))
          <li>🥇 <strong>{{ $topCorretores[0]->nome }}</strong> é o corretor mais engajado com a IA.</li>
        @endif
        <li>📈 Total de conversas no período: <strong>{{ number_format($kpis['totalConversas'] ?? 0, 0, ',', '.') }}</strong>.</li>
        <li>👥 Corretores ativos: <strong>{{ $kpis['corretoresAtivos'] ?? 0 }}</strong>.</li>
      </ul>
    </div>
  </div>

  {{-- Chart.js CDN --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Dados da timeline
    const timelineLabels = @json(collect($timeline)->pluck('d'));
    const timelineData   = @json(collect($timeline)->pluck('total'));

    new Chart(document.getElementById('chartTimeline'), {
      type: 'line',
      data: {
        labels: timelineLabels,
        datasets: [{
          label: 'Conversas',
          data: timelineData,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });

    // Top Empreendimentos
    const empLabels = @json(collect($topEmp)->pluck('nome'));
    const empData   = @json(collect($topEmp)->pluck('total'));

    new Chart(document.getElementById('chartEmp'), {
      type: 'bar',
      data: {
        labels: empLabels,
        datasets: [{ label: 'Menções', data: empData }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        indexAxis: 'y',
        scales: { x: { beginAtZero: true } }
      }
    });

    // Top Corretores
    const corLabels = @json(collect($topCorretores)->pluck('nome'));
    const corData   = @json(collect($topCorretores)->pluck('total'));

    new Chart(document.getElementById('chartCorretores'), {
      type: 'bar',
      data: {
        labels: corLabels,
        datasets: [{ label: 'Interações', data: corData }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        indexAxis: 'y',
        scales: { x: { beginAtZero: true } }
      }
    });
  </script>
</x-app-layout>
