<div x-data="{ showIncorporadoraModal: false }" class="inline-block">

    {{-- BOTÃO ABRIR MODAL --}}
    <button type="button"
        @click="showIncorporadoraModal = true"
        class="px-4 py-2 rounded border border-indigo-500 text-indigo-600 text-sm hover:bg-indigo-50">
        + Nova incorporadora
    </button>

    {{-- MODAL: NOVA INCORPORADORA --}}
    <div x-show="showIncorporadoraModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">

        <div @click.away="showIncorporadoraModal = false"
             class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6">

            <h3 class="text-lg font-semibold mb-4">Nova incorporadora</h3>

            <form action="{{ route('admin.incorporadoras.store') }}"
                  method="POST"
                  enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label class="block text-sm font-medium">Nome *</label>
                    <input type="text" name="nome" required
                           class="mt-1 w-full rounded border-gray-300 text-sm">
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium">Endereço</label>
                    <input type="text" name="endereco"
                           class="mt-1 w-full rounded border-gray-300 text-sm">
                </div>

                {{-- UF + CIDADE (IBGE) --}}
                <div x-data="incorporadoraLocalidade()" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">

                    <div>
                        <label class="block text-sm font-medium">Estado</label>

                        <select name="uf"
                                x-model="uf"
                                @focus="estados.length === 0 && loadEstados()"
                                @change="loadCidades()"
                                class="mt-1 w-full rounded border-gray-300 text-sm">

                            <option value="">Selecione...</option>

                            <template x-for="estado in estados" :key="estado.id">
                                <option :value="estado.sigla" x-text="estado.sigla + ' - ' + estado.nome"></option>
                            </template>

                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Cidade</label>

                        <select name="cidade"
                                x-model="cidade"
                                :disabled="!uf || !cidades.length"
                                class="mt-1 w-full rounded border-gray-300 text-sm">
                            <option value="">Selecione...</option>

                            <template x-for="cid in cidades" :key="cid.id">
                                <option :value="cid.nome" x-text="cid.nome"></option>
                            </template>
                        </select>
                    </div>

                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium">Responsável</label>
                    <input type="text" name="responsavel"
                           class="mt-1 w-full rounded border-gray-300 text-sm">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium">Logotipo</label>
                    <input type="file" name="logo" class="mt-1 w-full text-sm">
                    <p class="text-xs text-gray-500 mt-1">
                        PNG ou JPG até 2MB.
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button"
                            @click="showIncorporadoraModal = false"
                            class="px-4 py-2 text-sm rounded border border-gray-300 bg-white hover:bg-gray-50">
                        Cancelar
                    </button>

                    <button type="submit"
                            class="px-4 py-2 text-sm rounded bg-indigo-600 text-white hover:bg-indigo-700">
                        Salvar
                    </button>
                </div>
            </form>

        </div>

    </div>
</div>


{{-- Lista UF/Cidade (IBGE) --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('incorporadoraLocalidade', () => ({
        estados: [],
        cidades: [],
        uf: '',
        cidade: '',

        async loadEstados() {
            try {
                const res = await fetch(
                    'https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome'
                );
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
            } catch (e) {
                console.error('Erro ao carregar cidades IBGE', e);
            }
        }
    }));
});
</script>
