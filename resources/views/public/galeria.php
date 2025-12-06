<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>
        Galeria -
        @if($empreendimento && $empreendimento->nome)
            {{ $empreendimento->nome }}
        @else
            Empreendimento #{{ $empreendimentoId }}
        @endif
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 0;
            color: #111827;
        }
        .page {
            max-width: 1000px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 20px;
        }
        .header {
            margin-bottom: 16px;
        }
        .title {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 4px;
        }
        .subtitle {
            font-size: 13px;
            color: #6b7280;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        @media (min-width: 768px) {
            .grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        .item {
            background: #f9fafb;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .item img,
        .item video {
            width: 100%;
            height: 170px;
            object-fit: cover;
            display: block;
        }
        .item-meta {
            padding: 6px 8px;
            text-align: right;
            font-size: 11px;
            color: #6b7280;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            background: #eef2ff;
            color: #3730a3;
            margin-top: 6px;
        }
        .footer {
            margin-top: 16px;
            font-size: 11px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="header">
            <p class="title">
                @if($empreendimento && $empreendimento->nome)
                    {{ $empreendimento->nome }}
                @else
                    Empreendimento #{{ $empreendimentoId }}
                @endif
            </p>

            <p class="subtitle">
                Galeria compartilhada por
                @if($corretor && $corretor->name)
                    <strong>{{ $corretor->name }}</strong>
                @else
                    seu corretor
                @endif
            </p>

            <div class="badge">
                Fotos e vídeos do imóvel
            </div>
        </div>

        @if($arquivos->isEmpty())
            <p class="subtitle">
                Ainda não há mídias disponíveis nesta galeria.
            </p>
        @else
            <div class="grid">
                @foreach($arquivos as $item)
                    <div class="item">
                        @if($item['tipo'] === 'foto')
                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener">
                                <img src="{{ $item['url'] }}" alt="Foto do imóvel">
                            </a>
                        @elseif($item['tipo'] === 'video')
                            <video controls>
                                <source src="{{ $item['url'] }}">
                                Seu navegador não suporta vídeo.
                            </video>
                        @else
                            <div style="padding: 12px; font-size: 12px;">
                                <a href="{{ $item['url'] }}" target="_blank" rel="noopener">
                                    Abrir arquivo
                                </a>
                            </div>
                        @endif

                        <div class="item-meta">
                            @if(!empty($item['data']))
                                {{ \Carbon\Carbon::parse($item['data'])->format('d/m/Y H:i') }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="footer">
            ImobAgent · Galeria automática de mídias do corretor
        </div>
    </div>
</div>
</body>
</html>
