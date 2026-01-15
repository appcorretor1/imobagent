<x-app-layout>

    {{-- Opcional: header padrão da dashboard --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dados da empresa') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Título customizado --}}
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Dados da empresa
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Configure as informações oficiais da empresa, identidade visual e integrações de WhatsApp.
                    </p>
                </div>

                @if (session('status'))
                    <div class="rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                        {{ session('status') }}
                    </div>
                @endif
            </div>

            {{-- Erros --}}
            @if ($errors->any())
                <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-medium mb-1">Ops, encontramos alguns erros:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Formulário --}}
            <form
                method="POST"
                action="{{ route('admin.company.update') }}"
                enctype="multipart/form-data"
                class="space-y-8"
            >
                @csrf

                {{-- Card: Identidade / Logo --}}
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-base font-semibold text-slate-900">
                        Identidade da empresa
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Nome, logotipo e informações básicas que aparecem para corretores e clientes.
                    </p>

                    <div class="mt-6 grid gap-6 md:grid-cols-3">

                        {{-- Logo --}}
                        <div class="md:col-span-1">
                            <label class="block text-sm font-medium text-slate-700">
                                Logotipo
                            </label>
                            <p class="mt-1 text-xs text-slate-500">
                                PNG ou JPG, até 2MB. Ideal em fundo transparente.
                            </p>

                            <div class="mt-4 flex items-center gap-4">
                               @php
    $logoUrl = null;
    if (!empty($company->logo_path)) {
        $logoUrl = Storage::disk('s3')->url($company->logo_path);
    }
@endphp

@if ($logoUrl)
    <div class="h-16 w-16 rounded-lg border border-slate-200 overflow-hidden bg-slate-50 flex items-center justify-center">
        <img
            src="{{ $logoUrl }}"
            alt="Logo {{ $company->name }}"
            class="h-full w-full object-contain"
        >
    </div>
@else
    <div class="h-16 w-16 rounded-lg border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-xs text-slate-400">
        Sem logo
    </div>
@endif


                                <div>
                                    <input
                                        type="file"
                                        name="logo"
                                        accept="image/*"
                                        class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800"
                                    >
                                </div>
                            </div>
                        </div>

                        {{-- Nome --}}
                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-medium text-slate-700">
                                Nome da empresa
                            </label>
                            <input
                                type="text"
                                name="name"
                                id="name"
                                value="{{ old('name', $company->name) }}"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                                required
                            >
                            <p class="mt-1 text-xs text-slate-500">
                                Esse nome aparece em propostas, dashboards e comunicações.
                            </p>
                        </div>

                    </div>
                </div>

                {{-- Card: Presença digital --}}
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-base font-semibold text-slate-900">
                        Presença digital
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Links públicos que serão usados em comunicações geradas pela plataforma.
                    </p>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        
                        {{-- Site --}}
                        <div>
                            <label for="website_url" class="block text-sm font-medium text-slate-700">
                                Site da empresa
                            </label>
                            <input
                                type="url"
                                name="website_url"
                                id="website_url"
                                value="{{ old('website_url', $company->website_url ?? '') }}"
                                placeholder="https://www.suaempresa.com.br"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                        </div>

                        {{-- WhatsApp comercial (exibição) --}}
                        <div>
                            <label for="whatsapp_number" class="block text-sm font-medium text-slate-700">
                                WhatsApp comercial (exibição)
                            </label>
                            <input
                                type="text"
                                name="whatsapp_number"
                                id="whatsapp_number"
                                value="{{ old('whatsapp_number', $company->whatsapp_number ?? '') }}"
                                placeholder="(62) 9 9999-9999"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                            <p class="mt-1 text-xs text-slate-500">
                                Apenas para exibição em propostas. O número da API é configurado abaixo.
                            </p>
                        </div>

                        {{-- Instagram --}}
                        <div>
                            <label for="instagram_url" class="block text-sm font-medium text-slate-700">
                                Instagram
                            </label>
                            <input
                                type="url"
                                name="instagram_url"
                                id="instagram_url"
                                value="{{ old('instagram_url', $company->instagram_url ?? '') }}"
                                placeholder="https://instagram.com/suaempresa"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                        </div>

                        {{-- Facebook --}}
                        <div>
                            <label for="facebook_url" class="block text-sm font-medium text-slate-700">
                                Facebook
                            </label>
                            <input
                                type="url"
                                name="facebook_url"
                                id="facebook_url"
                                value="{{ old('facebook_url', $company->facebook_url ?? '') }}"
                                placeholder="https://facebook.com/suaempresa"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                        </div>

                        {{-- LinkedIn --}}
                        <div class="md:col-span-2">
                            <label for="linkedin_url" class="block text-sm font-medium text-slate-700">
                                LinkedIn
                            </label>
                            <input
                                type="url"
                                name="linkedin_url"
                                id="linkedin_url"
                                value="{{ old('linkedin_url', $company->linkedin_url ?? '') }}"
                                placeholder="https://www.linkedin.com/company/suaempresa"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                        </div>

                    </div>
                </div>

                {{-- Card: Integração Z-API --}}
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-base font-semibold text-slate-900">
                        Integração WhatsApp (Z-API)
                    </h2>
                  
                   

                    <div class="mt-6 grid gap-6 md:grid-cols-2">

                        <div>
                            <label for="zapi_instance_id" class="block text-sm font-medium text-slate-700">
                                Z-API Instance ID
                            </label>
                            <input
                                type="text"
                                name="zapi_instance_id"
                                id="zapi_instance_id"
                                value="{{ old('zapi_instance_id', $company->zapi_instance_id ?? '') }}"
                                placeholder="3EAD1E37BD2EF1DA..."
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                            <p class="mt-1 text-xs text-slate-500">
                                ID da instância no Z-API (cada instância já tem um número do WhatsApp associado).
                            </p>
                        </div>

                        <div>
                            <label for="zapi_token" class="block text-sm font-medium text-slate-700">
                                Z-API Token
                            </label>
                            <input
                                type="password"
                                name="zapi_token"
                                id="zapi_token"
                                value="{{ old('zapi_token', $company->zapi_token ?? '') }}"
                                placeholder="01136EDE942F8A04..."
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                            <p class="mt-1 text-xs text-slate-500">
                                PATH_TOKEN (token da instância que vai no path da URL, não é o Security Token da conta).
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="zapi_base_url" class="block text-sm font-medium text-slate-700">
                                Z-API Base URL
                            </label>
                            <input
                                type="text"
                                name="zapi_base_url"
                                id="zapi_base_url"
                                value="{{ old('zapi_base_url', $company->zapi_base_url ?? '') }}"
                                placeholder="https://api.z-api.io"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                        </div>

                        <div class="md:col-span-2 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-xs text-slate-600">
                            <p class="font-medium mb-1">Como funciona o fallback:</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                <li>Se estes campos estiverem <strong>preenchidos</strong>, esta empresa usará sua própria instância Z-API.</li>
                                <li>Se estiverem <strong>vazios</strong>, o sistema usa o número padrão configurado no <code>.env</code>.</li>
                            </ul>
                        </div>

                    </div>
                </div>

                {{-- Ações --}}
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                    >
                        Salvar alterações
                    </button>
                </div>

            </form>

        </div>
    </div>

</x-app-layout>
