<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Editar usuário: {{ $user->name }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto mt-6">

        <div class="bg-white p-6 rounded shadow">

            <form method="POST" action="{{ route('admin.users.update', $user->id) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block font-medium mb-1">Nome</label>
                    <input type="text" name="name" required
                        class="w-full border rounded p-2"
                        value="{{ old('name', $user->name) }}">
                </div>


                <div class="mb-4">
                    <label class="block font-medium mb-1">E-mail</label>
                    <input type="text" name="email" required
                        class="w-full border rounded p-2"
                        value="{{ old('email', $user->email) }}">
                </div>


                <div class="mb-4">
                    <label class="block font-medium mb-1">Telefone (WhatsApp)</label>
                    <input type="text" name="phone" required
                        class="w-full border rounded p-2"
                        value="{{ old('phone', $user->phone) }}">
                </div>

                <div class="mb-4">
                    <label class="block font-medium mb-1">Perfil</label>
                    <select name="role" class="w-full border rounded p-2">
                        <option value="corretor" @selected($user->role === 'corretor')>Corretor</option>
                        <option value="diretor" @selected($user->role === 'diretor')>Diretor</option>
                    </select>
                </div>

                <!-- 🔥 NOVO BLOCO: Status do usuário -->
               <div class="mb-4">
    <label class="block font-medium mb-1">Status</label>

    <select name="is_active" class="w-full border rounded p-2">
        <option value="1" @selected($user->is_active == 1)>Ativo</option>
        <option value="0" @selected($user->is_active == 0)>Inativo</option>
    </select>

    <div class="mt-2 flex items-center gap-2 text-sm">
        @if($user->is_active)
            <div class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded">
                <i data-lucide="user-check" class="w-4 h-4 mr-1"></i>
                Usuário Ativo
            </div>
        @else
            <div class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 rounded">
                <i data-lucide="user-x" class="w-4 h-4 mr-1"></i>
                Usuário Inativo
            </div>
        @endif
    </div>
</div>

                <!-- 🔥 FIM DO BLOCO -->

                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                    Salvar Alterações
                </button>

                <a href="{{ route('admin.users.index') }}"
                   class="ml-4 text-gray-600 hover:underline">
                    Cancelar
                </a>
            </form>

        </div>
    </div>

    {{-- Máscara de telefone --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.querySelector('input[name="phone"]');
            if (!input) return;

            input.addEventListener('input', function (e) {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length > 11) v = v.slice(0, 11);

                if (v.length <= 10) {
                    v = v.replace(/^(\d{2})(\d{0,4})(\d{0,4}).*/, function(_, ddd, p1, p2) {
                        let out = '';
                        if (ddd) out += '(' + ddd + ')';
                        if (p1) out += ' ' + p1;
                        if (p2) out += '-' + p2;
                        return out;
                    });
                } else {
                    v = v.replace(/^(\d{2})(\d{0,5})(\d{0,4}).*/, function(_, ddd, p1, p2) {
                        let out = '';
                        if (ddd) out += '(' + ddd + ')';
                        if (p1) out += ' ' + p1;
                        if (p2) out += '-' + p2;
                        return out;
                    });
                }

                e.target.value = v;
            });
        });
    </script>

</x-app-layout>
