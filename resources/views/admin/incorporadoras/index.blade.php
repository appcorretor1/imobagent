<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Incorporadoras</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto p-6">

        <div class="mb-6 flex justify-end">
       @include('admin.incorporadoras._create-modal')
        </div>

        <div class="bg-white shadow rounded">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Logo</th>
                        <th class="px-4 py-2 text-left">Nome</th>
                        <th class="px-4 py-2 text-left">Cidade</th>
                        <th class="px-4 py-2 text-left">UF</th>
                        <th class="px-4 py-2 text-right">Ações</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    @foreach($incorporadoras as $inc)
                        <tr>
                            <td class="px-4 py-2">
                                @if($inc->logo_path)
                                    <img src="{{ Storage::disk('s3')->url($inc->logo_path) }}"
                                        class="h-10 rounded">
                                @else
                                    <span class="text-gray-400 text-sm">sem logo</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $inc->nome }}</td>
                            <td class="px-4 py-2">{{ $inc->cidade }}</td>
                            <td class="px-4 py-2">{{ $inc->uf }}</td>

                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.incorporadoras.edit', $inc->id) }}"
                                   class="text-blue-600 hover:text-blue-800">
                                   Editar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>

    </div>
</x-app-layout>
