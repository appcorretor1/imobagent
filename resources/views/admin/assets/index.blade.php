<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl">
        Arquivos do empreendimento: {{ $e->nome }}
      </h2>

      <div class="flex items-center gap-2">
       

        <form method="GET" action="{{ route('admin.assets.index', $e) }}">
          <button class="text-sm px-3 py-1 bg-gray-100 rounded hover:bg-gray-200"
                  title="Atualizar a lista de arquivos">
            Atualizar status
          </button>
        </form>
      </div>
    </div>
  </x-slot>


  <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

    {{-- Aviso didático --}}
    <div class="p-4 bg-blue-50 text-blue-900 rounded border border-blue-100">
      <div class="font-medium">Como funciona</div>
      <ul class="mt-1 text-sm list-disc list-inside">
        <li>Faça o upload de PDFs, DOCX, XLSX, CSV, TXT ou imagens.</li>
        <li>Os arquivos são enviados ao storage e indexados pela IA (Vector Store) deste empreendimento.</li>
        <li>Assim que o status ficar <span class="px-1 rounded bg-green-100 text-green-800">ready</span>, o chat poderá usar o conteúdo.</li>
      </ul>
    </div>

    {{-- Flashes --}}
    @if (session('ok'))
      <div class="p-3 bg-green-50 text-green-800 rounded border border-green-100">
        {{ session('ok') }}
      </div>
    @endif
    @if (session('error'))
      <div class="p-3 bg-red-50 text-red-800 rounded border border-red-100">
        {{ session('error') }}
      </div>
    @endif

    {{-- Erros de validação --}}
    @if ($errors->any())
      <div class="p-3 bg-red-50 text-red-800 rounded border border-red-100">
        <div class="font-semibold mb-1">Erro no upload:</div>
        <ul class="list-disc list-inside text-sm">
          @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- UPLOAD --}}
