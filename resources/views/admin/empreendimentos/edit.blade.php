<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Editar empreendimento</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto p-6">

    @if (session('ok'))
        <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            {{ session('ok') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="max-w-7xl mx-auto p-6">

        <form action="{{ route('admin.empreendimentos.update', $e->id) }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6 bg-white rounded shadow p-6">
            @csrf
            @method('PUT')

            {{-- EMPREENDIMENTO ATIVO --}}
            <div class="flex items-center gap-2">
                <input type="checkbox" name="ativo" value="1"
                       @checked(old('ativo', $e->ativo))>
                <label class="text-sm font-medium">Empreendimento ativo</label>
            </div>

            {{-- NOME / INCORPORADORA --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>
                    <label class="block text-sm font-medium">Nome *</label>
                    <input type="text" name="nome" required
                           value="{{ old('nome', $e->nome) }}"
                           class="mt-1 w-full rounded border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium">Incorporadora</label>
                    <select name="incorporadora_id"
                            class="mt-1 w-full rounded border-gray-300">
                        <option value="">Selecione...</option>
                        @foreach($incorporadoras as $inc)
                            <option value="{{ $inc->id }}"
                                @selected($inc->id == old('incorporadora_id', $e->incorporadora_id))>
                                {{ $inc->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- TIPOLOGIA / METRAGEM --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>
                    <label class="block text-sm font-medium">Tipologia</label>
                    <input type="text" name="tipologia"
                           value="{{ old('tipologia', $e->tipologia) }}"
                           class="mt-1 w-full rounded border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium">Metragem</label>
                    <input type="text" name="metragem"
                           value="{{ old('metragem', $e->metragem) }}"
                           class="mt-1 w-full rounded border-gray-300">
                </div>

            </div>

            {{-- PREÇO --}}
            <div>
                <label class="block text-sm font-medium">Preço base</label>
                <input type="text" name="preco_base"
                       value="{{ old('preco_base', $e->preco_base) }}"
                       class="mt-1 w-full rounded border-gray-300">
            </div>

            {{-- ENDEREÇO + CEP --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>
                    <label class="block text-sm font-medium">Endereço</label>
                    <input type="text" name="endereco"
                           value="{{ old('endereco', $e->endereco) }}"
                           class="mt-1 w-full rounded border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium">CEP</label>
                    <input type="text" name="cep"
                           value="{{ old('cep', $e->cep) }}"
                           class="mt-1 w-full rounded border-gray-300">
                </div>

            </div>

            {{-- ESTADO / CIDADE / PDF --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <div>
                    <label class="block text-sm font-medium">Estado</label>
                    <input type="text" name="uf"
                           value="{{ old('uf', $e->uf) }}"
                           class="mt-1 w-full rounded border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium">Cidade</label>
                    <input type="text" name="cidade"
                           value="{{ old('cidade', $e->cidade) }}"
                           class="mt-1 w-full rounded border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium">PDF (URL)</label>
                    <input type="text" name="pdf_url"
                           value="{{ old('pdf_url', $e->pdf_url) }}"
                           class="mt-1 w-full rounded border-gray-300">
                </div>

            </div>

           

            {{-- DESCRIÇÃO --}}
            <div class="mt-4">
                <label class="block text-sm font-medium mb-1">Descrição</label>
                <textarea name="descricao" rows="4"
                          class="w-full rounded border-gray-300">{{ old('descricao', $e->descricao) }}</textarea>
            </div>

            {{-- LOGO + BANNER --}}
            <div class="pt-6 border-t border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- LOGO --}}
                <div>
                    <label class="block text-sm font-medium">Logotipo</label>

                    @if($e->logo_path)
                        <div class="my-2">
                            <img src="{{ Storage::disk('s3')->url($e->logo_path) }}"
                                 class="h-16 rounded border shadow bg-white object-contain">
                        </div>
                    @endif

                    <input type="file" name="logo_path"
                           class="mt-1 block w-full text-sm text-gray-700
                                  file:mr-4 file:py-2 file:px-3 file:rounded-md
                                  file:border-0 file:bg-indigo-50 file:text-indigo-700
                                  hover:file:bg-indigo-100">

                    <p class="mt-1 text-xs text-gray-500">PNG ou JPG até 2MB.</p>
                </div>

                {{-- BANNER THUMB --}}
                <div>
                    <label class="block text-sm font-medium">Banner (thumb da galeria)</label>

                    @if($e->banner_thumb)
                        <div class="my-2">
                            <img src="{{ Storage::disk('s3')->url($e->banner_thumb) }}"
                                 class="h-24 w-full rounded border shadow object-cover bg-gray-100">
                        </div>
                    @endif

                    <input type="file" name="banner_thumb"
                           class="mt-1 block w-full text-sm text-gray-700
                                  file:mr-4 file:py-2 file:px-3 file:rounded-md
                                  file:border-0 file:bg-indigo-50 file:text-indigo-700
                                  hover:file:bg-indigo-100">

                    <p class="mt-1 text-xs text-gray-500">
                        Imagem horizontal JPG/PNG. Usada no topo da galeria pública.
                    </p>
                </div>

            </div>

            <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.empreendimentos.index') }}"
                   class="px-4 py-2 rounded border bg-white text-gray-600 hover:bg-gray-50">
                    Cancelar
                </a>

                <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                    Salvar alterações
                </button>
            </div>

        </form>

       

      

    </div>
</x-app-layout>
