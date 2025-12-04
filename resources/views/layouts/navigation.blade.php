@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;
    use App\Helpers\ColorHelper;

    $role = auth()->user()->role ?? null;
    $company = auth()->user()->company ?? null;

    // Logo da empresa
    if ($company && $company->logo_path) {
        $logoUrl = Str::startsWith($company->logo_path, ['http://', 'https://'])
            ? $company->logo_path
            : Storage::disk('s3')->url($company->logo_path);
    } else {
        $logoUrl = asset('images/logo-default.png');
    }

    // Cor whitelabel da navbar
    $navColor = $company->primary_color ?? '#ffffff';

    // Determina se a navbar é escura ou clara
    $isDark = ColorHelper::isDark($navColor);

    // Cores globais de texto
    $navText        = $isDark ? 'text-white'       : 'text-gray-800';
    $navTextSecondary = $isDark ? 'text-gray-200' : 'text-gray-600';
    $navHover       = $isDark ? 'hover:text-gray-200' : 'hover:text-gray-900';
@endphp

<nav x-data="{ open: false }"
     class="border-b border-gray-200"
     style="background-color: {{ $navColor }}">

    <!-- Primary Navigation -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            <!-- Left Section -->
            <div class="flex items-center">

                <!-- Logo -->
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 shrink-0">
                    <img src="{{ $logoUrl }}" alt="Logo" class="h-8 w-auto object-contain">
                </a>

                <!-- Desktop Menu -->
                <div class="hidden sm:flex space-x-8 sm:ms-10">

                    <x-nav-link 
                        :href="route('admin.dashboard')"
                        :active="request()->routeIs('admin.dashboard')"
                        class="{{ $navText }} {{ $navHover }} font-medium">
                        Dashboard
                    </x-nav-link>

                    @if(in_array($role, ['super_admin','diretor','gerente']))
                        <x-nav-link 
                            :href="route('admin.empreendimentos.index')"
                            :active="request()->routeIs('admin.empreendimentos.*')"
                            class="{{ $navText }} {{ $navHover }} font-medium">
                            Empreendimentos
                        </x-nav-link>
                    @endif

                </div>
            </div>

            <!-- Right Section (Dropdown) -->
            <div class="hidden sm:flex sm:items-center sm:ml-6">

                <x-dropdown align="right" width="48">

                    <!-- Trigger -->
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 {{ $navText }} {{ $navHover }} font-medium focus:outline-none">

                            <span>{{ Auth::user()->name }}</span>

                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full 
                                {{ $isDark ? 'bg-white text-gray-800' : 'bg-gray-800 text-white' }}">
                                {{ Str::substr(Auth::user()->name, 0, 2) }}
                            </span>

                        </button>
                    </x-slot>

                    <!-- Dropdown Menu -->
                    <x-slot name="content">

                        <div class="bg-white p-2 rounded-md shadow-md">

                            @if(auth()->user()->role === 'diretor')
                                <x-dropdown-link :href="route('admin.users.index')" class="text-gray-700">
                                    Gestão de usuários
                                </x-dropdown-link>

                                <x-dropdown-link :href="route('admin.company.edit')" class="text-gray-700">
                                    Dados da empresa
                                </x-dropdown-link>
                            @endif

                            <x-dropdown-link :href="route('profile.edit')" class="text-gray-700">
                                Perfil
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link 
                                    href="{{ route('logout') }}"
                                    onclick="event.preventDefault(); this.closest('form').submit();"
                                    class="text-gray-700">
                                    Sair
                                </x-dropdown-link>
                            </form>

                        </div>

                    </x-slot>

                </x-dropdown>

            </div>

            <!-- Mobile Hamburger Button -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="p-2 rounded-md {{ $navText }} {{ $navHover }} focus:outline-none transition">
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

    <!-- Mobile Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden {{ $navText }}">

        <div class="pt-2 pb-3 space-y-1">

            <x-responsive-nav-link :href="route('admin.dashboard')" 
                                   :active="request()->routeIs('admin.dashboard')"
                                   class="{{ $navText }}">
                Dashboard
            </x-responsive-nav-link>

            @if(in_array($role, ['super_admin','diretor','gerente']))
                <x-responsive-nav-link 
                    :href="route('admin.empreendimentos.index')" 
                    :active="request()->routeIs('admin.empreendimentos.*')"
                    class="{{ $navText }}">
                    Empreendimentos
                </x-responsive-nav-link>
            @endif

        </div>

        <!-- User Info Mobile -->
        <div class="pt-4 pb-1 border-t border-gray-300">
            <div class="px-4">
                <div class="font-medium text-base {{ $navText }}">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm {{ $navTextSecondary }}">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">

                <x-responsive-nav-link :href="route('profile.edit')" class="{{ $navText }}">
                    Perfil
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link 
                        href="{{ route('logout') }}"
                        onclick="event.preventDefault(); this.closest('form').submit();"
                        class="{{ $navText }}">
                        Sair
                    </x-responsive-nav-link>
                </form>

            </div>
        </div>

    </div>

</nav>
