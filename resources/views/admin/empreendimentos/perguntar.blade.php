<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                Perguntar sobre: {{ $emp->nome }}
                @if($emp->cidade || $emp->uf)
                    <span class="text-gray-500 text-sm"> — {{ $emp->cidade }}@if($emp->uf)/{{ $emp->uf }}@endif</span>
                @endif
            </h2>

            <a href="{{ route('admin.empreendimentos.show', $emp->id) }}"
               class="text-sm text-indigo-600 hover:underline">← Voltar</a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white p-6 rounded shadow space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Sua pergunta</label>
                <textarea id="question" class="w-full border rounded p-3" rows="4"
                          placeholder="Ex.: Qual é o endereço? Como está o cronograma?"></textarea>
            </div>

            <div class="flex items-center gap-3">
                <button id="askBtn" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                    Perguntar
                </button>
                <span id="status" class="text-sm text-gray-500"></span>
            </div>

            <div id="answerBox" class="hidden border rounded p-4 bg-gray-50">
                <div class="text-sm text-gray-500 mb-1">Resposta da IA:</div>
                <div id="answerText" class="whitespace-pre-wrap"></div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('askBtn').addEventListener('click', async () => {
        const question = (document.getElementById('question').value || '').trim();
        const statusEl = document.getElementById('status');
        const answerBox = document.getElementById('answerBox');
        const answerText = document.getElementById('answerText');

        if (!question) {
            statusEl.textContent = 'Digite uma pergunta.';
            return;
        }

        statusEl.textContent = 'Consultando...';
        answerBox.classList.add('hidden');
        answerText.textContent = '';

        try {
            const resp = await fetch('{{ route('api.qa.ask') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    empreendimento_id: {{ $emp->id }},
                    question: question
                })
            });

            const data = await resp.json();
            if (data && data.message) {
                answerText.textContent = data.message;
                answerBox.classList.remove('hidden');
                statusEl.textContent = '';
            } else {
                answerText.textContent = 'Não consegui responder agora. Tente novamente.';
                answerBox.classList.remove('hidden');
                statusEl.textContent = '';
            }
        } catch (e) {
            statusEl.textContent = 'Erro ao consultar.';
        }
    });
    </script>
</x-app-layout>
