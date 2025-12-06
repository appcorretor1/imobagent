<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">
                Galeria do empreendimento #{{ $empreendimentoId }}
            </h2>

            <a href="{{ route('admin.meus-empreendimentos') }}"
               class="text-sm text-indigo-600 hover:underline">
                ← Voltar
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-4">

        <div class="bg-white rounded-xl shadow p-4 text-sm text-gray-600">
            <div class="font-semibold mb-1">
                Link da sua galeria (para enviar aos clientes):
            </div>
            <div class="text-indigo-600 break-all text-xs md:text-sm">
                {{ $linkGaleria }}
            </div>
        </div>

        @if($arquivos->isEmpty())
            <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">
                Ainda não há mídias salvas para este empreendimento.
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($arquivos as $item)
                    <div class="bg-white rounded-xl shadow overflow-hidden">
                        @if($item['tipo'] === 'foto')
                            <a href="{{ $item['url'] }}" target="_blank">
                                <img src="{{ $item['url'] }}"
                                     alt="Foto"
                                     class="w-full h-40 object-cover">
                            </a>
                        @elseif($item['tipo'] === 'video')
                            <video class="w-full h-40 object-cover" controls>
                                <source src="{{ $item['url'] }}">
                                Seu navegador não suporta vídeo.
                            </video>
                        @else
                            <div class="p-4 text-xs break-all">
                                <a href="{{ $item['url'] }}" target="_blank" class="text-indigo-600 underline">
                                    Arquivo
                                </a>
                            </div>
                        @endif

                        <div class="p-2 text-[11px] text-gray-500 text-right">
                            {{ optional($item['data'])->format('d/m/Y H:i') }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</x-app-layout>
