{{-- resources/views/public/galeria.blade.php --}}
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>
        @if($empreendimento && $empreendimento->nome)
            {{ $empreendimento->nome }}
        @else
            Empreendimento #{{ $empreendimentoId }}
        @endif
        – Galeria
    </title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- CSS bem simples só pra ficar apresentável --}}
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        .page {
            max-width: 960px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        }

        h1 {
            font-size: 1.5rem;
            margin: 0 0 0.25rem 0;
        }

        .subtitle {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
        }

        .media-card {
            border-radius: 10px;
            overflow: hidden;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }

        .media-card img,
        .media-card video {
            display: block;
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #d1d5db;
        }

        .media-info {
            padding: 0.75rem 0.9rem 0.9rem;
            font-size: 0.85rem;
            color: #4b5563;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }

        .badge {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: 0.2rem 0.45rem;
            border-radius: 999px;
            background: #e5e7eb;
            color: #374151;
        }

        .empty {
            padding: 2rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.75rem;
            color: #9ca3af;
        }

        a.download-link {
            text-decoration: none;
            color: #2563eb;
            font-weight: 500;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">

        <h1>
            @if($empreendimento && $empreendimento->nome)
                {{ $empreendimento->nome }}
            @else
                Empreendimento #{{ $empreendimentoId }}
            @endif
        </h1>

        <div class="subtitle">
            Galeria compartilhada por
            @if($corretor && $corretor->name)
                {{ $corretor->name }}
            @else
                seu corretor
            @endif
        </div>

        <h2 style="font-size: 1rem; margin-bottom: 0.75rem;">Fotos e vídeos do imóvel</h2>

        @if($arquivos->isEmpty())
            <div class="empty">
                Ainda não há mídias disponíveis nesta galeria.
            </div>
        @else
            <div class="grid">
                @foreach($arquivos as $item)
                    <div class="media-card">
                        @if($item['tipo'] === 'foto')
                            <img src="{{ $item['url'] }}" alt="Foto do imóvel">
                        @elseif($item['tipo'] === 'video')
                            <video src="{{ $item['url'] }}" controls></video>
                        @else
                            <div style="padding: 1rem;">
                                Arquivo disponível<br>
                                <a href="{{ $item['url'] }}" target="_blank" class="download-link">
                                    Abrir arquivo
                                </a>
                            </div>
                        @endif

                        <div class="media-info">
                            <span class="badge">
                                @if($item['tipo'] === 'foto')
                                    Foto
                                @elseif($item['tipo'] === 'video')
                                    Vídeo
                                @else
                                    Arquivo
                                @endif
                            </span>

                            <!--- @if(!empty($item['data']))
                                <span>{{ $item['data'] }}</span>
                            @endif ---> 
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="footer">
            ImobAgent · 2026
        </div>

    </div>
</div>
</body>
</html>
