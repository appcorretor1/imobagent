<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Galeria - {{ $empreendimento->nome ?? 'Residencial Vista Verde' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  @vite(['resources/css/app.css'])

  {{-- Alpine.js (se ainda não estiver global no layout) --}}
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <style>
      [x-cloak] { display: none !important; }
  </style>

  <script>
    function galleryPage(urls) {
      return {
        showModal: false,
        modalImage: '',
        modalIndex: 0,
        images: urls || [],
        open(img, index) {
          this.modalImage = img;
          this.modalIndex = index;
          this.showModal = true;
          document.body.classList.add('overflow-hidden');
        },
        close() {
          this.showModal = false;
          document.body.classList.remove('overflow-hidden');
        },
        next() {
          if (!this.images.length) return;
          this.modalIndex = (this.modalIndex + 1) % this.images.length;
          this.modalImage = this.images[this.modalIndex];
        },
        prev() {
          if (!this.images.length) return;
          this.modalIndex = (this.modalIndex - 1 + this.images.length) % this.images.length;
          this.modalImage = this.images[this.modalIndex];
        },
        copyLink() {
          if (!navigator.clipboard) {
            alert('Seu navegador não suporta cópia automática. Copie o link na barra de endereço.');
            return;
          }
          navigator.clipboard.writeText(window.location.href)
            .then(() => alert('Link copiado para a área de transferência!'))
            .catch(() => alert('Não foi possível copiar o link.'));
        },
        init() {
          document.addEventListener('keydown', (e) => {
            if (!this.showModal) return;
            if (e.key === 'Escape') this.close();
            if (e.key === 'ArrowRight') this.next();
            if (e.key === 'ArrowLeft') this.prev();
          });
        }
      }
    }
  </script>
</head>
<body class="bg-slate-950 text-slate-50">
  <div
    class="min-h-screen"
    x-data="galleryPage(@json($urls))"
    x-init="init()"
  >
    {{-- Header topo --}}
    {{-- Header completo com banner + logos --}}
<header class="relative w-full">

    {{-- Banner dinâmico --}}
    <div class="relative w-full h-52 md:h-64 lg:h-80 overflow-hidden">
        <img 
            src="{{ $empreendimento->banner_thumb 
                    ? Storage::disk('s3')->url($empreendimento->banner_thumb)
                    : 'https://via.placeholder.com/1920x600/0f172a/ffffff?text=Sem+Banner' }}"
            alt="Banner {{ $empreendimento->nome }}"
            class="w-full h-full object-cover"
        >

        <div class="absolute inset-0 bg-gradient-to-b 
                    from-black/60 via-black/40 to-slate-950"></div>

        {{-- Logos sobre o banner --}}
        <div class="absolute top-4 left-4 flex gap-3 items-center">

            {{-- Logo do empreendimento --}}
            @if($empreendimento->logo_path)
                <div class="bg-white/90 backdrop-blur-sm rounded-lg p-3 shadow-lg">
                    <img 
                        src="{{ Storage::disk('s3')->url($empreendimento->logo_path) }}"
                        alt="Logo empreendimento"
                        class="h-10 md:h-12 object-contain"
                    >
                </div>
            @endif

            {{-- Logo da incorporadora --}}
            @if($empreendimento->incorporadora && $empreendimento->incorporadora->logo_path)
                <div class="bg-white/90 backdrop-blur-sm rounded-lg p-3 shadow-lg">
                    <img 
                        src="{{ Storage::disk('s3')->url($empreendimento->incorporadora->logo_path) }}"
                        alt="Logo incorporadora"
                        class="h-10 md:h-12 object-contain"
                    >
                </div>
            @endif

        </div>
    </div>

    {{-- Título / Botões --}}
    <div class="border-b border-slate-800 bg-slate-900/80 backdrop-blur">
        <div class="max-w-6xl mx-auto px-4 py-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            
            <div>
                <h1 class="text-xl md:text-2xl font-semibold">
                    {{ $empreendimento->nome ?? 'Fotos do empreendimento' }}
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Visualize e compartilhe as fotos deste empreendimento.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if(!empty($zipUrl))
                    <a href="{{ $zipUrl }}" target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-3 py-2 text-sm font-medium text-emerald-950 hover:bg-emerald-400 transition">
                        Baixar todas em ZIP
                    </a>
                @endif

                <button type="button"
                        @click="copyLink()"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-slate-700 transition">
                    Copiar link
                </button>
            </div>
        </div>
    </div>

</header>


    {{-- Conteúdo principal --}}
    <main class="max-w-6xl mx-auto px-4 py-8">
      @if(empty($urls))
        <div class="flex flex-col items-center justify-center py-16 text-center">
          <p class="text-slate-300 text-base">
            Nenhuma foto cadastrada ainda para este empreendimento.
          </p>
        </div>
      @else
        <div class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          @foreach($urls as $index => $url)
            <button
              type="button"
              @click="open('{{ $url }}', {{ $index }})"
              class="group relative block bg-slate-900 rounded-xl shadow-md overflow-hidden border border-slate-800 hover:border-emerald-400/70 transition-transform hover:-translate-y-0.5 hover:shadow-lg"
            >
              <img
                src="{{ $url }}"
                alt="Foto do empreendimento"
                loading="lazy"
                class="w-full h-40 md:h-44 lg:h-48 object-cover group-hover:scale-105 transition-transform duration-200"
              >
              <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
              <div class="absolute bottom-2 left-2 px-2 py-1 rounded-full bg-slate-900/80 backdrop-blur text-[11px] font-medium text-slate-100">
                Clique para ampliar
              </div>
            </button>
          @endforeach
        </div>
      @endif
    </main>

    {{-- Modal de zoom --}}
    <div
      x-show="showModal"
      x-cloak
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
      @click.self="close()"
    >
      <div class="relative max-w-5xl w-full mx-4">
        <button
          type="button"
          class="absolute -top-10 right-0 text-slate-200 hover:text-white text-sm"
          @click="close()"
        >
          Fechar ✕
        </button>

        <button
          type="button"
          class="hidden md:flex absolute left-0 top-1/2 -translate-y-1/2 -translate-x-full px-2 py-1 text-slate-300 hover:text-white text-2xl"
          @click.stop="prev()"
        >
          ‹
        </button>

        <button
          type="button"
          class="hidden md:flex absolute right-0 top-1/2 -translate-y-1/2 translate-x-full px-2 py-1 text-slate-300 hover:text-white text-2xl"
          @click.stop="next()"
        >
          ›
        </button>

        <div class="bg-slate-900 rounded-xl overflow-hidden border border-slate-700 shadow-2xl">
          <img
            :src="modalImage"
            alt="Foto ampliada"
            class="w-full max-h-[80vh] object-contain bg-slate-950"
          >
        </div>

        <div class="mt-3 flex items-center justify-between text-xs text-slate-300">
          <span>
            {{ $empreendimento->nome ?? 'Empreendimento' }} —
            <span x-text="(modalIndex + 1) + ' / ' + images.length"></span>
          </span>
          <span class="hidden md:inline">
            Use as setas do teclado para navegar · ESC para fechar
          </span>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
