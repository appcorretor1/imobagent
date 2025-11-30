<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-semibold">Usuários da Empresa</h2>

            <a href="{{ route('admin.users.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
                + Criar Usuário
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto mt-6">
        @if (session('ok'))
            <div class="p-3 mb-4 bg-green-50 text-green-800 rounded">
                {{ session('ok') }}
            </div>
        @endif

        <div class="bg-white shadow rounded p-6">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b text-gray-600">
                        <th class="py-2">Nome</th>
                        <th class="py-2">E-mail</th>
                        <th class="py-2">Telefone</th>
                        <th class="py-2">Perfil</th>
                        <th class="py-2">Status</th>
                        <th class="py-2">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($users as $u)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3">{{ $u->name }}</td>
                            <td class="py-3">{{ $u->email }}</td>
                            <td class="py-3">{{ $u->phone }}</td>
                            <td class="py-3 capitalize">{{ $u->role }}</td>
                            <td class="py-3">
                                @if($u->is_active)
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                        <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                        Ativo
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700">
                                        <i data-lucide="x-circle" class="w-3 h-3 mr-1"></i>
                                        Inativo
                                    </span>
                                @endif
                            </td>

                            <td class="py-3">
                                <div class="flex items-center gap-2">

                                    {{-- Editar --}}
                                    <a href="{{ route('admin.users.edit', $u->id) }}"
                                       class="inline-flex items-center px-3 py-1.5 bg-indigo-100 text-indigo-700 text-xs rounded-md hover:bg-indigo-200 transition">
                                        <i data-lucide="pencil" class="w-4 h-4 mr-1"></i>
                                        Editar
                                    </a>

                                    {{-- Reenviar acesso --}}
                                    <form action="{{ route('admin.users.resend-access', $u->id) }}"
                                          method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 text-xs rounded-md hover:bg-blue-200 transition"
                                            onclick="return confirm('Gerar nova senha e reenviar acesso para {{ $u->name }}?')">
                                            <i data-lucide="send" class="w-4 h-4 mr-1"></i>
                                            Reenviar
                                        </button>
                                    </form>

                                    {{-- Ativar / Desativar --}}
                                    <form action="{{ route('admin.users.toggle-status', $u->id) }}"
                                          method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center px-3 py-1.5
                                            {{ $u->is_active
                                                ? 'bg-red-100 text-red-700 hover:bg-red-200'
                                                : 'bg-green-100 text-green-700 hover:bg-green-200' }}
                                            text-xs rounded-md transition"
                                            onclick="return confirm('Tem certeza que deseja alterar o status de {{ $u->name }}?')">

                                            @if($u->is_active)
                                                <i data-lucide="user-x" class="w-4 h-4 mr-1"></i>
                                                Desativar
                                            @else
                                                <i data-lucide="user-check" class="w-4 h-4 mr-1"></i>
                                                Ativar
                                            @endif
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </div>
</x-app-layout>
