<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Criar novo usuário</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto mt-6">
        <div class="bg-white p-6 rounded shadow">

            @if ($errors->any())
                <div class="mb-4 text-sm text-red-600">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="mb-4">
                    <label class="block font-medium mb-1">Nome</label>
                    <input type="text" name="name" required
                        class="w-full border rounded p-2"
                        value="{{ old('name') }}">
                </div>

                <div class="mb-4">
                    <label class="block font-medium mb-1">E-mail</label>
                    <input type="email" name="email" required
                        class="w-full border rounded p-2"
                        value="{{ old('email') }}">
                </div>

                <div class="mb-4">
                    <label class="block font-medium mb-1">Telefone (WhatsApp)</label>
                    <input type="text" name="phone" required
                        placeholder="(62) 99999-9999"
                        class="w-full border rounded p-2"
                        value="{{ old('phone') }}">
                </div>

                <div class="mb-4">
                    <label class="block font-medium mb-1">Perfil</label>
                    <select name="role" required class="w-full border rounded p-2">
                        <option value="corretor" {{ old('role') === 'corretor' ? 'selected' : '' }}>Corretor</option>
                        <option value="diretor" {{ old('role') === 'diretor' ? 'selected' : '' }}>Diretor</option>
                    </select>
                </div>

                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                    Criar Usuário
                </button>

                <a href="{{ route('admin.users.index') }}"
                   class="ml-4 text-gray-600 hover:underline">
                    Cancelar
                </a>
            </form>

        </div>
    </div>

    {{-- Máscara simples de telefone --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.querySelector('input[name="phone"]');
            if (!input) return;

            input.addEventListener('input', function (e) {
                let v = e.target.value.replace(/\D/g, '');

                if (v.length > 11) v = v.slice(0, 11);

                if (v.length <= 10) {
                    // (62) 1234-5678
                    v = v.replace(/^(\d{2})(\d{0,4})(\d{0,4}).*/, function(_, ddd, p1, p2) {
                        let out = '';
                        if (ddd) out += '(' + ddd + ')';
                        if (p1) out += ' ' + p1;
                        if (p2) out += '-' + p2;
                        return out;
                    });
                } else {
                    // (62) 91234-5678
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
