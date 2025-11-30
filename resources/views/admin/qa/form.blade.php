<style>
    .max-w-3xl.mx-auto.bg-white.p-6.rounded.shadow {
    margin-top: 100px;
}
</style>

<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl">Perguntar sobre: {{ $e->nome }}</h2>
      <a href="{{ route('admin.assets.index', $e) }}" class="text-sm text-indigo-600 hover:underline">Arquivos</a>
    </div>
  </x-slot>

  <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <form method="POST" action="{{ route('admin.qa.ask', $e) }}" class="space-y-3">
      @csrf
      <label class="block text-sm font-medium">Sua pergunta</label>
      <input type="text" name="q" value="{{ old('q') }}"
             class="w-full border rounded p-2"
             placeholder="Ex: Qual a metragem das unidades e o preço base?" required>

      <button class="px-4 py-2 bg-indigo-600 text-white rounded">Perguntar</button>
    </form>

    @if(session('answer'))
      <div class="mt-6 p-4 bg-gray-50 rounded border">
        <div class="text-sm text-gray-500 mb-1">Resposta da IA</div>
        <div class="whitespace-pre-wrap">{{ session('answer') }}</div>
      </div>
    @endif
  </div>
</x-app-layout>
