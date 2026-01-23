<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bem-vindo ao ImobAgent') }}
        </h2>
    </x-slot>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(to bottom, #f9fafb, #ffffff);
            color: #1f2937;
            line-height: 1.6;
        }

        /* Main Container */
        .main-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .section {
            margin-bottom: 3rem;
        }

        /* Hero Section */
        .hero {
            text-align: center;
            padding: 2rem 0;
        }

        .hero-icon {
            width: 5rem;
            height: 5rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.25rem;
            margin-bottom: 1.5rem;
        }

        .hero h1 {
            font-size: 2.25rem;
            font-weight: 300;
            color: #111827;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.125rem;
            color: #4b5563;
            max-width: 42rem;
            margin: 0 auto;
        }

        /* Index Section */
        .index-section {
            background: linear-gradient(to bottom right, #dbeafe, #e0e7ff);
            border-radius: 1rem;
            padding: 2rem;
            border: 1px solid #bfdbfe;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .section-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: #2563eb;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 300;
            color: #111827;
        }

        .index-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .index-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .index-item:hover {
            background: #f9fafb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .index-number {
            width: 2rem;
            height: 2rem;
            background: #dbeafe;
            color: #2563eb;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .index-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .index-emoji {
            font-size: 1.25rem;
        }

        /* Content Sections */
        .content-section {
            border-top: 1px solid #e5e7eb;
            padding-top: 3rem;
        }

        .content-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .content-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(to bottom right, #3b82f6, #4f46e5);
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .content-title h2 {
            font-size: 1.875rem;
            font-weight: 300;
            color: #111827;
            margin-bottom: 0.5rem;
        }

        .content-title p {
            color: #6b7280;
        }

        .content-box {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.3s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .stat-value.indigo {
            color: #4f46e5;
        }

        .stat-value.green {
            color: #16a34a;
        }

        .stat-value.blue {
            color: #2563eb;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Feature List */
        .feature-list {
            margin: 1.5rem 0;
        }

        .feature-list ul {
            list-style: none;
            padding-left: 1rem;
        }

        .feature-list li {
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .feature-list .title {
            font-weight: 300;
        }

        .feature-list .description {
            color: #6b7280;
        }

        /* Tip Box */
        .tip-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 2rem;
        }

        .tip-box p {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .tip-box code {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            color: #374151;
            font-size: 0.75rem;
        }

        /* WhatsApp Section */
        .whatsapp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .whatsapp-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .whatsapp-card h3 {
            font-size: 1.125rem;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        /* WhatsApp Demo */
        .whatsapp-demo {
            background: #0b141a;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .whatsapp-header {
            background: #1f2c33;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .whatsapp-avatar {
            width: 2.5rem;
            height: 2.5rem;
            background: #00a884;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .whatsapp-info .name {
            color: white;
            font-size: 0.875rem;
        }

        .whatsapp-info .status {
            color: #8696a0;
            font-size: 0.75rem;
        }

        .whatsapp-messages {
            padding: 1rem;
            min-height: 300px;
            max-height: 500px;
            overflow-y: auto;
        }

        .message {
            display: flex;
            margin-bottom: 0.75rem;
        }

        .message.user {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: 80%;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
        }

        .message.user .message-bubble {
            background: #005c4b;
            color: #e9edef;
        }

        .message.bot .message-bubble {
            background: #1f2c33;
            color: #e9edef;
        }

        .message-text {
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .message-time {
            color: #8696a0;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            text-align: right;
        }

        /* Assistant Section */
        .assistant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 2rem;
        }

        .assistant-features {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 2rem;
        }

        .assistant-features h3 {
            font-size: 1.25rem;
            color: #111827;
            margin-bottom: 1rem;
        }

        .assistant-features p {
            color: #374151;
            margin-bottom: 1.5rem;
        }

        .assistant-features ul {
            list-style: none;
            padding: 0;
        }

        .assistant-features li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .assistant-features .bullet {
            color: #2563eb;
            margin-top: 0.25rem;
        }

        .tip-card {
            background: linear-gradient(to bottom right, #eef2ff, #fae8ff);
            border: 1px solid #c7d2fe;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .tip-card h4 {
            font-size: 1.125rem;
            color: #111827;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tip-card p {
            font-size: 0.875rem;
            color: #374151;
            line-height: 1.6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .whatsapp-grid,
            .assistant-grid {
                grid-template-columns: 1fr;
            }

            .index-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="min-h-screen" style="background: linear-gradient(to bottom, #f9fafb, #ffffff);">
        <div class="main-container">
            <!-- Hero Section -->
            <section class="section hero">
                <div class="hero-icon">🤖</div>
                <h1>Bem-vindo ao ImobAgent</h1>
                <p>Seu assistente inteligente para gestão imobiliária. Gerencie empreendimentos, atenda clientes e feche negócios com eficiência.</p>
            </section>

            <!-- Index Section -->
            <section class="section index-section">
                <div class="section-header">
                    <div class="section-icon">📋</div>
                    <h2>Índice</h2>
                </div>
                <div class="index-grid">
                    <a href="#dashboard" class="index-item">
                        <div class="index-number">1</div>
                        <div class="index-content">
                            <span class="index-emoji">📊</span>
                            <span>Dashboard</span>
                        </div>
                    </a>
                    <a href="#whatsapp" class="index-item">
                        <div class="index-number">2</div>
                        <div class="index-content">
                            <span class="index-emoji">💬</span>
                            <span>WhatsApp</span>
                        </div>
                    </a>
                    <a href="#crm" class="index-item">
                        <div class="index-number">3</div>
                        <div class="index-content">
                            <span class="index-emoji">👥</span>
                            <span>CRM</span>
                        </div>
                    </a>
                    <a href="#empreendimentos" class="index-item">
                        <div class="index-number">4</div>
                        <div class="index-content">
                            <span class="index-emoji">🏢</span>
                            <span>Empreendimentos</span>
                        </div>
                    </a>
                    <a href="#atalhos" class="index-item">
                        <div class="index-number">5</div>
                        <div class="index-content">
                            <span class="index-emoji">⚡</span>
                            <span>Atalhos</span>
                        </div>
                    </a>
                </div>
            </section>

            <!-- Dashboard Section -->
            <section id="dashboard" class="section content-section">
                <div class="content-header">
                    <div class="content-icon">📊</div>
                    <div class="content-title">
                        <h2>Dashboard</h2>
                        <p>Central de informações e métricas</p>
                    </div>
                </div>
                
                <div class="content-box">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value indigo">R$ 880k</div>
                            <div class="stat-label">Vendas do Mês</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value green">5</div>
                            <div class="stat-label">Visitas Agendadas</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value blue">2</div>
                            <div class="stat-label">Propostas Fechadas</div>
                        </div>
                    </div>

                    <div class="feature-list">
                        <p style="color: #374151; margin-bottom: 1rem;">O Dashboard é sua central de informações, onde você pode visualizar:</p>
                        <ul>
                            <li><span class="title">Visão geral:</span> <span class="description">Estatísticas e KPIs importantes</span></li>
                            <li><span class="title">Atividades recentes:</span> <span class="description">Visitas, propostas, vendas e anotações</span></li>
                            <li><span class="title">Métricas do mês:</span> <span class="description">Vendas, propostas fechadas, visitas agendadas</span></li>
                        </ul>
                    </div>

                    <div class="tip-box">
                        <p>💡 <span style="font-weight: 300;">Dica:</span> Acesse o Dashboard através do menu principal ou pela rota <code>/dashboard</code></p>
                    </div>
                </div>
            </section>

            <!-- WhatsApp Section -->
            <section id="whatsapp" class="section content-section">
                <div class="content-header">
                    <div class="content-icon" style="background: linear-gradient(to bottom right, #10b981, #059669);">💬</div>
                    <div class="content-title">
                        <h2>WhatsApp</h2>
                        <p>Interaja com o assistente via mensagens</p>
                    </div>
                </div>

                <div class="whatsapp-grid">
                    <!-- Example 1 -->
                    <div class="whatsapp-card">
                        <h3>📱 Selecionando um Empreendimento</h3>
                        <div class="whatsapp-demo">
                            <div class="whatsapp-header">
                                <div class="whatsapp-avatar">IA</div>
                                <div class="whatsapp-info">
                                    <div class="name">ImobAgent</div>
                                    <div class="status">online</div>
                                </div>
                            </div>
                            <div class="whatsapp-messages">
                                <div class="message user">
                                    <div class="message-bubble">
                                        <div class="message-text">Empreendimentos</div>
                                        <div class="message-time">10:30</div>
                                    </div>
                                </div>
                                <div class="message bot">
                                    <div class="message-bubble">
                                        <div class="message-text">
                                            Escolha o empreendimento enviando o <strong>número</strong>:<br><br>
                                            1. Paradizzo - Goiânia/GO<br>
                                            2. Jardins ABC - Goiânia/GO<br>
                                            3. Atelier Opus - Goiânia/GO
                                        </div>
                                        <div class="message-time">10:30</div>
                                    </div>
                                </div>
                                <div class="message user">
                                    <div class="message-bubble">
                                        <div class="message-text">3</div>
                                        <div class="message-time">10:31</div>
                                    </div>
                                </div>
                                <div class="message bot">
                                    <div class="message-bubble">
                                        <div class="message-text">
                                            ✅ Empreendimento selecionado:<br>
                                            <strong>*Paradizzo* — Goiânia/GO.</strong><br>
                                            O que deseja saber sobre ele?
                                        </div>
                                        <div class="message-time">10:31</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Example 2 -->
                    <div class="whatsapp-card">
                        <h3>❓ Fazendo Perguntas</h3>
                        <div class="whatsapp-demo">
                            <div class="whatsapp-header">
                                <div class="whatsapp-avatar">IA</div>
                                <div class="whatsapp-info">
                                    <div class="name">ImobAgent</div>
                                    <div class="status">online</div>
                                </div>
                            </div>
                            <div class="whatsapp-messages">
                                <div class="message user">
                                    <div class="message-bubble">
                                        <div class="message-text">qual o preço da unidade 301?</div>
                                        <div class="message-time">10:32</div>
                                    </div>
                                </div>
                                <div class="message bot">
                                    <div class="message-bubble">
                                        <div class="message-text">
                                            🏢 <em>PARADIZZO</em><br>
                                            A unidade 301 está disponível por <strong>R$ 520.000,00</strong>.<br><br>
                                            Deseja que eu gere uma proposta em PDF?
                                        </div>
                                        <div class="message-time">10:32</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Assistant Section -->
            <section id="crm" class="section content-section">
                <div class="content-header">
                    <div class="content-icon" style="background: linear-gradient(to bottom right, #a855f7, #ec4899);">🚀</div>
                    <div class="content-title">
                        <h2>Assistente do Corretor</h2>
                        <p>CRM completo integrado ao WhatsApp</p>
                    </div>
                </div>

                <div class="assistant-grid">
                    <!-- Demo -->
                    <div class="whatsapp-card">
                        <h3>🚀 Entrando no Assistente do Corretor</h3>
                        <div class="whatsapp-demo">
                            <div class="whatsapp-header">
                                <div class="whatsapp-avatar">IA</div>
                                <div class="whatsapp-info">
                                    <div class="name">ImobAgent</div>
                                    <div class="status">online</div>
                                </div>
                            </div>
                            <div class="whatsapp-messages">
                                <div class="message user">
                                    <div class="message-bubble">
                                        <div class="message-text">assistente</div>
                                        <div class="message-time">10:35</div>
                                    </div>
                                </div>
                                <div class="message bot">
                                    <div class="message-bubble">
                                        <div class="message-text">
                                            🤖 <em>Assistente</em><br><br>
                                            📋 <em>Menu do Assistente</em><br><br>
                                            1️⃣ <strong>Visitas</strong><br>
                                            &nbsp;&nbsp;• Agendar: <em>visita amanhã 15h com João</em><br>
                                            &nbsp;&nbsp;• Listar: <em>listar visitas hoje</em><br><br>
                                            2️⃣ <strong>Propostas</strong><br>
                                            &nbsp;&nbsp;• Nova: <em>proposta 520k para Maria</em><br><br>
                                            3️⃣ <strong>Vendas</strong><br>
                                            &nbsp;&nbsp;• Fechar: <em>venda fechada 480k cliente Ana</em><br><br>
                                            4️⃣ <strong>Follow-ups</strong><br>
                                            5️⃣ <strong>Anotações</strong><br>
                                            6️⃣ <strong>Resumo</strong>
                                        </div>
                                        <div class="message-time">10:35</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Features -->
                    <div>
                        <div class="assistant-features">
                            <h3>🎯 O que é o Assistente do Corretor?</h3>
                            <p>O Assistente do Corretor é um CRM completo integrado ao WhatsApp que permite gerenciar:</p>
                            <ul>
                                <li>
                                    <span class="bullet">•</span>
                                    <div>
                                        <span class="title">Visitas:</span> <span class="description">Agendar e listar visitas com clientes</span>
                                    </div>
                                </li>
                                <li>
                                    <span class="bullet">•</span>
                                    <div>
                                        <span class="title">Propostas:</span> <span class="description">Registrar e acompanhar propostas enviadas</span>
                                    </div>
                                </li>
                                <li>
                                    <span class="bullet">•</span>
                                    <div>
                                        <span class="title">Vendas:</span> <span class="description">Fechar vendas e registrar valores</span>
                                    </div>
                                </li>
                                <li>
                                    <span class="bullet">•</span>
                                    <div>
                                        <span class="title">Follow-ups:</span> <span class="description">Criar lembretes e tarefas</span>
                                    </div>
                                </li>
                                <li>
                                    <span class="bullet">•</span>
                                    <div>
                                        <span class="title">Anotações:</span> <span class="description">Salvar informações importantes</span>
                                    </div>
                                </li>
                                <li>
                                    <span class="bullet">•</span>
                                    <div>
                                        <span class="title">Resumos:</span> <span class="description">Visualizar estatísticas e KPIs</span>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div class="tip-card">
                            <h4>💡 Dica de Uso</h4>
                            <p>
                                Para acessar o Assistente, basta enviar a palavra 
                                <code style="background: white; border: 1px solid #d1d5db; border-radius: 0.25rem; padding: 0.25rem 0.5rem; color: #111827; font-size: 0.75rem;">assistente</code> 
                                no chat do WhatsApp. Você verá um menu com todas as opções disponíveis.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="section content-section">
                <div class="content-header">
                    <div class="content-icon" style="background: linear-gradient(to bottom right, #3b82f6, #06b6d4);">✨</div>
                    <div class="content-title">
                        <h2>Recursos Principais</h2>
                        <p>Explore todas as funcionalidades do sistema</p>
                    </div>
                </div>

                <div class="whatsapp-grid">
                    <!-- Criando uma Venda -->
                    <div class="whatsapp-card">
                        <h3>📝 Criando uma Venda</h3>
                        <div class="whatsapp-demo">
                            <div class="whatsapp-header">
                                <div class="whatsapp-avatar">IA</div>
                                <div class="whatsapp-info">
                                    <div class="name">ImobAgent</div>
                                    <div class="status">online</div>
                                </div>
                            </div>
                            <div class="whatsapp-messages">
                                <div class="message user">
                                    <div class="message-bubble">
                                        <div class="message-text">venda 480k cliente Ana Silva</div>
                                        <div class="message-time">10:33</div>
                                    </div>
                                </div>
                                <div class="message bot">
                                    <div class="message-bubble">
                                        <div class="message-text">
                                            ✅ <strong>Venda Registrada</strong><br><br>
                                            💰 Valor: <strong>R$ 480.000</strong><br>
                                            👤 Cliente: Ana Silva<br>
                                            🏢 Empreendimento: Paradizzo<br>
                                            📅 Data: 23/01/2026<br><br>
                                            Parabéns pela venda! 🎉
                                        </div>
                                        <div class="message-time">10:33</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Agendando Visita -->
                    <div class="whatsapp-card">
                        <h3>📅 Agendando Visita</h3>
                        <div class="whatsapp-demo">
                            <div class="whatsapp-header">
                                <div class="whatsapp-avatar">IA</div>
                                <div class="whatsapp-info">
                                    <div class="name">ImobAgent</div>
                                    <div class="status">online</div>
                                </div>
                            </div>
                            <div class="whatsapp-messages">
                                <div class="message user">
                                    <div class="message-bubble">
                                        <div class="message-text">visita amanhã 15h com João</div>
                                        <div class="message-time">10:34</div>
                                    </div>
                                </div>
                                <div class="message bot">
                                    <div class="message-bubble">
                                        <div class="message-text">
                                            ✅ <strong>Visita Agendada</strong><br><br>
                                            👤 Cliente: João<br>
                                            📅 Data: 24/01/2026<br>
                                            🕒 Horário: 15:00<br>
                                            🏢 Local: Paradizzo<br><br>
                                            Lembrete criado! 📲
                                        </div>
                                        <div class="message-time">10:34</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gerando Proposta -->
                    <div class="whatsapp-card">
                        <h3>📄 Gerando Proposta</h3>
                        <div class="whatsapp-demo">
                            <div class="whatsapp-header">
                                <div class="whatsapp-avatar">IA</div>
                                <div class="whatsapp-info">
                                    <div class="name">ImobAgent</div>
                                    <div class="status">online</div>
                                </div>
                            </div>
                            <div class="whatsapp-messages">
                                <div class="message user">
                                    <div class="message-bubble">
                                        <div class="message-text">proposta 520k para Maria</div>
                                        <div class="message-time">10:36</div>
                                    </div>
                                </div>
                                <div class="message bot">
                                    <div class="message-bubble">
                                        <div class="message-text">
                                            📄 <strong>Proposta Gerada</strong><br><br>
                                            👤 Cliente: Maria<br>
                                            💰 Valor: R$ 520.000<br>
                                            🏢 Empreendimento: Paradizzo<br>
                                            📎 Unidade: 301<br><br>
                                            PDF pronto para envio! 📧
                                        </div>
                                        <div class="message-time">10:36</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visualizando Resumo -->
                    <div class="whatsapp-card">
                        <h3>📊 Visualizando Resumo</h3>
                        <div class="whatsapp-demo">
                            <div class="whatsapp-header">
                                <div class="whatsapp-avatar">IA</div>
                                <div class="whatsapp-info">
                                    <div class="name">ImobAgent</div>
                                    <div class="status">online</div>
                                </div>
                            </div>
                            <div class="whatsapp-messages">
                                <div class="message user">
                                    <div class="message-bubble">
                                        <div class="message-text">resumo</div>
                                        <div class="message-time">10:37</div>
                                    </div>
                                </div>
                                <div class="message bot">
                                    <div class="message-bubble">
                                        <div class="message-text">
                                            📊 <strong>Resumo do Mês</strong><br><br>
                                            💰 Vendas: <strong>R$ 1.2M</strong><br>
                                            📝 Propostas: 8 enviadas<br>
                                            📅 Visitas: 12 agendadas<br>
                                            ✅ Taxa conversão: 45%<br><br>
                                            Você está indo muito bem! 🚀
                                        </div>
                                        <div class="message-time">10:37</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Documentation Section -->
            <section class="section content-section">
                <div class="content-header">
                    <div class="content-icon" style="background: linear-gradient(to bottom right, #f59e0b, #ea580c);">📚</div>
                    <div class="content-title">
                        <h2>Documentação Completa</h2>
                        <p>Tudo que você precisa saber sobre o sistema</p>
                    </div>
                </div>

                <!-- Documentation Topics -->
                <div class="whatsapp-grid" style="margin-bottom: 3rem;">
                    <!-- Dashboard -->
                    <div class="whatsapp-card" style="transition: all 0.3s;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 2.5rem;">📊</span>
                            <h4 style="font-size: 1.25rem; font-weight: 300; color: #111827;">Dashboard</h4>
                        </div>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">Visualize métricas, KPIs e acompanhe o desempenho das vendas</p>
                        <ul style="list-style: none; padding: 0; space-y: 0.5rem;">
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Vendas do mês em tempo real</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Visitas agendadas e confirmadas</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Propostas enviadas e fechadas</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Gráficos e relatórios interativos</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Empreendimentos -->
                    <div class="whatsapp-card" style="transition: all 0.3s;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 2.5rem;">🏢</span>
                            <h4 style="font-size: 1.25rem; font-weight: 300; color: #111827;">Empreendimentos</h4>
                        </div>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">Gerencie todos os seus empreendimentos em um só lugar</p>
                        <ul style="list-style: none; padding: 0;">
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Cadastro completo de empreendimentos</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Gestão de unidades disponíveis</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Tabela de preços e condições</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Documentação e arquivos</span>
                            </li>
                        </ul>
                    </div>

                    <!-- CRM -->
                    <div class="whatsapp-card" style="transition: all 0.3s;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 2.5rem;">👥</span>
                            <h4 style="font-size: 1.25rem; font-weight: 300; color: #111827;">CRM</h4>
                        </div>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">Organize e acompanhe seus clientes de forma eficiente</p>
                        <ul style="list-style: none; padding: 0;">
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Cadastro detalhado de clientes</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Histórico de interações</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Follow-ups automáticos</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Pipeline de vendas</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Chat Simulador -->
                    <div class="whatsapp-card" style="transition: all 0.3s;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 2.5rem;">💬</span>
                            <h4 style="font-size: 1.25rem; font-weight: 300; color: #111827;">Chat Simulador</h4>
                        </div>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">Teste o assistente antes de usar com clientes reais</p>
                        <ul style="list-style: none; padding: 0;">
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Ambiente de testes seguro</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Simule conversas completas</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Aprenda os comandos principais</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.25rem;">✓</span>
                                <span>Veja respostas em tempo real</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Quick Tips -->
                <div class="whatsapp-grid" style="margin-bottom: 3rem;">
                    <!-- Comandos Rápidos -->
                    <div style="background: linear-gradient(to bottom right, #dbeafe, #e0e7ff); border: 1px solid #bfdbfe; border-radius: 1rem; padding: 2rem;">
                        <h4 style="font-size: 1.25rem; font-weight: 300; color: #111827; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1.5rem;">💡</span>
                            Comandos Rápidos
                        </h4>
                        <ul style="list-style: none; padding: 0; space-y: 0.75rem;">
                            <li style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.125rem;">→</span>
                                <span>Digite 'assistente' para acessar o menu principal</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.125rem;">→</span>
                                <span>Use 'resumo' para ver estatísticas do dia</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.125rem;">→</span>
                                <span>Digite 'visitas hoje' para listar agendamentos</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.125rem;">→</span>
                                <span>Use números para navegar entre opções</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Boas Práticas -->
                    <div style="background: linear-gradient(to bottom right, #dbeafe, #e0e7ff); border: 1px solid #bfdbfe; border-radius: 1rem; padding: 2rem;">
                        <h4 style="font-size: 1.25rem; font-weight: 300; color: #111827; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1.5rem;">💡</span>
                            Boas Práticas
                        </h4>
                        <ul style="list-style: none; padding: 0;">
                            <li style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.125rem;">→</span>
                                <span>Sempre confirme os dados antes de enviar</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.125rem;">→</span>
                                <span>Use o chat simulador para testar novos recursos</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.125rem;">→</span>
                                <span>Mantenha o cadastro de clientes atualizado</span>
                            </li>
                            <li style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #374151;">
                                <span style="color: #2563eb; margin-top: 0.125rem;">→</span>
                                <span>Revise propostas antes de enviar aos clientes</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Support Section -->
                <div style="background: linear-gradient(to right, #a855f7, #ec4899); border-radius: 1rem; padding: 2rem; text-align: center; color: white;">
                    <h4 style="font-size: 1.5rem; font-weight: 300; margin-bottom: 1rem;">Precisa de Ajuda?</h4>
                    <p style="color: #f3e8ff; margin-bottom: 1.5rem; max-width: 42rem; margin-left: auto; margin-right: auto;">
                        Nossa equipe está pronta para ajudar você a aproveitar ao máximo o ImobAgent
                    </p>
                    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem;">
                        <button style="background: white; color: #a855f7; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 500; border: none; cursor: pointer; transition: background 0.3s;">
                            📧 Enviar Email
                        </button>
                        <button style="background: rgba(255, 255, 255, 0.2); color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 500; border: none; cursor: pointer; transition: background 0.3s; backdrop-filter: blur(4px);">
                            💬 Chat ao Vivo
                        </button>
                        <button style="background: rgba(255, 255, 255, 0.2); color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 500; border: none; cursor: pointer; transition: background 0.3s; backdrop-filter: blur(4px);">
                            📱 WhatsApp
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
