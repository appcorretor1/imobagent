<?php

namespace App\Http\Controllers;

use App\Models\WhatsappThread;
use App\Models\User;
use App\Models\Empreendimento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ChatSimulatorController extends Controller
{
    protected array $capturedResponses = [];
    
    /**
     * Captura resposta do sendText (público para ser acessado pela classe anônima)
     */
    public function captureResponse(string $text)
    {
        $this->capturedResponses[] = $text;
        Log::info('Chat Simulator: Resposta capturada em captureResponse', [
            'text_preview' => substr($text, 0, 200),
            'text_length' => strlen($text),
            'count' => count($this->capturedResponses),
        ]);
    }

    /**
     * Mostra a tela do chat simulador
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Acesso negado');
        }

        // Busca ou cria uma thread para o admin
        $phone = $user->whatsapp ?? $user->phone ?? 'admin_' . $user->id;
        $thread = $this->getOrCreateThread($phone, $user);

        // Busca histórico de mensagens da thread APENAS do simulador
        // Por padrão, não mostra mensagens antigas - apenas do simulador
        // Usa whereJsonContains que funciona melhor com Laravel
        $messages = \App\Models\WhatsappMessage::where('thread_id', $thread->id)
            ->whereJsonContains('meta->simulator', true)
            ->orderBy('created_at', 'asc')
            ->get();
        
        // Se não houver mensagens do simulador, retorna array vazio
        // Isso evita mostrar mensagens antigas de conversas reais do WhatsApp

        return view('admin.chat-simulator.index', [
            'thread' => $thread,
            'messages' => $messages,
            'user' => $user,
        ]);
    }

    /**
     * Envia uma mensagem através do simulador
     */
    public function sendMessage(Request $request)
    {
        Log::info('Chat Simulator: sendMessage chamado', [
            'has_message' => $request->has('message'),
            'message_preview' => substr($request->input('message', ''), 0, 50),
            'all_inputs' => array_keys($request->all()),
        ]);

        try {
            $request->validate([
                'message' => 'required|string|max:5000',
            ]);
            Log::info('Chat Simulator: Validação passou');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Chat Simulator: Erro de validação', [
                'errors' => $e->errors(),
            ]);
            return response()->json(['ok' => false, 'error' => 'Mensagem inválida', 'errors' => $e->errors()], 422);
        }

        $user = Auth::user();
        Log::info('Chat Simulator: Usuário autenticado', [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
        ]);
        
        if (!$user) {
            Log::error('Chat Simulator: Usuário não autenticado');
            return response()->json(['ok' => false, 'error' => 'Usuário não autenticado'], 403);
        }

        $phone = $user->whatsapp ?? $user->phone ?? 'admin_' . $user->id;
        $message = trim($request->input('message'));
        
        Log::info('Chat Simulator: Dados preparados', [
            'phone' => $phone,
            'message' => substr($message, 0, 100),
            'message_length' => strlen($message),
        ]);

        // Busca ou cria thread
        try {
            $thread = $this->getOrCreateThread($phone, $user);
            $thread->refresh();
            Log::info('Chat Simulator: Thread obtida', [
                'thread_id' => $thread->id,
                'thread_state' => $thread->state,
                'selected_emp' => $thread->selected_empreendimento_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Chat Simulator: Erro ao obter thread', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['ok' => false, 'error' => 'Erro ao obter thread: ' . $e->getMessage()], 500);
        }

        // Salva mensagem do usuário
        try {
            $userMessage = \App\Models\WhatsappMessage::create([
                'thread_id' => $thread->id,
                'company_id' => $user->company_id ?? 1,
                'corretor_id' => $user->id,
                'phone' => $phone,
                'sender' => 'user',
                'type' => 'text',
                'body' => $message,
                'meta' => ['simulator' => true],
            ]);
            Log::info('Chat Simulator: Mensagem do usuário salva', [
                'message_id' => $userMessage->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Chat Simulator: Erro ao salvar mensagem do usuário', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['ok' => false, 'error' => 'Erro ao salvar mensagem: ' . $e->getMessage()], 500);
        }

        try {
            Log::info('Chat Simulator: Iniciando processamento da mensagem');
            // Processa mensagem
            $response = $this->processMessage($thread, $message, $phone, $user);
            Log::info('Chat Simulator: Processamento concluído', [
                'response_preview' => substr($response['body'] ?? '', 0, 100),
            ]);

            return response()->json([
                'ok' => true,
                'userMessage' => [
                    'id' => $userMessage->id,
                    'body' => $userMessage->body,
                    'sender' => $userMessage->sender,
                    'created_at' => $userMessage->created_at->format('H:i:s'),
                ],
                'botResponse' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Chat Simulator: Erro ao processar mensagem', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Retorna erro em formato JSON mesmo em caso de exceção
            return response()->json([
                'ok' => false,
                'error' => 'Erro ao processar mensagem. Tente novamente.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        } catch (\Throwable $e) {
            Log::error('Chat Simulator: Erro fatal ao processar mensagem', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Retorna erro em formato JSON mesmo em caso de erro fatal
            return response()->json([
                'ok' => false,
                'error' => 'Erro ao processar mensagem. Tente novamente.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Processa a mensagem usando a lógica do WppController
     */
    protected function processMessage(WhatsappThread $thread, string $message, string $phone, User $user)
    {
        Log::info('Chat Simulator: processMessage iniciado', [
            'thread_id' => $thread->id,
            'message_preview' => substr($message, 0, 50),
            'phone' => $phone,
        ]);

        $text = trim($message);
        $norm = $this->normalizeText($text);
        
        Log::info('Chat Simulator: Texto normalizado', [
            'text' => substr($text, 0, 50),
            'norm' => substr($norm, 0, 50),
        ]);
        
        // Limpa respostas capturadas anteriormente
        $this->capturedResponses = [];
        Log::info('Chat Simulator: Respostas capturadas limpas');
        
        // Atualiza thread com dados do usuário
        try {
            $this->updateThreadWithUser($thread);
            $thread->refresh();
            Log::info('Chat Simulator: Thread atualizada', [
                'state' => $thread->state,
                'selected_emp' => $thread->selected_empreendimento_id,
            ]);
        } catch (\Exception $e) {
            Log::warning('Chat Simulator: Erro ao atualizar thread', ['error' => $e->getMessage()]);
        }
        
        try {
            Log::info('Chat Simulator: Chamando processMessageDirectly');
            // Cria um WppController usando reflection para usar métodos protegidos
            // Mas em vez de estender, vamos chamar os métodos diretamente
            $responseText = $this->processMessageDirectly($thread, $phone, $text, $norm, $user);
            Log::info('Chat Simulator: processMessageDirectly retornou', [
                'response_text' => $responseText ? substr($responseText, 0, 50) : 'null',
                'captured_count' => count($this->capturedResponses),
            ]);
            
            // Pega todas as respostas capturadas (pode haver múltiplas, ex: PDF + mensagem de confirmação)
            if (!empty($this->capturedResponses)) {
                // Se houver múltiplas respostas, junta todas (ex: link do PDF + mensagem de confirmação)
                if (count($this->capturedResponses) > 1) {
                    $responseText = implode("\n\n", $this->capturedResponses);
                    Log::info('Chat Simulator: Múltiplas respostas capturadas, concatenando', [
                        'count' => count($this->capturedResponses),
                    ]);
                } else {
                    $responseText = $this->capturedResponses[0];
                }
                Log::info('Chat Simulator: Usando resposta(s) capturada(s)', [
                    'count' => count($this->capturedResponses),
                    'text_preview' => substr($responseText, 0, 100),
                ]);
            } else {
                // Se não capturou resposta, usa a resposta direta ou padrão
                $responseText = $responseText ?? "Mensagem recebida. Por favor, aguarde enquanto processamos.";
                Log::warning('Chat Simulator: Nenhuma resposta capturada, usando resposta direta ou padrão', [
                    'response_text' => substr($responseText, 0, 100),
                ]);
            }
            
        } catch (\Throwable $e) {
            Log::error('Chat Simulator: Erro no processamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Não expõe detalhes do erro ao usuário, apenas mensagem genérica
            $responseText = "Erro ao processar mensagem. Por favor, tente novamente.";
            
            // Se for erro de campo não encontrado (migration não executada), tenta continuar
            if (strpos($e->getMessage(), 'zapi_path_token') !== false || 
                strpos($e->getMessage(), "doesn't exist") !== false ||
                strpos($e->getMessage(), "Unknown column") !== false) {
                Log::warning('Chat Simulator: Campo zapi_path_token não existe, usando fallback', [
                    'error' => $e->getMessage()
                ]);
                // Tenta processar novamente sem o campo
                try {
                    $responseText = $this->processMessageDirectly($thread, $phone, $text, $norm, $user) 
                        ?? "Mensagem recebida. Por favor, aguarde enquanto processamos.";
                } catch (\Throwable $e2) {
                    Log::error('Chat Simulator: Erro ao reprocessar após erro de campo', [
                        'error' => $e2->getMessage()
                    ]);
                }
            }
        }
        
        // Salva resposta do bot
        try {
            $botMessage = \App\Models\WhatsappMessage::create([
                'thread_id' => $thread->id,
                'company_id' => $thread->company_id ?? $thread->tenant_id ?? $user->company_id ?? 1,
                'corretor_id' => $thread->corretor_id ?? $thread->user_id ?? $user->id,
                'phone' => $phone,
                'sender' => 'ia',
                'type' => 'text',
                'body' => $responseText,
                'meta' => ['simulator' => true],
            ]);
            Log::info('Chat Simulator: Mensagem do bot salva', [
                'bot_message_id' => $botMessage->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Chat Simulator: Erro ao salvar mensagem do bot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return [
            'id' => $botMessage->id,
            'body' => $botMessage->body,
            'sender' => $botMessage->sender,
            'created_at' => $botMessage->created_at->format('H:i:s'),
        ];
    }

    /**
     * Processa mensagem diretamente usando métodos do WppController
     * Simplificado para usar a mesma lógica do WhatsApp
     */
    protected function processMessageDirectly(WhatsappThread $thread, string $phone, string $text, string $norm, User $user): ?string
    {
        Log::info('Chat Simulator: processMessageDirectly iniciado', [
            'thread_state' => $thread->state,
            'selected_emp' => $thread->selected_empreendimento_id,
        ]);

        // Cria uma instância do WppController que captura respostas
        try {
            $simulator = $this; // Referência para usar na closure
            $wppController = new class($simulator) extends \App\Http\Controllers\WppController {
                protected ChatSimulatorController $simulator;
                
                public function __construct(ChatSimulatorController $simulator) {
                    $this->simulator = $simulator;
                    Log::info('Chat Simulator: WppController simulado criado');
                }
                
                protected function sendText(string $phone, string $text, ?int $companyId = null, ?WhatsappThread $thread = null): array
                {
                    // Busca thread se não foi passado
                    if (!$thread) {
                        try {
                            $thread = WhatsappThread::where('phone', $phone)->first();
                        } catch (\Throwable $e) {
                            // Ignora erro
                        }
                    }
                    
                    // Adiciona breadcrumb no início da mensagem
                    $breadcrumb = $this->buildBreadcrumb($thread);
                    $textWithBreadcrumb = $breadcrumb . $text;
                    
                    Log::info('Chat Simulator: sendText chamado (simulado)', [
                        'text_preview' => substr($textWithBreadcrumb, 0, 100),
                        'text_length' => strlen($textWithBreadcrumb),
                        'company_id' => $companyId,
                    ]);
                    // Chama o método público captureResponse
                    $this->simulator->captureResponse($textWithBreadcrumb);
                    return ['ok' => true, 'simulated' => true];
                }
                
                protected function sendMediaSmart(string $phone, string $pathOrUrl, string $caption = '', ?string $mime = null, string $disk = 's3'): array
                {
                    Log::info('Chat Simulator: sendMediaSmart chamado (simulado)', [
                        'path' => substr($pathOrUrl, 0, 100),
                        'caption' => substr($caption, 0, 50),
                        'mime' => $mime,
                        'disk' => $disk,
                    ]);
                    
                    try {
                        // Resolve URL pública do arquivo
                        $publicUrl = $pathOrUrl;
                        
                        // Se já for uma URL completa, usa como está
                        if (!preg_match('#^https?://#i', $pathOrUrl)) {
                            // Tenta gerar URL temporária do S3
                            $candidates = array_values(array_filter([$disk, 's3', 'public']));
                            
                            foreach ($candidates as $d) {
                                try {
                                    $storage = \Illuminate\Support\Facades\Storage::disk($d);
                                    
                                    if (method_exists($storage, 'temporaryUrl')) {
                                        $publicUrl = $storage->temporaryUrl($pathOrUrl, now()->addHours(24));
                                        Log::info('Chat Simulator: URL temporária gerada', [
                                            'disk' => $d,
                                            'url_preview' => substr($publicUrl, 0, 100),
                                        ]);
                                        break;
                                    } elseif (method_exists($storage, 'url')) {
                                        $publicUrl = $storage->url($pathOrUrl);
                                        Log::info('Chat Simulator: URL pública gerada', [
                                            'disk' => $d,
                                            'url_preview' => substr($publicUrl, 0, 100),
                                        ]);
                                        break;
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('Chat Simulator: Erro ao gerar URL do disco ' . $d, [
                                        'error' => $e->getMessage(),
                                    ]);
                                    continue;
                                }
                            }
                        }
                        
                        // Captura a mensagem com o link do arquivo
                        $message = "📄 *{$caption}*\n\n";
                        $message .= "🔗 Link para download:\n{$publicUrl}";
                        
                        Log::info('Chat Simulator: Capturando mensagem do PDF', [
                            'message_preview' => substr($message, 0, 150),
                        ]);
                        
                        $this->simulator->captureResponse($message);
                        
                        return [
                            'ok' => true,
                            'simulated' => true,
                            'url' => $publicUrl,
                            'via' => 'simulator',
                        ];
                    } catch (\Exception $e) {
                        Log::error('Chat Simulator: Erro ao processar sendMediaSmart', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        
                        $message = "📄 *{$caption}*\n\n";
                        $message .= "⚠️ Erro ao gerar link do arquivo. O arquivo foi gerado mas não foi possível obter o link.";
                        
                        $this->simulator->captureResponse($message);
                        
                        return [
                            'ok' => false,
                            'error' => $e->getMessage(),
                            'simulated' => true,
                        ];
                    }
                }
            };
            Log::info('Chat Simulator: WppController simulado instanciado com sucesso');
        } catch (\Exception $e) {
            Log::error('Chat Simulator: Erro ao criar WppController simulado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
        
        try {
            $reflection = new \ReflectionClass($wppController);
            Log::info('Chat Simulator: Reflection criado');
        } catch (\Exception $e) {
            Log::error('Chat Simulator: Erro ao criar reflection', ['error' => $e->getMessage()]);
            throw $e;
        }
        
        // Vincula corretor à thread
        try {
            Log::info('Chat Simulator: Tentando vincular corretor');
            $attachMethod = $reflection->getMethod('attachCorretorToThread');
            $attachMethod->setAccessible(true);
            $attachMethod->invoke($wppController, $thread, $phone);
            $thread->refresh();
            Log::info('Chat Simulator: Corretor vinculado com sucesso');
        } catch (\Exception $e) {
            Log::warning('Chat Simulator: Erro ao vincular corretor', [
                'error' => $e->getMessage(),
                'method_exists' => $reflection->hasMethod('attachCorretorToThread'),
            ]);
        }
        
        // ===== TRATAR RESPOSTA AO MENU (1-8) =====
        // IMPORTANTE: Se o menu foi mostrado mas a mensagem NÃO é um número 1-8 (qualquer pergunta/texto),
        // limpa a flag automaticamente e marca para ignorar comandos especiais, permitindo que a IA processe a mensagem normalmente.
        $ctx = $thread->context ?? [];
        $menuWasShown = !empty(data_get($ctx, 'shortcut_menu.shown_at'));
        if ($menuWasShown && !preg_match('/^\s*[1-9]\s*$/', $norm)) {
            // Limpa a flag do menu e marca que devemos ignorar comandos especiais nesta mensagem
            // para que seja processada pela IA normalmente
            $ctx = $thread->context ?? [];
            unset($ctx['shortcut_menu']);
            // Marca flag temporária para ignorar comandos especiais nesta mensagem
            $ctx['ignore_special_commands'] = true;
            $thread->context = $ctx;
            $thread->save();
            Log::info('Chat Simulator: Limpou flag do menu, mensagem não é opção 1-8 - processando normalmente pela IA', [
                'phone' => $phone, 
                'norm' => substr($norm, 0, 50)
            ]);
            // Recarrega o contexto atualizado
            $ctx = $thread->context ?? [];
        }
        
        // ===== RESUMO (sempre disponível) - ANTES DE QUALQUER PROCESSAMENTO =====
        // Ignora comando "resumo" se acabamos de limpar a flag do menu (mensagem deve ser processada pela IA)
        // Garante que $ctx está definido
        if (!isset($ctx)) {
            $ctx = $thread->context ?? [];
        }
        $shouldIgnoreSpecialCommands = !empty(data_get($ctx, 'ignore_special_commands'));
        try {
            $isResumoMethod = $reflection->getMethod('isResumoCommand');
            $isResumoMethod->setAccessible(true);
            $isResumo = $isResumoMethod->invoke($wppController, $norm);
            
            if (!$shouldIgnoreSpecialCommands && $isResumo) {
                Log::info('Chat Simulator: É comando resumo');
                $buildResumoMethod = $reflection->getMethod('buildResumoText');
                $buildResumoMethod->setAccessible(true);
                $resumoText = $buildResumoMethod->invoke($wppController, $thread);
                
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone, $resumoText);
                return null;
            }
        } catch (\Exception $e) {
            Log::warning('Chat Simulator: Erro ao processar resumo', ['error' => $e->getMessage()]);
        }
        
        // ===== MENU DE ATALHOS (só com empreendimento selecionado) - ANTES DE QUALQUER PROCESSAMENTO =====
        // Ignora comando "menu" se acabamos de limpar a flag do menu (mensagem deve ser processada pela IA)
        try {
            $isMenuMethod = $reflection->getMethod('isShortcutMenuCommand');
            $isMenuMethod->setAccessible(true);
            $isMenu = $isMenuMethod->invoke($wppController, $norm);
            
            if (!$shouldIgnoreSpecialCommands && $isMenu) {
                Log::info('Chat Simulator: É comando menu');
                
                // Se estiver no modo CRM, o "menu" aqui deve ser o MENU DO ASSISTENTE (não o menu do empreendimento)
                $ctx = $thread->context ?? [];
                $isCrmMode = data_get($ctx, 'crm_mode', false);
                if ($isCrmMode) {
                    // garante corretor
                    if (empty($thread->corretor_id)) {
                        $attachCorretorMethod = $reflection->getMethod('attachCorretorToThread');
                        $attachCorretorMethod->setAccessible(true);
                        $attachCorretorMethod->invoke($wppController, $thread, $phone);
                        $thread->refresh();
                    }

                    $corretor = $thread->corretor_id ? User::find($thread->corretor_id) : null;
                    if (!$corretor) {
                        $sendTextMethod = $reflection->getMethod('sendText');
                        $sendTextMethod->setAccessible(true);
                        $sendTextMethod->invoke(
                            $wppController,
                            $phone,
                            "⚠️ Não consegui identificar seu usuário corretor.\nVerifique se seu número está cadastrado corretamente na plataforma.",
                            null,
                            $thread
                        );
                        return null;
                    }

                    $assistente = new \App\Services\Crm\AssistenteService(
                        new \App\Services\Crm\SimpleNlpParser(),
                        new \App\Services\Crm\CommandRouter(),
                    );

                    $resposta = $assistente->processar($thread, $corretor, 'menu');

                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, $resposta, null, $thread);
                    return null;
                }

                // Menu só funciona se tiver empreendimento selecionado
                if (empty($thread->selected_empreendimento_id)) {
                    $footerMethod = $reflection->getMethod('footerControls');
                    $footerMethod->setAccessible(true);
                    $footer = $footerMethod->invoke($wppController);
                    
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, 
                        "⚠️ Para acessar o menu, primeiro selecione um empreendimento.\n\n" .
                        "Digite *mudar empreendimento* para ver a lista de empreendimentos disponíveis." . $footer
                    );
                    return null;
                }
                
                $buildMenuMethod = $reflection->getMethod('buildShortcutMenuText');
                $buildMenuMethod->setAccessible(true);
                $menuText = $buildMenuMethod->invoke($wppController, $thread);
                
                // Marca no contexto que o último comando foi "menu"
                $ctx = $thread->context ?? [];
                $ctx['shortcut_menu'] = [
                    'shown_at' => now()->toIso8601String(),
                ];
                $thread->context = $ctx;
                $thread->save();
                
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone, $menuText);
                return null;
            }
        } catch (\Exception $e) {
            Log::warning('Chat Simulator: Erro ao processar menu', ['error' => $e->getMessage()]);
        }
        
        // Verifica comando de mudar empreendimento
        try {
            Log::info('Chat Simulator: Verificando comando mudar empreendimento', ['norm' => substr($norm, 0, 50)]);
            $isChangeMethod = $reflection->getMethod('isChangeEmpreendimento');
            $isChangeMethod->setAccessible(true);
            $isChange = $isChangeMethod->invoke($wppController, $norm);
            Log::info('Chat Simulator: Resultado isChangeEmpreendimento', ['is_change' => $isChange]);
            
            if ($isChange) {
                Log::info('Chat Simulator: É comando mudar empreendimento, resetando');
                $resetMethod = $reflection->getMethod('resetEmpreendimento');
                $resetMethod->setAccessible(true);
                $resetMethod->invoke($wppController, $thread);
                $thread->refresh();
                
                Log::info('Chat Simulator: Enviando lista de empreendimentos');
                $listMethod = $reflection->getMethod('sendEmpreendimentosList');
                $listMethod->setAccessible(true);
                $listMethod->invoke($wppController, $thread);
                Log::info('Chat Simulator: Lista de empreendimentos enviada');
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Chat Simulator: Erro ao processar mudar empreendimento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        // Verifica se está aguardando escolha de empreendimento
        if ($thread->state === 'awaiting_emp_choice') {
            try {
                $extractMethod = $reflection->getMethod('extractIndexNumber');
                $extractMethod->setAccessible(true);
                $idx = $extractMethod->invoke($wppController, $norm);
                
                if ($idx) {
                    $ctx = $thread->context ?? [];
                    $map = data_get($ctx, 'emp_map', []);
                    if (isset($map[$idx])) {
                        $finalizeMethod = $reflection->getMethod('finalizeSelection');
                        $finalizeMethod->setAccessible(true);
                        $finalizeMethod->invoke($wppController, $thread, (int)$map[$idx]);
                        $thread->refresh();
                        return null;
                    }
                }
                
                // Verifica se mapa está válido
                $ctx = $thread->context ?? [];
                $mapOk = is_array(data_get($ctx, 'emp_map')) && count(data_get($ctx, 'emp_map', [])) > 0;
                
                if (!$mapOk) {
                    $listMethod = $reflection->getMethod('sendEmpreendimentosList');
                    $listMethod->setAccessible(true);
                    $listMethod->invoke($wppController, $thread);
                    $thread->refresh();
                } else {
                    $footerMethod = $reflection->getMethod('footerControls');
                    $footerMethod->setAccessible(true);
                    $footer = $footerMethod->invoke($wppController);
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, "Envie apenas o *número* do empreendimento (ex.: 1)." . $footer);
                }
            } catch (\Exception $e) {
                Log::error('Chat Simulator: Erro ao processar escolha', ['error' => $e->getMessage()]);
                try {
                    $listMethod = $reflection->getMethod('sendEmpreendimentosList');
                    $listMethod->setAccessible(true);
                    $listMethod->invoke($wppController, $thread);
                } catch (\Exception $e2) {
                    Log::error('Chat Simulator: Erro ao listar após erro', ['error' => $e2->getMessage()]);
                }
            }
            return null;
        }
        
        // Se não tem empreendimento selecionado, lista
        if (empty($thread->selected_empreendimento_id)) {
            try {
                $listMethod = $reflection->getMethod('sendEmpreendimentosList');
                $listMethod->setAccessible(true);
                $listMethod->invoke($wppController, $thread);
                $thread->refresh();
            } catch (\Exception $e) {
                Log::error('Chat Simulator: Erro ao listar empreendimentos', ['error' => $e->getMessage()]);
            }
            return null;
        }
        
        // ================== SUPER HARD-GATE: ARQUIVOS (sempre antes da IA) ==================
        // Replica a mesma lógica do WppController para processar comandos de arquivos
        if ($text !== '' && stripos(mb_strtolower($text), 'arquiv') !== false) {
            Log::info('Chat Simulator: SUPER-GATE arquivos acionado', ['phone' => $phone, 'msg' => $text]);
            
            try {
                $empId = (int) $thread->selected_empreendimento_id;
                $resolveCompanyMethod = $reflection->getMethod('resolveCompanyIdForThread');
                $resolveCompanyMethod->setAccessible(true);
                $companyId = $resolveCompanyMethod->invoke($wppController, $thread);
                
                $fileListKeyMethod = $reflection->getMethod('fileListKey');
                $fileListKeyMethod->setAccessible(true);
                $filesKey = $fileListKeyMethod->invoke($wppController, $phone, $empId);
                
                // A) Pedidos explícitos para listar
                if (preg_match('/\b(ver|listar|mostrar)\s+arquivos\b/i', $text)) {
                    $cacheAndBuildMethod = $reflection->getMethod('cacheAndBuildFilesList');
                    $cacheAndBuildMethod->setAccessible(true);
                    $listText = $cacheAndBuildMethod->invoke($wppController, $filesKey, $empId, $companyId);
                    
                    $footerMethod = $reflection->getMethod('footerControls');
                    $footerMethod->setAccessible(true);
                    $footer = $footerMethod->invoke($wppController);
                    
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, $listText . "\n\nResponda com os números (ex.: 1,2,5)." . $footer);
                    return null;
                }
                
                // B) Índices com lista em cache
                $isMultiIndexListMethod = $reflection->getMethod('isMultiIndexList');
                $isMultiIndexListMethod->setAccessible(true);
                if ($isMultiIndexListMethod->invoke($wppController, $text) && Cache::has($filesKey)) {
                    // Processa índices e envia arquivos
                    $parseIndicesMethod = $reflection->getMethod('parseIndices');
                    $parseIndicesMethod->setAccessible(true);
                    $indices = $parseIndicesMethod->invoke($wppController, $text);
                    
                    if (empty($indices)) {
                        $sendTextMethod = $reflection->getMethod('sendText');
                        $sendTextMethod->setAccessible(true);
                        $footerMethod = $reflection->getMethod('footerControls');
                        $footerMethod->setAccessible(true);
                        $footer = $footerMethod->invoke($wppController);
                        $sendTextMethod->invoke($wppController, $phone, 'Não entendi os números enviados. Tente algo como: 1,2,5' . $footer);
                        return null;
                    }
                    
                    $items = Cache::get($filesKey, []);
                    $byIdx = collect($items)->keyBy('index');
                    $picked = [];
                    foreach ($indices as $i) {
                        if ($byIdx->has($i)) {
                            $picked[] = $byIdx->get($i);
                        }
                    }
                    
                    if (empty($picked)) {
                        $sendTextMethod = $reflection->getMethod('sendText');
                        $sendTextMethod->setAccessible(true);
                        $footerMethod = $reflection->getMethod('footerControls');
                        $footerMethod->setAccessible(true);
                        $footer = $footerMethod->invoke($wppController);
                        $sendTextMethod->invoke($wppController, $phone, 'Esses índices não batem com a lista atual. Quer listar novamente? Diga: ver arquivos' . $footer);
                        return null;
                    }
                    
                    // Para o simulador, vamos apenas enviar os links dos arquivos
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, "⏳ Processando *" . count($picked) . "* item(s)…");
                    
                    $lines = [];
                    foreach ($picked as $pitem) {
                        try {
                            $path = $pitem['path'] ?? null;
                            if ($path) {
                                $url = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(60));
                                $name = $pitem['name'] ?? basename($path);
                                $lines[] = "📄 {$name}\n{$url}";
                            }
                        } catch (\Exception $e) {
                            Log::warning('Chat Simulator: Erro ao gerar URL do arquivo', ['error' => $e->getMessage()]);
                        }
                    }
                    
                    if (!empty($lines)) {
                        $sendTextMethod->invoke($wppController, $phone, implode("\n\n", $lines) . "\n\n" . $footer);
                    }
                    return null;
                }
                
                // C) Índices sem lista em cache
                if ($isMultiIndexListMethod->invoke($wppController, $text) && !Cache::has($filesKey)) {
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $footerMethod = $reflection->getMethod('footerControls');
                    $footerMethod->setAccessible(true);
                    $footer = $footerMethod->invoke($wppController);
                    $sendTextMethod->invoke($wppController, $phone, "Para solicitar arquivos, primeiro diga: *ver arquivos*.\nDepois responda com os números (ex.: 1,2,5)." . $footer);
                    return null;
                }
                
                // D) Qualquer outra frase com "arquiv" → instrução
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $footerMethod = $reflection->getMethod('footerControls');
                $footerMethod->setAccessible(true);
                $footer = $footerMethod->invoke($wppController);
                $sendTextMethod->invoke($wppController, $phone, "Entendi que você quer um arquivo.\nAntes, diga: *ver arquivos*.\nVou listar e você escolhe (ex.: 1,2,5)." . $footer);
                return null;
                
            } catch (\Exception $e) {
                Log::error('Chat Simulator: Erro ao processar arquivos', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
        // ================== FIM SUPER HARD-GATE ARQUIVOS ==================
        
        // Remove flag temporária de ignorar comandos especiais antes de processar pela IA
        // (se foi definida ao limpar a flag do menu)
        // Garante que $ctx está definido
        if (!isset($ctx)) {
            $ctx = $thread->context ?? [];
        }
        if (!empty(data_get($ctx, 'ignore_special_commands'))) {
            $ctx = $thread->context ?? [];
            unset($ctx['ignore_special_commands']);
            $thread->context = $ctx;
            $thread->save();
            Log::info('Chat Simulator: Removeu flag ignore_special_commands antes de processar pela IA', ['phone' => $phone]);
            $ctx = $thread->context ?? [];
        }

        // --------------------------------------------------------------------
        // 🔹 SUPER-GATE: Assistente do Corretor (CRM) - Chat Simulator
        // Detecta comandos para entrar no modo CRM
        // --------------------------------------------------------------------
        $isCrmCommandMethod = $reflection->getMethod('isCrmCommand');
        $isCrmCommandMethod->setAccessible(true);
        $isCrmCommand = $isCrmCommandMethod->invoke($wppController, $norm);
        // IMPORTANTE: isCrmMode só é true se JÁ estiver no modo CRM (não apenas se detectou comando)
        $isCrmMode = data_get($ctx, 'crm_mode', false);
        
        // Verifica se está aguardando confirmação para entrar no assistente
        $aguardandoConfirmacaoAssistente = data_get($ctx, 'aguardando_confirmacao_assistente', false);
        
        // Se está aguardando confirmação, verifica se a resposta é positiva
        if ($aguardandoConfirmacaoAssistente) {
            $isConfirmacaoPositivaMethod = $reflection->getMethod('isConfirmacaoPositiva');
            $isConfirmacaoPositivaMethod->setAccessible(true);
            $confirmacao = $isConfirmacaoPositivaMethod->invoke($wppController, $norm);
            
            if ($confirmacao) {
                // Remove flag de confirmação e ativa modo CRM
                unset($ctx['aguardando_confirmacao_assistente']);
                $ctx['crm_mode'] = true;
                $thread->context = $ctx;
                $thread->save();
                
                // Reprocessa o comando original que estava salvo
                $comandoOriginal = data_get($ctx, 'comando_assistente_original', $text);
                
                // Vincula corretor
                $attachCorretorMethod = $reflection->getMethod('attachCorretorToThread');
                $attachCorretorMethod->setAccessible(true);
                $attachCorretorMethod->invoke($wppController, $thread, $phone);
                
                if (empty($thread->corretor_id)) {
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, 
                        "⚠️ Não consegui identificar seu usuário corretor.\n" .
                        "Verifique se seu número está cadastrado corretamente na plataforma.",
                        null,
                        $thread
                    );
                    return null;
                }

                $corretor = User::find($thread->corretor_id);
                if (!$corretor) {
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, "❌ Erro ao identificar corretor. Tente novamente.", null, $thread);
                    return null;
                }

                // Processa com o AssistenteService usando o comando original
                try {
                    $parser = new \App\Services\Crm\SimpleNlpParser();
                    $router = new \App\Services\Crm\CommandRouter();
                    $assistente = new \App\Services\Crm\AssistenteService($parser, $router);
                    
                    $resposta = $assistente->processar($thread, $corretor, $comandoOriginal);
                    
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, $resposta, null, $thread);
                    
                    // Registra mensagem
                    $storeMessageMethod = $reflection->getMethod('storeMessage');
                    $storeMessageMethod->setAccessible(true);
                    $storeMessageMethod->invoke($wppController, $thread, [
                        'sender' => 'ia',
                        'type' => 'text',
                        'body' => $resposta,
                        'meta' => ['source' => 'crm_assistente'],
                    ]);

                    return null;
                } catch (\Throwable $e) {
                    Log::error('Chat Simulator: Erro no Assistente CRM', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $sendTextMethod = $reflection->getMethod('sendText');
                    $sendTextMethod->setAccessible(true);
                    $sendTextMethod->invoke($wppController, $phone, "❌ Erro ao processar. Tente novamente ou digite *sair* para voltar.", null, $thread);
                    return null;
                }
            } else {
                // Resposta negativa ou não reconhecida - cancela e volta ao normal
                unset($ctx['aguardando_confirmacao_assistente']);
                unset($ctx['comando_assistente_original']);
                $thread->context = $ctx;
                $thread->save();
                
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone, 
                    "Ok, continuando com as perguntas sobre o empreendimento. Como posso ajudar?",
                    null,
                    $thread
                );
                return null;
            }
        }

        // Se detectou comando do assistente mas NÃO está no modo assistente E tem empreendimento selecionado
        // Pergunta se quer usar o assistente
        if ($isCrmCommand && !$isCrmMode) {
            $empId = $thread->selected_empreendimento_id ?? $thread->empreendimento_id;
            if ($empId) {
                // Salva o comando original e pergunta se quer usar o assistente
                $ctx['aguardando_confirmacao_assistente'] = true;
                $ctx['comando_assistente_original'] = $text;
                $thread->context = $ctx;
                $thread->save();
                
                $emp = \App\Models\Empreendimento::find($empId);
                $oneLineMethod = $reflection->getMethod('oneLine');
                $oneLineMethod->setAccessible(true);
                $empNome = $emp ? $oneLineMethod->invoke($wppController, $emp->nome ?? "Empreendimento #{$empId}", 40) : "o empreendimento";
                
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone,
                    "🤖 Parece que você quer usar o *Assistente do Corretor* para registrar isso.\n\n" .
                    "Você está atualmente em *{$empNome}* fazendo perguntas.\n\n" .
                    "Deseja entrar no *Assistente* para registrar essa ação?\n\n" .
                    "Responda *sim* ou *ok* para entrar no assistente, ou *não* para continuar aqui.",
                    null,
                    $thread
                );
                return null;
            }
        }

        // Só processa direto se JÁ estiver no modo CRM (não apenas se detectou comando)
        if ($isCrmMode) {
            Log::info('Chat Simulator: Processando comando CRM (já está no modo)', [
                'isCrmCommand' => $isCrmCommand,
                'isCrmMode' => $isCrmMode,
                'norm' => $norm,
            ]);

            // Vincula corretor
            $attachCorretorMethod = $reflection->getMethod('attachCorretorToThread');
            $attachCorretorMethod->setAccessible(true);
            $attachCorretorMethod->invoke($wppController, $thread, $phone);
            
            if (empty($thread->corretor_id)) {
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone, 
                    "⚠️ Não consegui identificar seu usuário corretor.\n" .
                    "Verifique se seu número está cadastrado corretamente na plataforma."
                );
                return null;
            }

            $corretor = User::find($thread->corretor_id);
            if (!$corretor) {
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone, "❌ Erro ao identificar corretor. Tente novamente.");
                return null;
            }

            // Ativa modo CRM no contexto
            $ctx = $thread->context ?? [];
            $ctx['crm_mode'] = true;
            $thread->context = $ctx;
            $thread->save();

            // Processa com o AssistenteService
            try {
                $parser = new \App\Services\Crm\SimpleNlpParser();
                $router = new \App\Services\Crm\CommandRouter();
                $assistente = new \App\Services\Crm\AssistenteService($parser, $router);
                
                $resposta = $assistente->processar($thread, $corretor, $text);
                
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone, $resposta, null, $thread);
                
                // Registra mensagem
                $storeMessageMethod = $reflection->getMethod('storeMessage');
                $storeMessageMethod->setAccessible(true);
                $storeMessageMethod->invoke($wppController, $thread, [
                    'sender' => 'ia',
                    'type' => 'text',
                    'body' => $resposta,
                    'meta' => ['source' => 'crm_assistente'],
                ]);

                return null;
            } catch (\Throwable $e) {
                Log::error('Chat Simulator: Erro no Assistente CRM', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone, "❌ Erro ao processar. Tente novamente ou digite *sair* para voltar.");
                return null;
            }
        }
        
        // Processa perguntas normais usando handleNormalAIFlow (só se não for arquivo)
        try {
            Log::info('Chat Simulator: Chamando handleNormalAIFlow', [
                'phone' => $phone,
                'text_preview' => substr($text, 0, 50),
                'selected_emp' => $thread->selected_empreendimento_id,
            ]);
            
            $handleAIMethod = $reflection->getMethod('handleNormalAIFlow');
            $handleAIMethod->setAccessible(true);
            $result = $handleAIMethod->invoke($wppController, $thread, $text);
            
            Log::info('Chat Simulator: handleNormalAIFlow concluído', [
                'result' => $result !== null ? 'retornou valor' : 'null',
            ]);
        } catch (\Throwable $e) {
            Log::error('Chat Simulator: Erro ao processar pergunta IA', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Em caso de erro, envia mensagem genérica
            try {
                $sendTextMethod = $reflection->getMethod('sendText');
                $sendTextMethod->setAccessible(true);
                $sendTextMethod->invoke($wppController, $phone, "Desculpe, ocorreu um erro ao processar sua pergunta. Por favor, tente novamente.");
            } catch (\Exception $e2) {
                Log::error('Chat Simulator: Erro ao enviar mensagem de erro', [
                    'error' => $e2->getMessage(),
                    'trace' => $e2->getTraceAsString(),
                ]);
            }
        }
        
        return null;
    }
    
    
    /**
     * Atualiza thread com dados do usuário
     */
    protected function updateThreadWithUser(WhatsappThread $thread)
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }
        
        $updated = false;
        
        if (Schema::hasColumn('whatsapp_threads', 'corretor_id') && empty($thread->corretor_id)) {
            $thread->corretor_id = $user->id;
            $updated = true;
        }
        
        if (Schema::hasColumn('whatsapp_threads', 'company_id') && empty($thread->company_id) && $user->company_id) {
            $thread->company_id = $user->company_id;
            $updated = true;
        }
        
        if (Schema::hasColumn('whatsapp_threads', 'tenant_id') && empty($thread->tenant_id) && $user->company_id) {
            $thread->tenant_id = $user->company_id;
            $updated = true;
        }
        
        if ($updated) {
            $thread->save();
        }
    }

    /**
     * Busca ou cria thread para o usuário
     */
    protected function getOrCreateThread(string $phone, User $user)
    {
        $thread = WhatsappThread::where('phone', $phone)->first();

        if (!$thread) {
            $data = [
                'phone' => $phone,
                'thread_id' => 'simulator_' . $phone . '_' . time(),
                'state' => 'idle',
                'context' => [],
            ];
            
            // Adiciona campos se existirem na tabela
            if (Schema::hasColumn('whatsapp_threads', 'tenant_id')) {
                $data['tenant_id'] = $user->company_id ?? 1;
            }
            if (Schema::hasColumn('whatsapp_threads', 'user_id')) {
                $data['user_id'] = $user->id;
            }
            if (Schema::hasColumn('whatsapp_threads', 'corretor_id')) {
                $data['corretor_id'] = $user->id;
            }
            if (Schema::hasColumn('whatsapp_threads', 'company_id')) {
                $data['company_id'] = $user->company_id ?? 1;
            }
            
            $thread = WhatsappThread::create($data);
        } else {
            // Atualiza dados do usuário se necessário
            $updated = false;
            
            if (Schema::hasColumn('whatsapp_threads', 'user_id') && empty($thread->user_id)) {
                $thread->user_id = $user->id;
                $updated = true;
            }
            if (Schema::hasColumn('whatsapp_threads', 'corretor_id') && empty($thread->corretor_id)) {
                $thread->corretor_id = $user->id;
                $updated = true;
            }
            if (Schema::hasColumn('whatsapp_threads', 'company_id') && empty($thread->company_id) && $user->company_id) {
                $thread->company_id = $user->company_id;
                $updated = true;
            }
            if (Schema::hasColumn('whatsapp_threads', 'tenant_id') && empty($thread->tenant_id) && $user->company_id) {
                $thread->tenant_id = $user->company_id;
                $updated = true;
            }
            
            if ($updated) {
                $thread->save();
            }
        }

        return $thread;
    }

    /**
     * Normaliza texto (copiado do WppController)
     */
    protected function normalizeText(string $text): string
    {
        $t = \Illuminate\Support\Str::of($text)->lower()->squish()->toString();
        if (class_exists('\Normalizer')) {
            $t = \Normalizer::normalize($t, \Normalizer::FORM_D);
            $t = preg_replace('/\p{Mn}+/u', '', $t);
        }
        return $t;
    }
}
