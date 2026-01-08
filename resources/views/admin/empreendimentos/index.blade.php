@php
    use Illuminate\Support\Facades\Storage;
@endphp
@if(session('ok'))
    <script>
        window.dispatchEvent(new CustomEvent('toast', {
            detail: { message: "{{ session('ok') }}" }
        }))
    </script>
@endif

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                Empreendimentos
            </h2>

            <div class="mb-6 flex justify-end gap-2">
                <a href="{{ route('admin.incorporadoras.index') }}"
                   class="px-4 py-2 rounded border border-slate-300 text-slate-600 text-sm hover:bg-slate-100">
                    Incorporadoras
                </a>

                <a href="{{ route('admin.empreendimentos.create') }}"
                   class="px-4 py-2 rounded bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                    + Novo empreendimento
                </a>
            </div>
        </div>
    </x-slot>


    <div x-data="{ showIncorporadoraModal: false }"
         class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6">

        @if (session('ok'))
            <div class="mb-4 rounded bg-green-50 text-green-800 p-3 text-sm">
                {{ session('ok') }}
            </div>
        @endif

        {{-- FILTROS --}}
      <form method="GET"
      class="mb-4 bg-white p-4 rounded shadow flex flex-wrap gap-4 items-end">

    {{-- BUSCA POR NOME --}}
    <div class="flex-1 min-w-[220px]">
        <label class="block text-xs font-medium text-gray-600">Buscar por nome</label>
        <input type="text" name="search" value="{{ request('search') }}"
               class="mt-1 w-full rounded border-gray-300 text-sm"
               placeholder="Ex: Atelier Opus">
    </div>

    {{-- INCORPORADORA --}}
    <div class="w-48 min-w-[180px]">
        <label class="block text-xs font-medium text-gray-600">Incorporadora</label>
        <select name="incorporadora_id"
                class="mt-1 w-full rounded border-gray-300 text-sm">
            <option value="">Todas</option>
            @foreach($incorporadoras as $inc)
                <option value="{{ $inc->id }}"
                    @selected((string)request('incorporadora_id') === (string)$inc->id)>
                    {{ $inc->nome }}@if($inc->cidade || $inc->uf) — {{ $inc->cidade }}/{{ $inc->uf }} @endif
                </option>
            @endforeach
        </select>
    </div>

    {{-- STATUS --}}
    <div class="w-40 min-w-[150px]">
        <label class="block text-xs font-medium text-gray-600">Status</label>
        <select name="status"
                class="mt-1 w-full rounded border-gray-300 text-sm">
            <option value="ativo" {{ request('status', 'ativo') === 'ativo' ? 'selected' : '' }}>
                Ativos
            </option>
            <option value="todos" {{ request('status') === 'todos' ? 'selected' : '' }}>
                Todos
            </option>
        </select>
    </div>

    {{-- BOTÃO FILTRAR --}}
    <div class="flex items-end">
        <button type="submit"
                class="px-4 py-2 rounded bg-gray-800 text-white text-sm hover:bg-gray-900 whitespace-nowrap">
            Filtrar
        </button>
    </div>

</form>


        {{-- CARDS DE EMPREENDIMENTOS --}}
        @if($empreendimentos->isEmpty())
            <div class="bg-white rounded shadow p-6 text-center text-sm text-gray-500">
                Nenhum empreendimento encontrado.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($empreendimentos as $emp)
                    @php
                        $bannerUrl   = $emp->banner_thumb
                            ? Storage::disk('s3')->url($emp->banner_thumb)
                            : null;

                        $logoEmpUrl  = $emp->logo_path
                            ? Storage::disk('s3')->url($emp->logo_path)
                            : null;

                        $logoIncUrl  = $emp->incorporadora?->logo_path
                            ? Storage::disk('s3')->url($emp->incorporadora->logo_path)
                            : null;
                    @endphp
<div class="bg-white rounded-xl border overflow-hidden flex flex-col
     transition-all duration-300
     hover:border-blue-500 hover:shadow-[0_4px_12px_rgba(0,0,0,0.12)] hover:-translate-y-1">

