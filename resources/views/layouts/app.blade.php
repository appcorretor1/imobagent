<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
<script src="https://unpkg.com/lucide@latest"></script>
<script>lucide.createIcons();</script>
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            main {
    padding: 100px 0;
}

div#chatBox {
    height: auto;
}
</style>


    </head>
    <body class="font-sans antialiased">
{{-- 🎉 Toast global --}}
<div
    x-data="{ show: false, message: '' }"
    x-show="show"
    x-transition.opacity.duration.400ms
    x-cloak
    class="fixed top-4 right-4 z-50"
    @toast.window="
        message = $event.detail.message;
        show = true;
        setTimeout(() => show = false, 3000);
    "
>
    <div class="bg-green-600 text-white px-4 py-2 rounded shadow-lg flex items-center gap-2">
        <!-- Ícone -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
             stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5 13l4 4L19 7"/>
        </svg>

        <span x-text="message"></span>
    </div>
</div>


        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>


<script src="https://unpkg.com/alpinejs" defer></script>

        <script src="https://unpkg.com/lucide@latest"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.lucide) {
            lucide.createIcons();
        }
    });
</script>

    </body>
</html>
