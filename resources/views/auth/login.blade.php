<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Entrar • {{ config('app.name', 'ImobAgent') }}</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
            background-color: #f9fafb;
        }

        .container {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            background: white;
        }

        /* Left Side - Image Section */
        .left-side {
            width: 50%;
            position: relative;
            overflow: hidden;
            background: linear-gradient(to bottom right, #030213, #1f2937, #374151);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px;
            color: white;
        }

        .left-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('/images/login-bg.png');
            background-size: cover;
            background-position: center;
            opacity: 0.35;
            z-index: 0;
        }

        .left-side > * { position: relative; z-index: 1; }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
        }

        .logo-icon svg { width: 24px; height: 24px; stroke: white; }

        .brand-name { font-size: 24px; font-weight: 600; }

        .content-section { margin-top: auto; margin-bottom: auto; }

        .content-section h2 {
            font-size: 36px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 24px;
        }

        .content-section > p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.85);
            max-width: 480px;
            margin-bottom: 32px;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .feature-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 8px;
        }

        .feature-icon svg { width: 20px; height: 20px; stroke: white; }
        .feature-text { color: rgba(255, 255, 255, 0.9); }

        .stats-section {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .stat-item h3 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .stat-item p { font-size: 13px; color: rgba(255, 255, 255, 0.7); }

        .decorative-blur-1,
        .decorative-blur-2 {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            filter: blur(80px);
        }

        .decorative-blur-1 { top: 80px; right: 80px; width: 288px; height: 288px; }
        .decorative-blur-2 { bottom: 80px; left: 80px; width: 384px; height: 384px; }

        /* Right Side - Form Section */
        .right-side {
            width: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 64px;
        }

        .form-container { width: 100%; max-width: 448px; }
        .mobile-logo { display: none; }
        .form-header { margin-bottom: 28px; }

        .form-header h1 {
            font-size: 32px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
        }

        .form-header p { color: #717182; font-size: 16px; }

        .alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
        }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert ul { padding-left: 18px; }

        .form { display: flex; flex-direction: column; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { color: #374151; font-weight: 600; font-size: 14px; }

        .input-wrapper { position: relative; }
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }

        .input-icon svg { width: 20px; height: 20px; stroke: currentColor; }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border-radius: 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            font-size: 16px;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            background: white;
            border-color: #030213;
            box-shadow: 0 0 0 3px rgba(3, 2, 19, 0.1);
        }

        .form-input::placeholder { color: #9ca3af; }
        .password-input { padding-right: 48px; }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .toggle-password:hover { color: #4b5563; }
        .toggle-password svg { width: 20px; height: 20px; stroke: currentColor; }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #030213;
        }

        .remember-me span { font-size: 14px; color: #4b5563; transition: color 0.2s; }
        .remember-me:hover span { color: #111827; }

        .forgot-password {
            color: #030213;
            font-size: 14px;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .forgot-password:hover { opacity: 0.8; }

        .submit-btn {
            width: 100%;
            padding: 14px 16px;
            background: linear-gradient(to right, #030213, #374151);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .submit-btn:hover {
            box-shadow: 0 10px 25px rgba(3, 2, 19, 0.25);
            transform: translateY(-2px);
        }
        .submit-btn:active { transform: translateY(0); }

        .security-badge {
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #6b7280;
            font-size: 14px;
            text-align: center;
        }

        .security-badge svg { width: 16px; height: 16px; stroke: currentColor; }

        /* Responsive */
        @media (max-width: 1024px) {
            .left-side { display: none; }
            .right-side { width: 100%; }
            .mobile-logo {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 24px;
            }
            .mobile-logo .logo-icon { background: linear-gradient(to bottom right, #030213, #374151); }
            .mobile-logo .brand-name { color: #111827; }
        }

        @media (max-width: 640px) {
            .container { box-shadow: none; }
            .right-side { padding: 32px 24px; }
        }

        .hidden { display: none; }
    </style>
</head>
<body>
<div class="container">
    <!-- Left Side - Image -->
    <div class="left-side">
        <div class="logo-section">
            <div class="logo-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span class="brand-name">ImobAgent</span>
        </div>

        <div class="content-section">
            <h2>IA que transforma sua <br>operação imobiliária</h2>
            <p>Entre para acompanhar visitas, propostas, vendas e anotações — tudo sincronizado com as conversas do WhatsApp.</p>

            <div class="features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <span class="feature-text">Controle de leads, visitas e follow-ups</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <span class="feature-text">Pipeline de propostas e vendas em um só lugar</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <span class="feature-text">Segurança e auditoria de alterações</span>
                </div>
            </div>
        </div>

        <div class="stats-section">
            <div class="stat-item">
                <h3>CRM</h3>
                <p>Visitas, propostas e vendas</p>
            </div>
            <div class="stat-item">
                <h3>IA</h3>
                <p>Comandos e automações</p>
            </div>
            <div class="stat-item">
                <h3>WhatsApp</h3>
                <p>Operação no dia a dia</p>
            </div>
        </div>

        <div class="decorative-blur-1"></div>
        <div class="decorative-blur-2"></div>
    </div>

    <!-- Right Side - Login Form -->
    <div class="right-side">
        <div class="form-container">
            <!-- Mobile Logo -->
            <div class="mobile-logo">
                <div class="logo-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="brand-name">{{ config('app.name', 'ImobAgent') }}</span>
            </div>

            <div class="form-header">
                <h1>Entrar</h1>
                <p>Acesse o painel administrativo do seu Assistente</p>
            </div>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error">
                    <strong>Não foi possível entrar:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="form" method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <div class="input-wrapper">
                        <div class="input-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                            </svg>
                        </div>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="seu@email.com"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                        />
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <div class="input-wrapper">
                        <div class="input-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input password-input"
                            placeholder="Digite sua senha"
                            required
                            autocomplete="current-password"
                        />
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Mostrar/ocultar senha">
                            <svg class="eye-off" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg class="eye-on hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me" for="remember_me">
                        <input type="checkbox" id="remember_me" name="remember" {{ old('remember') ? 'checked' : '' }}/>
                        <span>Lembrar de mim</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="forgot-password" href="{{ route('password.request') }}">Esqueceu a senha?</a>
                    @endif
                </div>

                <button type="submit" class="submit-btn">Entrar</button>
            </form>

            <div class="security-badge">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span>Seus dados estão protegidos</span>
            </div>
        </div>
    </div>
</div>

<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeOff = document.querySelector('.eye-off');
    const eyeOn = document.querySelector('.eye-on');

    if (togglePassword && passwordInput && eyeOff && eyeOn) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeOff.classList.toggle('hidden');
            eyeOn.classList.toggle('hidden');
        });
    }
</script>
</body>
</html>