{{-- BANNER / THUMB --}}
                        <div class="relative h-32 bg-slate-100">
                            @if($bannerUrl)
                                <img src="{{ $bannerUrl }}"
                                     alt="Banner {{ $emp->nome }}"
                                     class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-[11px] text-slate-400">
                                    Sem banner cadastrado
                                </div>
                            @endif

                            {{-- LOGOS SOBREPOSTOS --}}
                            <div class="absolute top-2 left-2 flex gap-2">
                                @if($logoEmpUrl)
                                    <div class="h-9 w-9 rounded-lg bg-white/90 border border-slate-200 shadow-sm flex items-center justify-center overflow-hidden">
                                        <img src="{{ $logoEmpUrl }}"
                                             alt="Logo {{ $emp->nome }}"
                                             class="max-h-8 max-w-[32px] object-contain">
                                    </div>
                                @endif

                                @if($logoIncUrl)
                                    <div class="h-9 w-9 rounded-lg bg-white/90 border border-slate-200 shadow-sm flex items-center justify-center overflow-hidden">
                                        <img src="{{ $logoIncUrl }}"
                                             alt="Logo {{ $emp->incorporadora?->nome }}"
                                             class="max-h-8 max-w-[32px] object-contain">
                                    </div>
                                @endif
                            </div>

                            {{-- BADGE ATIVO / INATIVO --}}
                            <div class="absolute bottom-2 right-2">
                                @if($emp->ativo)
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                        Ativo
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                        Inativo
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- CONTEÚDO --}}
                        <div class="p-4 flex-1 flex flex-col justify-between">
                            <div class="space-y-1">
                                <h3 class="text-sm font-semibold text-gray-900 line-clamp-2">
                                    {{ $emp->nome }}
                                </h3>

                                <p class="text-xs text-gray-600">
                                    @if($emp->cidade || $emp->uf)
                                        {{ $emp->cidade }}@if($emp->uf)/{{ $emp->uf }}@endif
                                    @else
                                        <span class="italic text-gray-400">Cidade/UF não informado</span>
                                    @endif
                                </p>

                                <p class="text-xs text-gray-600 flex items-center gap-1">
                                    <span class="font-medium">Incorporadora:</span>
                                    <span class="truncate">
                                        {{ $emp->incorporadora?->nome ?? '—' }}
                                    </span>
                                </p>

                                @if($emp->tipologia)
                                    <p class="text-xs text-gray-600">
                                        <span class="font-medium">Tipologia:</span> {{ $emp->tipologia }}
                                    </p>
                                @endif

                                @if($emp->preco_base)
                                    <p class="text-xs text-gray-600">
                                        <span class="font-medium">Preço base:</span>
                                        R$ {{ number_format($emp->preco_base, 2, ',', '.') }}
                                    </p>
                                @endif
                            </div>

                            {{-- AÇÕES --}}
                            <div class="mt-4 flex flex-wrap gap-2">
                                {{-- TEXTO IA --}}
                                <a href="{{ route('admin.empreendimentos.texto.edit', $emp->id) }}"
                                   class="flex-1 min-w-[120px] inline-flex items-center justify-center px-3 py-1.5 rounded text-xs font-medium border border-indigo-500 text-indigo-600 hover:bg-indigo-50">
                                    Texto IA
                                </a>

                                {{-- ARQUIVOS --}}
                                <a href="{{ route('admin.assets.index', $emp->id) }}"
                                   class="flex-1 min-w-[120px] inline-flex items-center justify-center px-3 py-1.5 rounded text-xs font-medium border border-slate-300 text-slate-700 hover:bg-slate-50">
                                    Arquivos
                                </a>

                                {{-- UNIDADES --}}
                                <a href="{{ route('admin.empreendimentos.unidades.index', $emp->id) }}"
                                   class="flex-1 min-w-[120px] inline-flex items-center justify-center px-3 py-1.5 rounded text-xs font-medium border border-emerald-500 text-emerald-700 hover:bg-emerald-50">
                                    Unidades
                                </a>

                                {{-- PROPOSTAS --}}
                                <a href="{{ route('admin.propostas.index', $emp->id) }}"
                                   class="flex-1 min-w-[120px] inline-flex items-center justify-center px-3 py-1.5 rounded text-xs font-medium border border-purple-500 text-purple-700 hover:bg-purple-50">
                                    Propostas
                                </a>

                                {{-- EDITAR --}}
                                <a href="{{ route('admin.empreendimentos.edit', $emp->id) }}"
                                   class="flex-1 min-w-[120px] inline-flex items-center justify-center px-3 py-1.5 rounded text-xs font-medium border border-amber-400 text-amber-700 hover:bg-amber-50">
                                    Editar
                                </a>

                               

                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $empreendimentos->links() }}
            </div>
        @endif

        {{-- MODAL: NOVA INCORPORADORA (mantido igual) --}}
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
                        <input type="file" name="logo"
                               class="mt-1 w-full text-sm">
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

    <!--- lista UF/cidade ----->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('incorporadoraLocalidade', () => ({
                estados: [],
                cidades: [],
                uf: '',
                cidade: '',

                async loadEstados() {
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
                    } catch (e) {
                        console.error('Erro ao carregar cidades IBGE', e);
                    }
                }
            }));
        });
    </script>

</x-app-layout>
