<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Editar incorporadora</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto p-6">

        <form action="{{ route('admin.incorporadoras.update', $inc->id) }}"
              method="POST"
              enctype="multipart/form-data"
              class="bg-white rounded-lg shadow p-6 space-y-6">
            @csrf
            {{-- se futuramente mudar a rota para PUT/PATCH, basta adicionar @method('PUT') aqui --}}

            {{-- CABEÇALHO DO FORM --}}
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">
                        Dados da incorporadora
                    </h3>
                    <p class="text-sm text-gray-500">
                        Atualize nome, endereço e logotipo exibidos nos empreendimentos.
                    </p>
                </div>

                @if($inc->logo_path)
                    <div class="shrink-0 flex flex-col items-center gap-2">
                        <span class="text-xs text-gray-400">Logo atual</span>
                        <img src="{{ Storage::disk('s3')->url($inc->logo_path) }}"
                             class="h-12 w-auto rounded border bg-white object-contain px-2 py-1">
                    </div>
                @endif
            </div>

            {{-- NOME / RESPONSÁVEL --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Nome *
                    </label>
                    <input type="text"
                           name="nome"
                           value="{{ old('nome', $inc->nome) }}"
                           required
                           class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Responsável
                    </label>
                    <input type="text"
                           name="responsavel"
                           value="{{ old('responsavel', $inc->responsavel) }}"
                           class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            {{-- ENDEREÇO / CIDADE / UF --}}
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Endereço
                    </label>
                    <input type="text"
                           name="endereco"
                           value="{{ old('endereco', $inc->endereco) }}"
                           class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Cidade
                        </label>
                        <input type="text"
                               name="cidade"
                               value="{{ old('cidade', $inc->cidade) }}"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            UF
                        </label>
                        <input type="text"
                               name="uf"
                               maxlength="2"
                               value="{{ old('uf', $inc->uf) }}"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm uppercase tracking-wide text-center focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            {{-- LOGO --}}
            <div class="pt-4 border-t border-gray-200">
                <label class="block text-sm font-medium text-gray-700">
                    Logotipo
                </label>

                <div class="mt-2 flex flex-col gap-2">
                    <input type="file"
                           name="logo_path"
                           accept="image/*"
                           class="block w-full text-sm text-gray-700
                                  file:mr-4 file:py-2 file:px-3
                                  file:rounded-md file:border-0
                                  file:bg-indigo-50 file:text-indigo-700
                                  hover:file:bg-indigo-100">

                    <p class="text-xs text-gray-500">
                        PNG ou JPG até 2MB. Esse logo aparece junto aos empreendimentos vinculados.
                    </p>
                </div>
            </div>

            {{-- AÇÕES --}}
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.incorporadoras.index') }}"
                   class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                    Salvar alterações
                </button>
            </div>
        </form>

    </div>
</x-app-layout>
