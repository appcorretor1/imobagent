<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Meus empreendimentos (galerias)
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">

        @if($galerias->isEmpty())
            <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">
                Você ainda não enviou fotos ou vídeos de nenhum empreendimento pelo WhatsApp.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($galerias as $g)
                    @php
                        $emp = $g->empreendimento ?? null;
                    @endphp

                    <a href="{{ route('admin.meus-empreendimentos.show', $g->empreendimento_id) }}"
                       class="block bg-white rounded-xl shadow p-4 hover:shadow-md transition">
                        <div class="text-sm text-gray-500 mb-1">
                            @if($emp)
                                {{ $emp->nome ?? ('Empreendimento #' . $g->empreendimento_id) }}
                            @else
                                Empreendimento #{{ $g->empreendimento_id }}
                            @endif
                        </div>

                        <div class="text-xs text-gray-400 mt-1">
                            Clique para ver a galeria de fotos e vídeos que você salvou.
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</x-app-layout>
