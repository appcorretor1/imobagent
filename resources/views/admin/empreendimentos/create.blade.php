<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                Adicionar empreendimento
            </h2>
            <a href="{{ route('admin.empreendimentos.index') }}" class="text-sm text-indigo-600 hover:underline">
                ← Voltar
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 py-6">
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
            <form action="{{ route('admin.empreendimentos.store') }}" method="POST">
                @csrf

                {{-- Ativo --}}
                <div class="mb-4 flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="ativo"
                        name="ativo"
                        value="1"
                        class="rounded border-gray-300"
                        {{ old('ativo', 1) ? 'checked' : '' }}
                    >
                    <label for="ativo" class="text-sm font-medium text-gray-700">
                        Empreendimento ativo
                    </label>
                </div>

                {{-- Dados principais --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="nome" class="block text-sm font-medium text-gray-700">Nome *</label>
                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            value="{{ old('nome') }}"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>

                    {{-- INCORPORADORA --}}
                    <div>
                        <label class="block text-sm font-medium">Incorporadora</label>
                        <select name="incorporadora_id"
                                class="mt-1 w-full rounded border-gray-300 text-sm">
                            <option value="">Selecione...</option>
                            @foreach($incorporadoras as $inc)
                                <option value="{{ $inc->id }}"
                                    @selected(old('incorporadora_id') == $inc->id)>
                                    {{ $inc->nome }}@if($inc->cidade || $inc->uf) — {{ $inc->cidade }}/{{ $inc->uf }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tipologia" class="block text-sm font-medium text-gray-700">Tipologia</label>
                        <input
                            type="text"
                            id="tipologia"
                            name="tipologia"
                            value="{{ old('tipologia') }}"
                            placeholder="Ex: 2 e 3 dormitórios"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="metragem" class="block text-sm font-medium text-gray-700">Metragem</label>
                        <input
                            type="text"
                            id="metragem"
                            name="metragem"
                            value="{{ old('metragem') }}"
                            placeholder="Ex: 68 a 95 m²"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="preco_base" class="block text-sm font-medium text-gray-700">Preço base</label>
                        <input
                            type="number"
                            step="0.01"
                            id="preco_base"
                            name="preco_base"
                            value="{{ old('preco_base') }}"
                            placeholder="Ex: 650000.00"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>
                </div>

                {{-- Localização --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label for="endereco" class="block text-sm font-medium text-gray-700">Endereço</label>
                        <input
                            type="text"
                            id="endereco"
                            name="endereco"
                            value="{{ old('endereco') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="cep" class="block text-sm font-medium text-gray-700">CEP</label>
                        <input
                            type="text"
                            id="cep"
                            name="cep"
                            value="{{ old('cep') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    {{-- UF / CIDADE via IBGE (sem $e no create) --}}
                    <div x-data="localidadeIBGE('{{ old('uf') }}', '{{ old('cidade') }}')" class="grid grid-cols-1 sm:grid-cols-2 gap-3">

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
                        <label for="pdf_url" class="block text-sm font-medium text-gray-700">PDF (URL)</label>
                        <input
                            type="text"
                            id="pdf_url"
                            name="pdf_url"
                            value="{{ old('pdf_url') }}"
                            placeholder="Ex: caminho no S3 ou URL pública"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>
                </div>

                {{-- Descrição --}}
                <div class="mb-4">
                    <label for="descricao" class="block text-sm font-medium text-gray-700">Descrição</label>
                    <textarea
                        id="descricao"
                        name="descricao"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >{{ old('descricao') }}</textarea>
                </div>

                {{-- Unidades do empreendimento (importação via planilha) --}}
<div class="mt-6 rounded border border-dashed border-slate-300 bg-slate-50 p-4">
    <h3 class="font-semibold mb-2 text-sm">Unidades do empreendimento</h3>
    <p class="text-xs text-slate-600 mb-2">
        As unidades serão cadastradas em uma tela específica, a partir de uma planilha Excel.
        Primeiro salve o empreendimento. Depois, na listagem, clique no botão 
        <span class="font-semibold">“Unidades”</span> para importar a planilha.
    </p>

    <div class="text-xs text-slate-600">
        <p class="font-semibold mb-1">Modelo ideal de planilha (.xlsx):</p>
        <div class="inline-block rounded bg-white border border-slate-200 px-3 py-2">
            <pre class="text-[11px] leading-4">
grupo_unidade   unidade     status
Torre 1         101         livre
Torre 1         102         livre
Torre 2         101         reservado
Quadra A        Casa 08     livre
Alameda Azul    Casa 01     fechado
—               201         livre
            </pre>
        </div>
        <p class="mt-2 text-[11px] text-slate-500">
            • <code>grupo_unidade</code> pode ser Torre, Quadra, Alameda, Bloco, etc.  
            • Se não existir agrupamento, deixe como <code>—</code> ou em branco.  
            • <code>status</code> deve ser algo como: <strong>livre</strong>, <strong>reservado</strong>, <strong>fechado</strong>, etc.
        </p>
    </div>
</div>


                {{-- Campos IA / JSON (avançado) --}}
                <details class="mb-6 mt-6">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-700 mb-2">
                        Opções avançadas (IA / JSON)
                    </summary>

                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="tabela_descontos" class="block text-xs font-medium text-gray-700">
                                Tabela de descontos (JSON)
                            </label>
                            <textarea
                                id="tabela_descontos"
                                name="tabela_descontos"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                placeholder='Ex: [{"tipo":"vista","desconto":5}]'
                            >{{ old('tabela_descontos') }}</textarea>
                        </div>

                        <div>
                            <label for="amenidades" class="block text-xs font-medium text-gray-700">
                                Amenidades (JSON)
                            </label>
                            <textarea
                                id="amenidades"
                                name="amenidades"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                placeholder='Ex: ["Piscina","Academia","Portaria 24h"]'
                            >{{ old('amenidades') }}</textarea>
                        </div>

                        <div>
                            <label for="imagens" class="block text-xs font-medium text-gray-700">
                                Imagens (JSON)
                            </label>
                            <textarea
                                id="imagens"
                                name="imagens"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                placeholder='Ex: [{"url":"...","tipo":"fachada"}]'
                            >{{ old('imagens') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="contexto_ia" class="block text-sm font-medium text-gray-700">
                            Contexto para IA
                        </label>
                        <textarea
                            id="contexto_ia"
                            name="contexto_ia"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Informações que a IA deve saber sobre o empreendimento..."
                        >{{ old('contexto_ia') }}</textarea>
                    </div>

                    <div class="mt-4">
                        <label for="texto_ia" class="block text-sm font-medium text-gray-700">
                            Texto IA (informações internas / experimentos)
                        </label>
                        <textarea
                            id="texto_ia"
                            name="texto_ia"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >{{ old('texto_ia') }}</textarea>
                    </div>
                </details>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.empreendimentos.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        Salvar empreendimento
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

                    // Se já houver cidade no old(), seleciona
                    if (cidadeInicial) {
                        this.cidade = cidadeInicial;
                    }
                } catch (e) {
                    console.error('Erro ao carregar cidades IBGE', e);
                }
            },

            // Carrega tudo automaticamente se vier valores do formulário (old)
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
