<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Proposta - {{ $empreendimentoNome }}</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        body {
            background: #f3f4f6;
            color: #111827;
            font-size: 11px;
        }

        .page {
            width: 100%;
            padding: 0;
            background: #ffffff;
            margin: 0;
            box-sizing: border-box;
            position: relative;
        }

        .page-content {
            padding: 24px 28px;
        }

        @page {
            margin: 0;
            padding: 0;
            size: A4;
        }

        /* HEADER */
        .header {
            background: #4f46e5;
            background-image: linear-gradient(135deg, #4f46e5 0%, #5b21b6 100%);
            padding: 18px 20px;
            border-radius: 10px;
            color: #ffffff;
            margin-bottom: 18px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-left,
        .header-right {
            vertical-align: top;
        }

        .header-right {
            text-align: right;
        }

        .logo-row {
            vertical-align: middle;
        }

        .logo-img {
            max-height: 42px;
            width: auto;
            background: #ffffff;
            padding: 6px;
            border-radius: 6px;
        }

        .logo-name {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
        }

        .logo-tag {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.85);
        }

        .doc-title h1 {
            margin: 0 0 4px 0;
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
        }

        .doc-title span {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.9);
        }

        .badge {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
        }

        /* SEÇÕES */
        .section {
            margin-bottom: 16px;
            padding: 16px 18px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            margin-bottom: 8px;
        }

        .section-title span {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            text-transform: none;
            letter-spacing: 0;
        }

        .section-empreendimento {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .section-atendimento {
            background: #ecfdf5;
            border-color: #bbf7d0;
        }

        /* GRID EM TABELA */
        .two-col-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .two-col-table td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }

        .info-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #9ca3af;
            font-weight: 600;
            padding-right: 8px;
            white-space: nowrap;
        }

        .info-value {
            font-size: 12px;
            font-weight: 600;
            color: #111827;
            text-align: right;
        }

        .info-row {
            margin-bottom: 8px;
        }

        .info-row-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-row-table td {
            vertical-align: top;
            padding: 0;
        }

        .info-value strong {
            font-weight: 700;
            color: #4f46e5;
        }

        .pill {
            display: inline-block;
            font-size: 9px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
        }

        /* PAGAMENTO */
        .section-payment {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .payment-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .payment-header-left {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
        }

        .payment-header-right {
            text-align: right;
        }

        .payment-header-badge {
            display: inline-block;
            font-size: 9px;
            padding: 3px 9px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1e40af;
            font-weight: 600;
            border: 1px solid #93c5fd;
        }

        .payment-main {
            margin-top: 6px;
            margin-bottom: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid #bfdbfe;
        }

        .payment-main-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            font-weight: 600;
            padding-right: 8px;
            white-space: nowrap;
            vertical-align: middle;
        }

        .payment-main-value {
            font-size: 22px;
            font-weight: 700;
            color: #4f46e5;
            text-align: right;
            vertical-align: middle;
        }

        .payment-main-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .payment-main-table td {
            padding: 0;
        }

        .payment-main-table td:first-child {
            width: auto;
            white-space: nowrap;
            padding-right: 12px;
        }

        .payment-main-table td:last-child {
            width: 100%;
            text-align: right;
            padding-left: 12px;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-left: 0;
            margin-right: 0;
        }

        .payment-table tr td {
            font-size: 11px;
            padding: 6px 0;
            border-bottom: 1px solid #e0e7ff;
            vertical-align: middle;
        }

        .payment-table tr:last-child td {
            border-bottom: none;
        }

        .payment-label {
            color: #64748b;
            font-weight: 500;
            white-space: nowrap;
            padding-right: 12px;
            vertical-align: middle;
            text-align: left;
        }

        .payment-value {
            text-align: right !important;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
            vertical-align: middle;
            padding-left: 12px;
        }

        /* WHATSAPP CHIP */
        .whatsapp-chip {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 8px;
            background: #10b981;
            color: #ffffff;
            font-size: 10px;
            font-weight: 600;
            margin-top: 6px;
        }

        /* RODAPÉ */
        .footer {
            border-top: 1px solid #e5e7eb;
            margin-top: 18px;
            padding-top: 10px;
            font-size: 9px;
            color: #6b7280;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-left span {
            display: block;
        }

        .footer-left span:first-child {
            font-weight: 700;
            color: #374151;
            font-size: 10px;
        }

        .footer-right {
            text-align: right;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="page-content">
    {{-- HEADER --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <table>
                        <tr class="logo-row">
                            <td>
                                @if(!empty($imobiliariaLogo))
                                    <img src="{{ $imobiliariaLogo }}" class="logo-img" alt="Logo {{ $imobiliariaNome }}">
                                @endif
                            </td>
                            <td>
                                <div class="logo-name">{{ $imobiliariaNome }}</div>
                                @if(!empty($imobiliariaSite))
                                    <div class="logo-tag">{{ $imobiliariaSite }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="header-right">
                    <div class="doc-title">
                        <h1>Proposta Comercial</h1>
                        <span>{{ $hoje ?? '' }}</span><br>
                        <span class="badge">Uso exclusivo do cliente</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- EMPREENDIMENTO / UNIDADE --}}
    <div class="section section-empreendimento">
        <div class="section-title">
            EMPREENDIMENTO <span>· {{ $empreendimentoNome }}</span>
        </div>

        <table class="two-col-table">
            <tr>
                <td>
                    <div class="info-row">
                        <table class="info-row-table">
                            <tr>
                                <td class="info-label">Unidade</td>
                                <td class="info-value">
                                    Unidade <strong>{{ $unidade }}</strong>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="info-row">
                        <table class="info-row-table">
                            <tr>
                                <td class="info-label">Torre / Bloco</td>
                                <td class="info-value">
                                    {{ $torre ?: '—' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="info-row">
                        <table class="info-row-table">
                            <tr>
                                <td class="info-label">Localização</td>
                                <td class="info-value">{{ $cidadeUf ?: '—' }}</td>
                            </tr>
                        </table>
                    </div>

                    <div class="info-row">
                        <table class="info-row-table">
                            <tr>
                                <td class="info-label">Status da proposta</td>
                                <td class="info-value">
                                    <span class="pill">Proposta em análise</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- INFORMAÇÕES DE PAGAMENTO --}}
    @php
        $linhasBrutas = explode("\n", (string)$textoPagamento);
        $linhas = [];

        foreach ($linhasBrutas as $l) {
            $t = trim($l);
            if ($t !== '') {
                $linhas[] = $t;
            }
        }

        $linhaPrincipal = $linhas[0] ?? null;
        $demaisLinhas   = array_slice($linhas, 1);

        $lpLabel = null;
        $lpValor = null;
        if ($linhaPrincipal) {
            [$lpLabel, $lpValor] = array_pad(explode(':', $linhaPrincipal, 2), 2, null);
            $lpLabel = trim($lpLabel ?? '');
            $lpValor = trim($lpValor ?? '');
        }
    @endphp

    @if(!empty($linhas))
        <div class="section section-payment">
            <table class="payment-header">
                <tr>
                    <td class="payment-header-left">
                        INFORMAÇÕES DE PAGAMENTO
                    </td>
                    <td class="payment-header-right">
                        <span class="payment-header-badge">Condições simuladas</span>
                    </td>
                </tr>
            </table>

            @if($linhaPrincipal)
                <div class="payment-main">
                    @if($lpLabel && $lpValor)
                        <table class="payment-main-table" style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td class="payment-main-label" style="white-space: nowrap; padding-right: 12px; vertical-align: middle;">{{ $lpLabel }}</td>
                                <td class="payment-main-value" style="text-align: right; white-space: nowrap; vertical-align: middle; width: 100%;">{{ $lpValor }}</td>
                            </tr>
                        </table>
                    @else
                        <div class="payment-main-value" style="text-align: left;">{{ $linhaPrincipal }}</div>
                    @endif
                </div>
            @endif

            @if(!empty($demaisLinhas))
                <table class="payment-table" style="width: 100%; border-collapse: collapse;">
                    @foreach($demaisLinhas as $linha)
                        @php
                            [$label, $valor] = array_pad(explode(':', $linha, 2), 2, null);
                            $label = trim($label ?? '');
                            $valor = trim($valor ?? '');
                        @endphp
                        <tr style="display: table-row;">
                            <td class="payment-label" style="white-space: nowrap; padding-right: 12px; vertical-align: middle;">
                                {{ $label ?: $linha }}
                            </td>
                            <td class="payment-value" style="text-align: right; white-space: nowrap; vertical-align: middle; width: 100%;">
                                {{ $valor ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endif

    {{-- ATENDIMENTO / NEGOCIAÇÃO --}}
    <div class="section section-atendimento">
        <div class="section-title">ATENDIMENTO E NEGOCIAÇÃO</div>

        <table class="two-col-table">
            <tr>
                <td>
                    <div class="info-row">
                        <table class="info-row-table">
                            <tr>
                                <td class="info-label">Corretor responsável</td>
                                <td class="info-value">
                                    {{ $corretorNome ?: 'Equipe comercial' }}
                                </td>
                            </tr>
                        </table>
                    </div>

                    @if(!empty($corretorTelefone))
                        <div class="info-row">
                            <table class="info-row-table">
                                <tr>
                                    <td class="info-label">Contato</td>
                                    <td class="info-value">
                                        {{ $corretorTelefone }}<br>
                                        <span class="whatsapp-chip">Atendimento via WhatsApp</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    @endif
                </td>
                <td>
                    <div class="info-row">
                        <table class="info-row-table">
                            <tr>
                                <td class="info-label">Observações</td>
                                <td class="info-value" style="font-size: 10px; line-height: 1.5; color:#4b5563; font-weight: 500; text-align: left;">
                                    Esta proposta é ilustrativa e está sujeita à aprovação de crédito,
                                    atualização da tabela de vendas e disponibilidade da unidade.
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- RODAPÉ --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    <span>{{ $imobiliariaNome }}</span>
                    @if(!empty($imobiliariaSite))
                        <span>{{ $imobiliariaSite }}</span>
                    @endif
                </td>
                <td class="footer-right">
                    <span>Documento gerado automaticamente</span>
                    <span>Não requer assinatura</span>
                </td>
            </tr>
        </table>
    </div>
    </div>

</div>
</body>
</html>
