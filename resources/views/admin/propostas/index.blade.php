<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                Propostas - {{ $empreendimento->nome }}
            </h2>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.empreendimentos.index') }}"
                   class="text-sm px-3 py-1 bg-gray-100 rounded hover:bg-gray-200">
                    ← Voltar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6">
        @if (session('ok'))
            <div class="mb-4 rounded bg-green-50 text-green-800 p-3 text-sm">
                {{ session('ok') }}
            </div>
        @endif

        @if (empty($propostas))
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-500 text-center">
                    Nenhuma proposta encontrada para este empreendimento.
                </p>
            </div>
        @else
            <div class="bg-white rounded shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Unidade
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Torre/Bloco
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Data de Geração
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Arquivo
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($propostas as $proposta)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        Unidade {{ $proposta['unidade'] }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ $proposta['torre'] }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ $proposta['data'] }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-500 truncate max-w-xs" title="{{ $proposta['nome'] }}">
                                        {{ $proposta['nome'] }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('admin.propostas.visualizar', ['empId' => $empreendimento->id, 'fileName' => $proposta['nome']]) }}"
                                       target="_blank"
                                       class="text-indigo-600 hover:text-indigo-900 mr-4">
                                        Visualizar
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
