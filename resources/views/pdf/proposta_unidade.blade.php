<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Proposta Comercial - {{ $imobiliariaNome ?? 'Imobiliária' }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700;800&display=swap');
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 0;
            padding: 0;
            size: A4 landscape;
        }

        * {
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
            orphans: 0 !important;
            widows: 0 !important;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: auto;
            overflow: visible;
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
        }

        .container {
            width: 100%;
            background: white;
            margin: 0;
            padding: 0;
            height: auto;
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
        }

        .header, .section, .footer, .value-highlight, .payment-table, .payment-row, table, tr, td, div, p, h1, h2, h3, h4, h5, h6 {
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
            break-inside: avoid !important;
            break-after: avoid !important;
            break-before: avoid !important;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: auto;
            min-height: auto;
            font-family: "Urbanist", "DejaVu Sans", sans-serif;
            background: #f5f7fa;
            color: #111827;
            line-height: 1.4;
        }

        body {
            padding: 0;
        }

        .container {
            width: 100%;
            background: white;
            margin: 0;
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 30px 40px;
            position: relative;
            overflow: hidden;
            height: 350px;
            min-height: 350px;
            max-height: 350px;
        }

        .header-bg {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: #000000;
        }

        .header-bg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.4;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .header-top {
            width: 100%;
            border-collapse: collapse;
        }

        .header-top td {
            vertical-align: top;
        }

        .header-logo {
            margin-bottom: 8px;
        }

        .header-title {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .header-subtitle {
            margin: 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .header-info {
            background: rgba(255, 255, 255, 0.2);
            padding: 16px 24px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: right;
        }

        .header-info h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 8px 0;
        }

        .header-date {
            font-size: 13px;
            opacity: 0.95;
            margin-bottom: 4px;
        }

        .header-confidential {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            opacity: 0.9;
            text-transform: uppercase;
        }

        /* Section */
        .section {
            padding: 20px 40px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section.gray {
            background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
        }

        .section.light-gray {
            background: #fafafa;
            padding: 15px 40px;
            color: #111827;
            margin-top: 100px;
            page-break-before: always !important;
        }

        .section.light-gray .section-title {
            color: #374151;
        }

        .section.light-gray * {
            color: inherit;
        }

        .section.light-gray .value-highlight,
        .section.light-gray .payment-table {
            color: #111827;
        }

        .section.green {
            background: #f0fdf4;
            page-break-before: always !important;
            margin-top: 100px;
        }

        .section-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 1px;
            font-weight: 700;
            margin: 0 0 18px 0;
        }

        .section-title i {
            margin-right: 6px;
            font-size: 12px;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            font-size: 12px;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .card-heading {
            font-size: 36px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: -1px;
            margin: 0 0 24px 0;
        }

        /* Grid */
        .grid {
            width: 100%;
            border-collapse: collapse;
        }

        .grid-2 td {
            width: 50%;
            vertical-align: top;
            padding-right: 24px;
            padding-bottom: 24px;
        }

        .grid-4-single-row td {
            width: 25%;
            vertical-align: top;
            padding-right: 12px;
            padding-bottom: 0;
        }

        .grid-3 td {
            width: 33.33%;
            vertical-align: top;
            padding-right: 16px;
            padding-bottom: 12px;
        }

        .grid-4 td {
            width: 25%;
            vertical-align: top;
            padding-right: 24px;
            padding-bottom: 24px;
        }

        .grid-contact td {
            width: 33.33%;
            vertical-align: top;
            padding-right: 24px;
            padding-bottom: 24px;
        }

        /* Info Item */
        .info-item {
            display: block;
        }

        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .badge-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Feature Box */
        .feature-box {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }

        /* Value Highlight */
        .value-highlight {
            background: #ffffff;
            color: #111827;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .value-highlight-subtitle {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #6b7280;
        }

        .value-highlight-label {
            font-size: 12px;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 600;
        }

        .value-highlight-price {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -1px;
            color: #1e3a8a;
        }

        /* Payment Table */
        .payment-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            color: #111827 !important;
        }

        .payment-table * {
            color: #111827 !important;
        }

        .payment-row {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-row td {
            padding: 12px 24px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            color: #111827 !important;
        }

        .payment-row.highlight td {
            background: #f9fafb;
        }

        .payment-row:last-child td {
            border-bottom: none;
        }

        .payment-left {
            width: 1%;
            text-align: left;
            white-space: nowrap;
            padding-right: 20px;
            color: #111827 !important;
        }

        .payment-right {
            width: 99%;
            text-align: right;
            white-space: nowrap;
        }

        .payment-title {
            font-size: 13px;
            font-weight: 600;
            color: #111827 !important;
            margin-bottom: 4px;
        }


        .payment-value {
            font-size: 16px;
            font-weight: 700;
            color: #111827 !important;
            white-space: nowrap;
        }

        .payment-value.blue {
            color: #1e3a8a;
        }

        /* Alert Box */
        .alert {
            padding: 20px 24px;
            border-radius: 10px;
            margin-top: 24px;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
        }

        .alert-title {
            font-size: 12px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert-text {
            margin: 0;
            font-size: 13px;
            color: #78350f;
            line-height: 1.6;
        }

        /* Features */
        .feature-item {
            background: #f0fdf4;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
            margin-bottom: 12px;
        }

        .feature-text {
            font-size: 14px;
            color: #166534;
            font-weight: 500;
        }

        /* Contact Card */
        .contact-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #d1fae5;
        }

        .contact-label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin: 0 0 8px 0;
        }

        .contact-value {
            font-size: 16px;
            color: #111827;
            font-weight: 600;
            margin: 0;
        }

        /* Notes Box */
        .notes-box {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border-left: 4px solid #10b981;
        }

        .notes-title {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin: 0 0 12px 0;
        }

        .notes-text {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.7;
            margin: 0;
        }

        /* Validity Box */
        .validity-box {
            margin-top: 24px;
            text-align: center;
            padding: 16px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 8px;
        }

        .validity-text {
            margin: 0;
            font-size: 13px;
            color: #047857;
            font-weight: 600;
        }

        /* Footer */
        .footer {
            padding: 20px 40px;
            background: #111827;
            color: white;
            text-align: center;
        }

        .footer-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .footer-info {
            margin: 0;
            font-size: 13px;
            opacity: 0.8;
        }

        .footer-copyright {
            margin: 8px 0 0 0;
            font-size: 11px;
            opacity: 0.6;
        }

        /* Section Header */
        .section-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .section-header td {
            vertical-align: middle;
        }

        .section-header-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            @if(!empty($empreendimentoBanner))
                <div class="header-bg">
                    <img src="{{ $empreendimentoBanner }}" alt="{{ $empreendimentoNome }}">
                </div>
            @endif
            <div class="header-content">
                <table class="header-top">
                    <tr>
                        <td>
                            <div class="header-logo">
                                <h1 class="header-title">{{ $imobiliariaNome ?? 'Imobiliária' }}</h1>
                            </div>
                            <p class="header-subtitle">Sua propriedade dos sonhos está aqui</p>
                        </td>
                        <td style="text-align: right;">
                            <div class="header-info">
                                <h2>Proposta Comercial</h2>
                                <div class="header-date">📅 {{ $hoje ?? date('d/m/Y') }}</div>
                                <div class="header-confidential">Documento Confidencial</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Resumo Executivo -->
        <div class="section gray">
            <h3 class="section-title">Resumo do Imóvel</h3>

            <div class="card">
                <div style="margin-bottom: 24px;">
                    <div class="card-title">Empreendimento</div>
                    <h2 class="card-heading">{{ $empreendimentoNome }}</h2>
                </div>

                <table class="grid grid-4-single-row">
                    <tr>
                        <td>
                            <div class="info-item">
                                <div class="info-label">Unidade</div>
                                <div class="info-value">{{ $unidade }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="info-item">
                                <div class="info-label">Torre / Bloco</div>
                                <div class="info-value">{{ $torre ?: '' }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="info-item">
                                <div class="info-label">📍 Localização</div>
                                <div class="info-value">{{ $cidadeUf ?: '' }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div>
                                    <span class="badge badge-warning">✓ Em Análise</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Características -->
        <div class="section">
            <h3 class="section-title">Características</h3>

            <table class="grid grid-3">
                <tr>
                    <td>
                        <div class="feature-box">
                            <div class="info-label">Área Total</div>
                            <div class="info-value" style="font-size: 18px;">— m²</div>
                        </div>
                    </td>
                    <td>
                        <div class="feature-box">
                            <div class="info-label">Dormitórios</div>
                            <div class="info-value" style="font-size: 18px;">—</div>
                        </div>
                    </td>
                    <td>
                        <div class="feature-box">
                            <div class="info-label">Suítes</div>
                            <div class="info-value" style="font-size: 18px;">—</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="feature-box">
                            <div class="info-label">Vagas de Garagem</div>
                            <div class="info-value" style="font-size: 18px;">—</div>
                        </div>
                    </td>
                    <td>
                        <div class="feature-box">
                            <div class="info-label">Andar</div>
                            <div class="info-value" style="font-size: 18px;">—</div>
                        </div>
                    </td>
                    <td>
                        <div class="feature-box">
                            <div class="info-label">Entrega Prevista</div>
                            <div class="info-value" style="font-size: 18px;">—</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Plano de Pagamento -->
        @php
            $linhasBrutas = explode("\n", (string)$textoPagamento);
            $linhasProcessadas = [];

            // Processa linhas para juntar labels e valores que estão separados
            $i = 0;
            while ($i < count($linhasBrutas)) {
                $linha = trim($linhasBrutas[$i]);
                
                if ($linha === '') {
                    $i++;
                    continue;
                }

                // Remove emojis e caracteres especiais do início
                $linhaLimpa = preg_replace('/^[🔹💰\*\s]+/', '', $linha);
                $linhaLimpa = trim($linhaLimpa);

                // Se a linha tem ":", já está formatada (label: valor)
                if (strpos($linhaLimpa, ':') !== false) {
                    $linhasProcessadas[] = $linhaLimpa;
                    $i++;
                } else {
                    // Pode ser um label, verifica se a próxima linha (não vazia) é um valor
                    $proximaLinha = '';
                    $j = $i + 1;
                    while ($j < count($linhasBrutas) && $proximaLinha === '') {
                        $proximaLinha = trim($linhasBrutas[$j]);
                        $j++;
                    }
                    
                    // Se a próxima linha parece um valor (tem R$ ou números ou "x de")
                    if ($proximaLinha !== '' && (preg_match('/R\$\s*\d|^\d+[xX]\s+de|^\d+[,\d\.]+/', $proximaLinha))) {
                        $linhasProcessadas[] = $linhaLimpa . ': ' . $proximaLinha;
                        $i = $j; // Pula até a linha após o valor
                    } else {
                        // Linha única, pode ser label ou valor
                        $linhasProcessadas[] = $linhaLimpa;
                        $i++;
                    }
                }
            }

            $linhaPrincipal = $linhasProcessadas[0] ?? null;
            $demaisLinhas   = array_slice($linhasProcessadas, 1);

            $lpLabel = null;
            $lpValor = null;
            if ($linhaPrincipal) {
                [$lpLabel, $lpValor] = array_pad(explode(':', $linhaPrincipal, 2), 2, null);
                $lpLabel = trim($lpLabel ?? '');
                $lpValor = trim($lpValor ?? '');
            }
        @endphp

        @if(!empty($linhasProcessadas))
            <div class="section light-gray" style="margin-top: 80px;">
                <table class="section-header">
                    <tr>
                        <td>
                            <h3 class="section-title" style="margin: 0; color: #374151;">
                                💰 Plano de Pagamento
                            </h3>
                        </td>
                        <td class="section-header-right">
                            <span class="badge badge-blue">Condições Simuladas</span>
                        </td>
                    </tr>
                </table>

                <!-- Valor Total Destaque -->
                @if($lpLabel && $lpValor)
                    <div class="value-highlight">
                        <div class="value-highlight-label">{{ $lpLabel }}</div>
                        <div class="value-highlight-price">{{ $lpValor }}</div>
                    </div>
                @endif

                <!-- Detalhamento de Pagamento -->
                <table class="payment-table">
                    @if(!empty($demaisLinhas))
                        @foreach($demaisLinhas as $linha)
                            @php
                                [$label, $valor] = array_pad(explode(':', $linha, 2), 2, null);
                                $label = trim($label ?? '');
                                $valor = trim($valor ?? '');
                                $isHighlight = stripos($label, 'financiamento') !== false || stripos($label, 'sinal') !== false && stripos($label, 'ato') !== false;
                            @endphp
                            @if($label || $valor)
                                <tr class="payment-row @if($isHighlight) highlight @endif">
                                    <td class="payment-left" style="color: #111827 !important;">
                                        <div class="payment-title" style="color: #111827 !important;">{{ $label ?: '' }}</div>
                                    </td>
                                    <td class="payment-right" style="color: #111827 !important;">
                                        <div class="payment-value @if($isHighlight) blue @endif" style="color: #111827 !important;">{{ $valor ?: '' }}</div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    @endif
                </table>

                <!-- Alerta -->
                <div class="alert alert-warning">
                    <div class="alert-title">⚠️ Importante</div>
                    <p class="alert-text">
                        Os valores apresentados são estimados e podem sofrer alterações. O financiamento bancário está sujeito à aprovação de crédito pela instituição financeira.
                    </p>
                </div>
            </div>
        @endif

        <!-- Contato -->
        <div class="section green">
            <h3 class="section-title">Atendimento e Contato</h3>

            <table class="grid grid-contact" style="margin-bottom: 24px;">
                <tr>
                    <td>
                        <div class="contact-card">
                            <h4 class="contact-label">Corretor Responsável</h4>
                            <p class="contact-value">{{ $corretorNome ?: 'Equipe Comercial' }}</p>
                        </div>
                    </td>
                    @if(!empty($corretorTelefone))
                        <td>
                            <div class="contact-card">
                                <h4 class="contact-label">Telefone</h4>
                                <p class="contact-value">{{ $corretorTelefone }}</p>
                            </div>
                        </td>
                    @endif
                    <td>
                        <div class="contact-card">
                            <h4 class="contact-label">E-mail</h4>
                            <p class="contact-value">{{ $imobiliariaSite ?: 'contato@imobiliaria.com.br' }}</p>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Observações -->
            <div class="notes-box">
                <h4 class="notes-title">Observações Importantes</h4>
                <p class="notes-text">
                    Esta proposta comercial é válida por 7 dias corridos a partir da data de emissão. Os valores, condições de pagamento e disponibilidade da unidade estão sujeitos à confirmação e atualização da tabela de vendas. A aprovação do financiamento bancário depende da análise de crédito da instituição financeira escolhida. Memorial descritivo, plantas e demais informações técnicas disponíveis mediante solicitação. Imagens meramente ilustrativas.
                </p>
            </div>

            <!-- Validade -->
            <div class="validity-box">
                <p class="validity-text">
                    📅 Proposta válida até: <strong>{{ date('d/m/Y', strtotime('+7 days')) }}</strong>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-title">{{ $imobiliariaNome ?? 'Imobiliária' }}</div>
            <p class="footer-info">
                @if(!empty($imobiliariaSite))
                    {{ $imobiliariaSite }}
                @else
                    CRECI 00000 • contato@imobiliaria.com.br
                @endif
            </p>
            <p class="footer-copyright">
                © {{ date('Y') }} {{ $imobiliariaNome ?? 'Imobiliária' }}. Todos os direitos reservados.
            </p>
        </div>
    </div>
</body>
</html>
