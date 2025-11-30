<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                Unidades do empreendimento: {{ $empreendimento->nome }}
            </h2>
            <a href="{{ route('admin.empreendimentos.index') }}"
               class="text-sm text-indigo-600 hover:underline">
                ← Voltar
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 py-6">
        
        {{-- Sucesso --}}
        @if (session('ok'))
            <div class="mb-4 rounded bg-green-50 text-green-800 p-3 text-sm">
                {{ session('ok') }}
            </div>
        @endif

        {{-- Erros --}}
        @if ($errors->any())
            <div class="mb-4 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold mb-2">Ops, verifique os campos abaixo:</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif


        {{-- IMPORTAR PLANILHA --}}
        <div class="mb-6 bg-white rounded shadow p-4">

            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="font-semibold text-sm mb-1">Importar unidades via planilha</h3>
                    <p class="text-xs text-slate-500">
                        Utilize um arquivo CSV/XLSX com as colunas:
                        <code>grupo_unidade</code>, <code>unidade</code>, <code>status</code>.
                    </p>
                </div>

                {{-- 🔹 BOTÃO PARA BAIXAR O MODELO --}}
                <a href="{{ route('admin.empreendimentos.unidades.template', $empreendimento) }}"
                   class="px-3 py-1.5 text-xs border border-slate-300 rounded text-slate-700 hover:bg-slate-50">
                    Baixar modelo (.csv)
                </a>
            </div>

            <p class="text-xs text-slate-500 mb-3">
                Exemplo:<br>
                Torre 1 — 101 — livre<br>
                Torre 1 — 102 — reservado<br>
                Quadra A — Casa 08 — livre
            </p>

            <form method="POST"
                  action="{{ route('admin.empreendimentos.unidades.import', $empreendimento) }}"
                  enctype="multipart/form-data"
                  class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                @csrf

                <input type="file"
                       name="arquivo"
                       accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                       class="text-sm">

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-slate-800 text-white text-xs font-medium rounded hover:bg-slate-900">
                    Importar planilha
                </button>
            </form>

        </div>



        {{-- LISTA + EDIÇÃO DE STATUS --}}
        <form method="POST"
              action="{{ route('admin.empreendimentos.unidades.bulk-status', $empreendimento) }}">
            @csrf
            @method('PUT')

            <div class="bg-white rounded shadow overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100 text-slate-700">
                        <tr>
                            <th class="px-3 py-2 text-left w-1/3">Unidade</th>
                            <th class="px-3 py-2 text-left w-1/3">Grupo / Bloco</th>
                            <th class="px-3 py-2 text-left w-1/3">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($unidades as $u)
                            <tr class="border-t border-slate-100">

                                {{-- UNIDADE --}}
                                <td class="px-3 py-2">
                                    {{ $u->unidade }}
                                </td>

                                {{-- GRUPO --}}
                                <td class="px-3 py-2">
                                    {{ $u->grupo_unidade ?: '—' }}
                                </td>

                                {{-- STATUS --}}
                                <td class="px-3 py-2">
                                    <select name="unidades[{{ $u->id }}]"
                                            class="border rounded px-2 py-1 text-sm">
                                        @foreach ($statusOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($u->status === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-4 text-center text-slate-400">
                                    Nenhuma unidade cadastrada ainda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- BOTÃO SALVAR --}}
            @if ($unidades->isNotEmpty())
                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                        Salvar alterações de status
                    </button>
                </div>
            @endif

        </form>

    </div>
</x-app-layout>
