<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Editar texto do empreendimento: {{ $e->nome }}</h2>
  </x-slot>

  <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6">
    <form method="POST" action="{{ route('admin.empreendimentos.texto.update', $e) }}">
      @csrf

      <textarea name="texto_ia"
                class="w-full h-96 border rounded p-3 focus:ring focus:ring-indigo-200"
                placeholder="Digite aqui o texto descritivo que será consumido pela IA...">{{ old('texto_ia', $e->texto_ia) }}</textarea>

      <div class="mt-4">
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
          Salvar texto
        </button>
        <a href="{{ route('admin.empreendimentos.index') }}" class="ml-2 text-sm text-gray-600 hover:underline">
          ← Voltar
        </a>
      </div>
    </form>
  </div>
</x-app-layout>
