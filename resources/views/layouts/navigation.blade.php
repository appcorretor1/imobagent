@php
    $role = auth()->user()->role ?? null;
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('admin.dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if(in_array($role, ['super_admin','diretor','gerente']))
                        <x-nav-link :href="route('admin.empreendimentos.index')" :active="request()->routeIs('admin.empreendimentos.*')">
                            {{ __('Empreendimentos') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ml-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none transition"
                        >
                            <div class="mr-2">
                                {{ Auth::user()->name }}
                            </div>
                            <div class="relative">
                                <span
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-gray-200 text-gray-700 text-xs font-semibold"
                                >
                                    {{ \Illuminate\Support\Str::substr(Auth::user()->name, 0, 2) }}
                                </span>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
    @if(auth()->check() && auth()->user()->role === 'diretor')
        <x-dropdown-link :href="route('admin.users.index')">
            Gestão de usuários
        </x-dropdown-link>

        <x-dropdown-link :href="route('admin.company.edit')">
            Dados da empresa
        </x-dropdown-link>
    @endif

    <x-dropdown-link :href="route('profile.edit')">
        {{ __('Profile') }}
    </x-dropdown-link>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-dropdown-link
            href="{{ route('logout') }}"
            onclick="event.preventDefault(); this.closest('form').submit();">
            {{ __('Log Out') }}
        </x-dropdown-link>
    </form>
</x-slot>

                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }"
                              class="inline-flex"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }"
                              class="hidden"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @if(in_array($role, ['super_admin','diretor','gerente']))
                <x-responsive-nav-link :href="route('admin.empreendimentos.index')" :active="request()->routeIs('admin.empreendimentos.*')">
                    {{ __('Empreendimentos') }}
                </x-responsive-nav-link>
            @endif

             @if(in_array($role, ['super_admin','diretor','gerente']))
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    Gestão de usuários
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault();
                                 this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
