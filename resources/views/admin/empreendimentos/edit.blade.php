<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                Editar empreendimento: {{ $e->nome }}
            </h2>
          
        </div>
    </x-slot>

    <div 
        x-data="{ showSuccess: {{ session('ok') ? 'true' : 'false' }} }"
        class="max-w-5xl mx-auto sm:px-6 lg:px-8 py-6"
    >
        {{-- Modal de sucesso --}}
        <div 
            x-show="showSuccess"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        >
            <div 
                @click.away="showSuccess = false"
                class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6"
            >
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-1">
                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-100">
                            <svg class="h-5 w-5 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-gray-900">
                            Alterações salvas
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ session('ok') ?? 'Empreendimento atualizado com sucesso!' }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="showSuccess = false"
                        class="px-4 py-2 text-sm rounded border border-gray-300 bg-white hover:bg-gray-50"
                    >
                        Fechar
                    </button>
                    
                </div>
            </div>
        </div>

        {{-- ALERTAS (erros) --}}
        @if ($errors->any())
            <div class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold mb-2">Ops, verifique os campos abaixo:</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-6">
            <form action="{{ route('admin.empreendimentos.update', $e->id) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- ATIVO --}}
                <div class="mb-4 flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="ativo"
                        name="ativo"
                        value="1"
                        class="rounded border-gray-300"
                        {{ old('ativo', $e->ativo) ? 'checked' : '' }}
                    >
                    <label for="ativo" class="text-sm font-medium text-gray-700">Empreendimento ativo</label>
                </div>

                {{-- CAMPOS PRINCIPAIS --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

                    <div>
                        <label class="block text-sm font-medium">Nome *</label>
                        <input type="text" name="nome"
                            value="{{ old('nome', $e->nome) }}"
                            required
                            class="mt-1 w-full rounded border-gray-300">
                    </div>

                  <div>
    <label class="block text-sm font-medium">Incorporadora</label>
<select name="incorporadora_id"
        class="mt-1 w-full rounded border-gray-300 text-sm">
    <option value="">Selecione...</option>
    @foreach($incorporadoras as $inc)
        <option value="{{ $inc->id }}"
            @selected((string) old('incorporadora_id', $e->incorporadora_id ?? '') === (string) $inc->id)>
            {{ $inc->nome }}@if($inc->cidade || $inc->uf) — {{ $inc->cidade }}/{{ $inc->uf }} @endif
        </option>
    @endforeach
</select>

    </select>
</div>

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

                    <div>
                        <label class="block text-sm font-medium">Preço base</label>
                        <input type="number" step="0.01" name="preco_base"
                            value="{{ old('preco_base', $e->preco_base) }}"
                            class="mt-1 w-full rounded border-gray-300">
                    </div>
                </div>

                {{-- LOCALIZAÇÃO --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="md:col-span-2">
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

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                   <div x-data="localidadeIBGE('{{ old('uf', $e->uf ?? '') }}', '{{ old('cidade', $e->cidade ?? '') }}')" 
     class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">

    {{-- ESTADO --}}
    <div>
        <label class="block text-sm font-medium">Estado</label>
        <select name="uf"
                x-model="uf"
                @focus="loadEstados()"
                @change="loadCidades()"
                class="mt-1 w-full rounded border-gray-300 text-sm">
            <option value="">Selecione...</option>
            <template x-for="estado in estados" :key="estado.id">
                <option :value="estado.sigla" x-text="estado.sigla + ' - ' + estado.nome"></option>
            </template>
        </select>
    </div>

    {{-- CIDADE --}}
    <div>
        <label class="block text-sm font-medium">Cidade</label>
        <select name="cidade"
                x-model="cidade"
                :disabled="!uf"
                class="mt-1 w-full rounded border-gray-300 text-sm">
            <option value="">Selecione...</option>
            <template x-for="c in cidades" :key="c.id">
                <option :value="c.nome" x-text="c.nome"></option>
            </template>
        </select>
    </div>

</div>

                    <div>
                        <label class="block text-sm font-medium">PDF (URL)</label>
                        <input type="text" name="pdf_url"
                            value="{{ old('pdf_url', $e->pdf_url) }}"
                            class="mt-1 w-full rounded border-gray-300">
                    </div>
                </div>

                {{-- DESCRIÇÃO --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Descrição</label>
                    <textarea name="descricao" rows="3"
                        class="w-full rounded border-gray-300">{{ old('descricao', $e->descricao) }}</textarea>
                </div>


                <!----
                <div class="mb-4">
                    <label class="block text-sm font-medium">Texto de disponibilidade</label>
                    <textarea name="disponibilidade_texto" rows="2"
                        class="w-full rounded border-gray-300">{{ old('disponibilidade_texto', $e->disponibilidade_texto) }}</textarea>
                </div> --->

                {{-- CAMPOS JSON E IA --}}
              <!----  <details class="mb-6">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-700">
                        Opções avançadas (IA / JSON)
                    </summary>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                        <div>
                            <label class="text-xs font-medium">Tabela de descontos (JSON)</label>
                            <textarea name="tabela_descontos" rows="4"
                                class="w-full rounded border-gray-300">{{ old('tabela_descontos', json_encode($e->tabela_descontos)) }}</textarea>
                        </div>

                        <div>
                            <label class="text-xs font-medium">Amenidades (JSON)</label>
                            <textarea name="amenidades" rows="4"
                                class="w-full rounded border-gray-300">{{ old('amenidades', json_encode($e->amenidades)) }}</textarea>
                        </div>

                        <div>
                            <label class="text-xs font-medium">Imagens (JSON)</label>
                            <textarea name="imagens" rows="4"
                                class="w-full rounded border-gray-300">{{ old('imagens', json_encode($e->imagens)) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="text-sm font-medium">Contexto IA</label>
                        <textarea name="contexto_ia" rows="4"
                            class="w-full rounded border-gray-300">{{ old('contexto_ia', $e->contexto_ia) }}</textarea>
                    </div>

                    <div class="mt-4">
                        <label class="text-sm font-medium">Texto IA</label>
                        <textarea name="texto_ia" rows="3"
                            class="w-full rounded border-gray-300">{{ old('texto_ia', $e->texto_ia) }}</textarea>
                    </div>
                </details>

                ----->

                {{-- BOTÕES --}}
                <div class="flex justify-end gap-3 mt-6">
                    <a href="{{ route('admin.empreendimentos.show', $e->id) }}"
                       class="px-4 py-2 border border-gray-300 rounded text-sm bg-white hover:bg-gray-50">
                        Cancelar
                    </a>

                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                        Salvar alterações
                    </button>
                </div>

            </form>
        </div>
    </div>


<!--- lista uf / cidade ----->
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('localidadeIBGE', (ufInicial = '', cidadeInicial = '') => ({
            estados: [],
            cidades: [],
            uf: ufInicial,
            cidade: cidadeInicial,

            async loadEstados() {
                if (this.estados.length) return; // evita recarregar
                try {
                    const res = await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome');
                    this.estados = await res.json();
                } catch (e) {
                    console.error('Erro ao carregar estados IBGE', e);
                }
            },

            async loadCidades() {
                this.cidades = [];
                this.cidade = '';

                if (!this.uf) return;

                try {
                    const res = await fetch(
                        `https://servicodados.ibge.gov.br/api/v1/localidades/estados/${this.uf}/municipios?orderBy=nome`
                    );
                    this.cidades = await res.json();

                    // Se já houver cidade no banco ou no old(), seleciona
                    if (cidadeInicial) {
                        this.cidade = cidadeInicial;
                    }
                } catch (e) {
                    console.error('Erro ao carregar cidades IBGE', e);
                }
            },

            // Carrega tudo automaticamente se vier valores do banco (edit)
            init() {
                if (ufInicial) {
                    this.loadEstados().then(() => {
                        this.loadCidades();
                    });
                }
            }
        }));
    });
</script>

</x-app-layout>