<div class="bg-white p-6 rounded shadow">
    <h3 class="font-medium text-lg mb-1">Enviar novos arquivos</h3>
    <p class="text-sm text-gray-500 mb-4">
        Tamanho máx. por arquivo: 200MB. Formatos: PDF, DOC/DOCX, XLS/XLSX, CSV, TXT, imagens e vídeos (vídeos não são indexados pela IA).
    </p>

    <form
        id="uploadForm"
        method="POST"
        action="{{ route('admin.assets.store', $e) }}"
        enctype="multipart/form-data"
        class="space-y-4"
    >
        @csrf

        <!-- Barra de Progresso -->
        <div id="progressContainer" class="hidden">
            <div class="w-full bg-gray-200 rounded h-2.5 mb-2 overflow-hidden">
                <div
                    id="progressBar"
                    class="bg-indigo-600 h-2.5 rounded transition-all duration-300 ease-out"
                    style="width: 0%"
                ></div>
            </div>
            <p id="progressText" class="text-xs text-gray-600">0%</p>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Selecionar arquivos</label>
                <input
                    type="file"
                    id="fileInput"
                    name="files[]"
                    multiple
                    class="block w-full border rounded px-3 py-2"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,image/*,video/*,.mp4,.mov,.mkv,.avi,.webm"
                    required
                />
            </div>

            <div class="flex items-end">
               <button
    id="uploadButton"
    type="submit"
    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition disabled:bg-gray-400 disabled:cursor-not-allowed"
>
    <span id="uploadIconWrapper" class="w-4 h-4 mr-2 flex items-center justify-center">
        <i data-lucide="upload-cloud" class="w-4 h-4"></i>
    </span>
    <span id="uploadButtonLabel">Enviar</span>
</button>

            </div>
        </div>
    </form>
</div>
<!-- TOAST -->
<div id="toast"
     class="hidden fixed bottom-6 right-6 px-4 py-3 rounded shadow text-white text-sm z-50">
</div>

<!-- MODAL TREINAMENTO IA -->
<div id="trainingModal"
     class="hidden fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4 p-6 text-center space-y-4">
        <div class="flex justify-center">
            <svg class="h-10 w-10 animate-spin text-indigo-600" viewBox="0 0 24 24">
                <circle
                    class="opacity-25"
                    cx="12" cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                    fill="none"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8"
                ></path>
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-slate-900">
                Treinando a IA com o novo arquivo
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Já estamos processando os PDFs e atualizando o conhecimento deste empreendimento.
                Isso pode levar alguns instantes.
            </p>
        </div>
        <p class="text-xs text-slate-400">
            A página será atualizada automaticamente para mostrar o status.
        </p>
    </div>
</div>



    {{-- LISTA --}}
    <div class="bg-white p-6 rounded shadow">
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-medium text-lg">Arquivos enviados</h3>
        <div class="text-xs text-gray-500">
          Total: {{ $assets->count() }}
        </div>
      </div>

      @if ($assets->isEmpty())
        <div class="text-sm text-gray-500">Nenhum arquivo enviado ainda.</div>
      @else
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-left border-b bg-gray-50">
              <tr>
                <th class="py-2 px-3">Nome</th>
                <th class="py-2 px-3">Tipo</th>
                <th class="py-2 px-3">Tamanho</th>
                <th class="py-2 px-3">Status</th>
                <th class="py-2 px-3">Mensagem</th>
                <th class="py-2 px-3 w-56">Ações</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($assets as $a)
               @php
  $bytes = (int) ($a->size ?? 0);
  if ($bytes >= 1073741824)      { $human = number_format($bytes/1073741824, 2).' GB'; }
  elseif ($bytes >= 1048576)     { $human = number_format($bytes/1048576, 2).' MB'; }
  elseif ($bytes >= 1024)        { $human = number_format($bytes/1024, 2).' KB'; }
  else                           { $human = $bytes.' B'; }

  $badge = match($a->status) {
    'ready'                => 'bg-green-100 text-green-800',
    'processing','pending' => 'bg-yellow-100 text-yellow-800',
    'error','failed'       => 'bg-red-100 text-red-800',
    default                => 'bg-gray-100 text-gray-800',
  };

  $statusLabel = match($a->status) {
    'ready'      => 'Pronto',
    'processing' => 'Processando',
    'pending'    => 'Pendente',
    'error','failed' => 'Falhou',
    default      => ucfirst($a->status ?? '-'),
  };
@endphp


                <tr class="border-b">
                  <td class="py-2 px-3 align-top">
                    <div class="font-medium text-gray-900">
    {{ $a->original_name }}<br>
   
</div>

                  </td>
                  <td class="py-2 px-3 align-top">{{ $a->kind ?? '-' }}</td>
                  <td class="py-2 px-3 align-top">{{ $human }}</td>
                  <td class="py-2 px-3 align-top">
                    <span class="px-2 py-1 rounded text-xs inline-flex items-center gap-1 {{ $badge }}">
                      @if(in_array($a->status, ['processing','pending']))
                        <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3"></path>
                        </svg>
                      @endif
                      {{ $statusLabel }}
                    </span>

                    <!--- @if($a->openai_vector_store_id)
                      <div class="text-[11px] text-gray-400 mt-1">VS: {{ $a->openai_vector_store_id }}</div>
                    @endif --->
                  </td>
                <td class="py-2 px-3 align-top">
  @if($a->error_info)
    <div class="text-red-600">
      {{ \Illuminate\Support\Str::limit($a->error_info, 100) }}
    </div>
  @else
    <span class="text-gray-400">—</span>
  @endif
</td>

<td class="py-2 px-3 align-top">
  <div class="flex items-center gap-2">
    <span>{{ $a->kind ?? '-' }}</span>
    @if(($a->kind ?? null) === 'video')
      <span class="px-1.5 py-0.5 rounded text-[11px] bg-purple-100 text-purple-800"
            title="Vídeos não são indexados pela IA; ficam disponíveis para download/envio.">
        vídeo (não indexado)
      </span>
    @endif
  </div>
</td>


                  <td class="py-2 px-3 align-top">
                    <div class="flex flex-wrap items-center gap-2">
                      @if (in_array($a->status, ['ready','processing','pending']))
                        <a href="{{ route('admin.assets.download', [$e, $a]) }}"
                           class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200"
                           title="Baixar o arquivo">
                          Baixar
                        </a>
                        <a href="{{ route('admin.assets.show', [$e, $a]) }}"
                           class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200"
                           title="Abrir em nova aba">
                          Abrir
                        </a>
                      @endif

                      <form method="POST"
                            action="{{ route('admin.assets.destroy', [$e, $a]) }}"
                            onsubmit="return confirm('Excluir este arquivo? Esta ação não pode ser desfeita.');">
                        @csrf
                        @method('DELETE')
                        <button class="px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200"
                                title="Excluir do storage e do banco">
                          Excluir
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Legenda rápida --}}
        <div class="mt-3 text-xs text-gray-500">
          <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-800">Pendente/Processando</span> A IA ainda está indexando o conteúdo.
          <span class="ml-2 px-2 py-1 rounded bg-green-100 text-green-800">Pronto</span> O chat já pode usar o arquivo.
          <span class="ml-2 px-2 py-1 rounded bg-red-100 text-red-800">Falhou</span> Houve um erro na indexação (ver coluna Mensagem).
        </div>
      @endif
    </div>
  </div>
</x-app-layout>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const button = document.getElementById('uploadButton');
    const buttonLabel = document.getElementById('uploadButtonLabel');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const iconWrapper = document.getElementById('uploadIconWrapper');

    // guarda o ícone original na primeira vez
    if (!iconWrapper.dataset.originalIcon) {
        iconWrapper.dataset.originalIcon = iconWrapper.innerHTML;
    }

    // trava o botão
    button.disabled = true;
    buttonLabel.innerText = "Enviando...";

    // troca ícone por spinner
    iconWrapper.innerHTML = `
        <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24">
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
                fill="none"
            ></circle>
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8"
            ></path>
        </svg>
    `;

    // mostra barra
    progressContainer.classList.remove('hidden');

    const formData = new FormData(form);
    const xhr = new XMLHttpRequest();

    xhr.open("POST", form.action);

    // progresso com animação suave
    xhr.upload.addEventListener("progress", function(e) {
        if (e.lengthComputable) {
            let percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + "%";
            progressText.innerText = percent + "%";
        }
    });

    function resetButtonState() {
        button.disabled = false;
        buttonLabel.innerText = "Enviar";

        // restaura ícone original
        if (iconWrapper.dataset.originalIcon) {
            iconWrapper.innerHTML = iconWrapper.dataset.originalIcon;

            if (window.lucide && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }
        }
    }

    function showTrainingModal() {
        const modal = document.getElementById('trainingModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

   // sucesso
xhr.onload = function () {
    if (xhr.status === 200) {
        showToast("Arquivos enviados com sucesso!", "success");

        // reset
        form.reset();
        progressBar.style.width = "0%";
        progressText.innerText = "0%";

        // mostra modal de treinamento
        showTrainingModal();

        // mantém o modal por 5 segundos antes de recarregar
        setTimeout(() => location.reload(), 8000);

    } else {
        showToast("Erro ao enviar arquivos.", "error");
        resetButtonState();
    }
};


    // erro de rede
    xhr.onerror = function () {
        showToast("Falha de conexão.", "error");
        resetButtonState();
    };

    xhr.send(formData);
});

// FUNÇÃO DE TOAST
function showToast(message, type = "success") {
    const toast = document.getElementById('toast');
    toast.innerText = message;
    toast.classList.remove('hidden');

    toast.classList.remove('bg-red-500', 'bg-green-600');
    toast.classList.add(type === "success" ? 'bg-green-600' : 'bg-red-500');

    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}
</script>
