@props(['active' => false, 'color' => 'light'])

@php
    $base = $color === 'light'
        ? 'text-white hover:text-gray-200'
        : 'text-gray-700 hover:text-gray-900';

    $classes = $active
        ? $base . ' font-semibold border-b-2 border-current'
        : $base . ' border-b-2 border-transparent';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
