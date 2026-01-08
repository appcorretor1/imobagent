<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                Chat Simulador
            </h2>
            <div class="text-sm text-gray-500">
                Simulando conversa como WhatsApp
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-6">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden" style="height: 80vh; display: flex; flex-direction: column;">
            
            {{-- Header do Chat --}}
            <div class="bg-indigo-600 text-white px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center font-semibold">
                        {{ strtoupper(substr($user->name ?? 'A', 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-semibold">{{ $user->name ?? 'Admin' }}</div>
                        <div class="text-xs text-indigo-100">Simulador de Chat</div>
                    </div>
                </div>
                <div class="text-xs text-indigo-100">
                    <span id="status">Online</span>
                </div>
            </div>

            {{-- Área de Mensagens --}}
            <div id="messages-container" class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-4" style="background: #ece5dd url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAxMCAwIEwgMCAwIDAgMTAiIGZpbGw9Im5vbmUiIHN0cm9rZT0iI2QxZDVmNiIgc3Ryb2tlLXdpZHRoPSIwLjUiLz48L3BhdHRlcm4+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjZ3JpZCkiLz48L3N2Zz4=') repeat;">
                @foreach($messages as $message)
                    <div class="flex {{ $message->sender === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg shadow-sm {{ $message->sender === 'user' ? 'bg-indigo-500 text-white' : 'bg-white text-gray-800' }}">
                            <div class="text-sm whitespace-pre-wrap break-words">{{ $message->body }}</div>
                            <div class="text-xs mt-1 {{ $message->sender === 'user' ? 'text-indigo-100' : 'text-gray-500' }}">
                                {{ $message->created_at->format('H:i') }}
                            </div>
                        </div>
                    </div>
                @endforeach
                <div id="typing-indicator" class="hidden flex justify-start">
                    <div class="bg-white px-4 py-2 rounded-lg shadow-sm">
                        <div class="flex gap-1">
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0s"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Input de Mensagem --}}
            <div class="border-t border-gray-300 bg-white p-4">
                <form id="chat-form" class="flex items-end gap-2" onsubmit="return false;">
                    @csrf
                    <div class="flex-1">
                        <textarea 
                            id="message-input" 
                            name="message" 
                            rows="1" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                            placeholder="Digite sua mensagem..."
                            maxlength="5000"
                            required
                        ></textarea>
                    </div>
                    <button 
                        type="submit" 
                        id="send-button"
                        class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Enviar
                    </button>
                </form>
            </div>
        </div>

        {{-- Avisos e Informações --}}
        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="text-sm text-blue-800">
                <strong>💡 Dica:</strong> Este é um simulador de chat. Use comandos como:
                <ul class="mt-2 list-disc list-inside space-y-1">
                    <li><code class="bg-blue-100 px-1 rounded">mudar empreendimento</code> - Para ver lista de empreendimentos</li>
                    <li><code class="bg-blue-100 px-1 rounded">menu</code> - Para ver o menu de opções</li>
                    <li>Ou faça perguntas sobre o empreendimento selecionado</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Aguarda o DOM estar completamente carregado
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Chat Simulator JS: DOM carregado');
            
            const messagesContainer = document.getElementById('messages-container');
            const chatForm = document.getElementById('chat-form');
            const messageInput = document.getElementById('message-input');
            const sendButton = document.getElementById('send-button');
            const typingIndicator = document.getElementById('typing-indicator');

            if (!chatForm || !messageInput || !sendButton) {
                console.error('Chat Simulator JS: Elementos não encontrados!', {
                    chatForm: !!chatForm,
                    messageInput: !!messageInput,
                    sendButton: !!sendButton,
                });
                return;
            }

            console.log('Chat Simulator JS: Elementos encontrados', {
                chatFormId: chatForm.id,
                messageInputId: messageInput.id,
                sendButtonId: sendButton.id,
            });
            
            // Previne submit padrão também no nível do formulário
            chatForm.addEventListener('submit', function(e) {
                console.log('Chat Simulator JS: Prevenindo submit padrão (primeiro handler)');
                e.preventDefault();
                return false;
            }, true); // Use capture phase

            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Scroll para o final
            function scrollToBottom() {
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }

            // Adiciona mensagem na tela
            function addMessage(body, sender, time) {
                if (!messagesContainer) return;
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `flex ${sender === 'user' ? 'justify-end' : 'justify-start'}`;
                
                const isUser = sender === 'user';
                messageDiv.innerHTML = `
                    <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg shadow-sm ${isUser ? 'bg-indigo-500 text-white' : 'bg-white text-gray-800'}">
                        <div class="text-sm whitespace-pre-wrap break-words">${escapeHtml(body)}</div>
                        <div class="text-xs mt-1 ${isUser ? 'text-indigo-100' : 'text-gray-500'}">${time}</div>
                    </div>
                `;
                
                if (typingIndicator) {
                    messagesContainer.insertBefore(messageDiv, typingIndicator);
                } else {
                    messagesContainer.appendChild(messageDiv);
                }
                scrollToBottom();
            }

            // Escapa HTML para segurança
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML.replace(/\n/g, '<br>');
            }

            // Formata hora
            function formatTime() {
                const now = new Date();
                return now.getHours().toString().padStart(2, '0') + ':' + 
                       now.getMinutes().toString().padStart(2, '0');
            }

            // Envia mensagem - Múltiplas proteções
            chatForm.addEventListener('submit', async function(e) {
                console.log('Chat Simulator JS: Evento submit capturado');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                const message = messageInput.value.trim();
                console.log('Chat Simulator JS: Submit do formulário', { message, messageLength: message.length });
            
            if (!message) {
                console.log('Chat Simulator JS: Mensagem vazia, ignorando');
                return;
            }

            // Desabilita input
            sendButton.disabled = true;
            messageInput.disabled = true;
            console.log('Chat Simulator JS: Input desabilitado');

            // Adiciona mensagem do usuário
            addMessage(message, 'user', formatTime());
            console.log('Chat Simulator JS: Mensagem do usuário adicionada na tela');

            // Limpa input
            messageInput.value = '';
            messageInput.style.height = 'auto';

            // Mostra indicador de digitação
            typingIndicator.classList.remove('hidden');
            scrollToBottom();

            const url = '{{ route("admin.chat-simulator.send") }}';
            const csrfToken = '{{ csrf_token() }}';
            const payload = { message: message };
            
            console.log('Chat Simulator JS: Preparando requisição', {
                url,
                method: 'POST',
                hasCsrfToken: !!csrfToken,
                payload,
            });

            try {
                console.log('Chat Simulator JS: Enviando fetch...');
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload)
                });

                console.log('Chat Simulator JS: Resposta recebida', {
                    status: response.status,
                    statusText: response.statusText,
                    ok: response.ok,
                });

                const data = await response.json();
                console.log('Chat Simulator JS: Dados JSON recebidos', {
                    ok: data.ok,
                    hasBotResponse: !!data.botResponse,
                    error: data.error,
                    data,
                });

                // Remove indicador de digitação
                typingIndicator.classList.add('hidden');

                if (data.ok) {
                    console.log('Chat Simulator JS: Resposta OK, adicionando mensagem do bot');
                    // Adiciona resposta do bot
                    if (data.botResponse) {
                        console.log('Chat Simulator JS: Adicionando mensagem do bot', {
                            body: data.botResponse.body?.substring(0, 50),
                            sender: data.botResponse.sender,
                        });
                        addMessage(data.botResponse.body, 'bot', data.botResponse.created_at);
                    } else {
                        console.warn('Chat Simulator JS: Resposta OK mas sem botResponse');
                    }
                } else {
                    console.error('Chat Simulator JS: Resposta não OK', { error: data.error });
                    // Mostra erro
                    addMessage('Erro: ' + (data.error || 'Erro desconhecido'), 'bot', formatTime());
                }
            } catch (error) {
                console.error('Chat Simulator JS: Erro na requisição', {
                    error,
                    message: error.message,
                    stack: error.stack,
                });
                typingIndicator.classList.add('hidden');
                addMessage('Erro ao enviar mensagem. Tente novamente. Erro: ' + error.message, 'bot', formatTime());
            } finally {
                // Reabilita input
                sendButton.disabled = false;
                messageInput.disabled = false;
                messageInput.focus();
                console.log('Chat Simulator JS: Input reabilitado');
            }
            });

            // Proteção adicional: handler no botão
            sendButton.addEventListener('click', function(e) {
                console.log('Chat Simulator JS: Botão clicado');
                // Não previne aqui, deixa o submit acontecer para o handler do form capturar
            });

            // Scroll inicial
            scrollToBottom();

            // Foco no input ao carregar
            messageInput.focus();
            
            console.log('Chat Simulator JS: Inicialização completa');
        });
        
        // Log para verificar se o script está carregando
        console.log('Chat Simulator JS: Script carregado (fora do DOMContentLoaded)');
    </script>
</x-app-layout>
