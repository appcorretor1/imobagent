<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">
                Perguntar sobre: {{ $empreendimento->nome }}
            </h2>
            <a href="{{ route('admin.empreendimentos.index') }}" class="text-sm text-indigo-600 hover:underline">
                ← Voltar
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded p-6 space-y-6">

            @if (session('ok'))
                <div class="p-3 mb-2 bg-green-50 text-green-800 rounded">
                    {{ session('ok') }}
                </div>
            @endif

            @if ($question)
                <div class="p-4 bg-gray-50 rounded border border-gray-200">
                    <div class="text-sm text-gray-600 mb-1">Sua pergunta</div>
                    <div class="font-medium text-gray-900">{{ $question }}</div>
                </div>
            @endif

            @if ($answer)
                <div class="p-4 bg-emerald-50 rounded border border-emerald-200">
                    <div class="text-sm text-emerald-700 mb-1">Resposta da IA</div>
                    <div class="prose prose-sm max-w-none">{!! nl2br(e($answer)) !!}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.empreendimentos.ask.submit', $empreendimento) }}" class="space-y-4">
                @csrf
                <label class="block">
                    <span class="text-sm text-gray-700">Faça uma pergunta</span>
                    <textarea
                        name="question"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Ex.: Onde fica localizado? Quais os diferenciais? Como é a área de lazer?"
                        required>{{ old('question') }}</textarea>
                </label>

                @error('question')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Perguntar
                    </button>
                    <span class="text-xs text-gray-500">
                        A IA consulta os PDFs vinculados a este empreendimento.
                    </span>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
