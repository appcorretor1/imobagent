@props([
    'src',
    'alt' => '',
    'class' => '',
])

<div class="relative overflow-hidden bg-slate-100 {{ $class }}">
    {{-- Placeholder / skeleton com blur --}}
    <div class="absolute inset-0 lazy-placeholder bg-slate-200 animate-pulse"></div>

    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        loading="lazy"
        decoding="async"
        class="lazy-img w-full h-full object-cover blur-lg scale-105 opacity-0 transition-all duration-700 ease-out"
    >
</div>
