<style>
.max-w-4xl.mx-auto { width: 1280px; }

/* Efeito dos três pontinhos da IA */
.typing-dots .dot {
  display: inline-block;
  font-size: 22px;
  line-height: 1;
  opacity: 0.2;
  animation: pulse 1.2s infinite;
}
.typing-dots .dot2 { animation-delay: .2s; }
.typing-dots .dot3 { animation-delay: .4s; }

@keyframes pulse {
  0%, 80%, 100% { opacity: 0.2; transform: translateY(0); }
  40% { opacity: 1; transform: translateY(-2px); }
}
</style>

<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl">Chat sobre: {{ $e->nome }}</h2>

      <div class="flex items-center gap-3">
        <a href="{{ route('admin.assets.index', $e) }}" class="text-sm text-indigo-600 hover:underline">Arquivos</a>
        <form method="POST" action="{{ route('admin.qa.reset', $e) }}">
          @csrf
          <button class="text-sm text-gray-500 hover:text-gray-700" title="Limpar histórico">Resetar chat</button>
        </form>
      </div>
    </div>
  </x-slot>

  <div class="max-w-4xl mx-auto">
    <div class="bg-white rounded shadow p-4 h-[70vh] overflow-y-auto" id="chatBox">

      @forelse ($messages as $m)
        @php
          // metadata pode vir como objeto; converte p/ array
          $meta = (array) ($m->metadata ?? []);

          // hidden vem como string ('1','true','yes'...) — normaliza
          $hiddenRaw = (string) ($meta['hidden'] ?? '');
          $hidden    = in_array(strtolower($hiddenRaw), ['1','true','yes'], true);

          $isAssistant      = ($m->role ?? '') === 'assistant';
          $isContextMessage = (($meta['type'] ?? null) === 'contexto_bd'); // trata como esquerda (mas está hidden)

          // extrai primeiro bloco de texto
          $text = '';
          foreach ($m->content as $c) {
              if (($c->type ?? '') === 'text' && !empty($c->text->value)) {
                  $text = $c->text->value;
                  break;
              }
          }

          // limpa citações automáticas de arquivos
          $text = preg_replace('/\[[^\]]+\.pdf\]/i', '', $text);
        @endphp

        @continue($hidden)

        <div class="w-full flex mb-3 {{ ($isAssistant || $isContextMessage) ? 'justify-start' : 'justify-end' }}">
          <div class="max-w-[80%] px-3 py-2 rounded-lg text-sm leading-relaxed
                      {{ ($isAssistant || $isContextMessage) ? 'bg-gray-100 text-gray-800' : 'bg-indigo-600 text-white' }}">
            {!! nl2br(e($text ?: ($isAssistant ? '...' : ''))) !!}
          </div>
        </div>
      @empty
        <div class="text-center text-gray-500 text-sm">Sem mensagens ainda. Envie sua primeira pergunta abaixo 👇</div>
      @endforelse

      {{-- Bolha de carregamento (aparece enquanto IA pensa) --}}
      <div id="thinkingBubble" class="hidden w-full flex mb-3 justify-start">
        <div class="max-w-[80%] px-3 py-2 rounded-lg text-sm leading-relaxed bg-gray-100 text-gray-800">
          <div class="flex items-center gap-2">
            <span>IA está escrevendo</span>
            <span class="typing-dots">
              <span class="dot dot1">•</span>
              <span class="dot dot2">•</span>
              <span class="dot dot3">•</span>
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Campo de envio --}}
    <form id="qaForm" method="POST" action="{{ route('admin.qa.ask', $e) }}" class="mt-4 flex gap-2">
      @csrf
      <input
        type="text"
        name="q"
        id="qaInput"
        class="flex-1 border rounded px-3 py-2"
        placeholder="Digite sua pergunta (ex: Qual a localização? Quem é a incorporadora?)"
        required
        autofocus
      />
      <button id="qaSendBtn" type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded flex items-center gap-2">
        <span class="btn-label">Enviar</span>
        <svg class="hidden h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
      </button>
    </form>
  </div>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const box = document.getElementById('chatBox');
    const form = document.getElementById('qaForm');
    const input = document.getElementById('qaInput');
    const btn = document.getElementById('qaSendBtn');
    const spinner = btn?.querySelector('svg');
    const label = btn?.querySelector('.btn-label');
    const thinking = document.getElementById('thinkingBubble');

    // Sempre rolar pro final ao carregar a página
    if (box) box.scrollTop = box.scrollHeight;

    // Enviar com Enter
    input?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        form?.requestSubmit();
      }
    });

    form?.addEventListener('submit', () => {
      // desabilita botão e mostra spinner
      btn?.setAttribute('disabled', 'disabled');
      btn?.classList.add('opacity-70', 'cursor-not-allowed');
      spinner?.classList.remove('hidden');
      label.textContent = 'Carregando…';

      // mostra a bolha "IA está pensando"
      thinking?.classList.remove('hidden');
      if (box) box.scrollTop = box.scrollHeight;
    });
  });
  </script>
</x-app-layout>
