<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório IA - Imobiliária</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1, h2, h3 { margin: 0 0 4px 0; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .grid { display: flex; gap: 10px; }
        .grid > .card { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; }
        small { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Relatório de uso da IA</h1>
    <small>Período: {{ $dateFrom ?? '' }} até {{ $dateTo ?? '' }}</small>

    <div class="mb-4 grid">
        <div class="card">
            <h3>Total de conversas</h3>
            <strong>{{ number_format($kpis['totalConversas'] ?? 0, 0, ',', '.') }}</strong>
        </div>
        <div class="card">
            <h3>Corretores ativos</h3>
            <strong>{{ $kpis['corretoresAtivos'] ?? 0 }}</strong>
        </div>
        <div class="card">
            <h3>Tempo médio de resposta da IA</h3>
            <strong>
                @if(!empty($kpis['tempoMedioIA']))
                    {{ number_format($kpis['tempoMedioIA'], 1, ',', '.') }} s
                @else
                    n/d
                @endif
            </strong>
        </div>
        <div class="card">
            <h3>Média de perguntas por corretor</h3>
            <strong>{{ number_format($kpis['mediaPerguntasCorretor'] ?? 0, 1, ',', '.') }}</strong>
        </div>
    </div>

    <div class="card mb-4">
        <h2>Empreendimentos mais falados</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Empreendimento</th>
                    <th>Interações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topEmp as $i => $row)
                    <tr>
                        <td>{{ $i+1 }}</td>
                        <td>{{ $row->nome }}</td>
                        <td>{{ $row->total }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Sem dados no período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card mb-4">
        <h2>Corretores mais engajados</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Corretor</th>
                    <th>Interações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topCorretores as $i => $row)
                    <tr>
                        <td>{{ $i+1 }}</td>
                        <td>{{ $row->nome ?? 'Corretor' }}</td>
                        <td>{{ $row->total }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Sem dados no período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Resumo executivo</h2>
        <ul>
            @if(count($topEmp))
                <li>Empreendimento mais citado: <strong>{{ $topEmp[0]->nome }}</strong>.</li>
            @endif
            @if(count($topCorretores))
                <li>Corretor mais engajado com a IA: <strong>{{ $topCorretores[0]->nome }}</strong>.</li>
            @endif
            <li>Total de conversas: <strong>{{ number_format($kpis['totalConversas'] ?? 0, 0, ',', '.') }}</strong>.</li>
            <li>Corretores ativos: <strong>{{ $kpis['corretoresAtivos'] ?? 0 }}</strong>.</li>
            <li>Média de perguntas por corretor: <strong>{{ number_format($kpis['mediaPerguntasCorretor'] ?? 0, 1, ',', '.') }}</strong>.</li>
        </ul>
    </div>
</body>
</html>
