<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

<form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;
@endphp

<!---- AVATAR ----------->

<div class="col-span-6 sm:col-span-4">
    <x-input-label for="avatar" value="Foto do corretor" />

    <div class="flex items-center gap-4 mt-2">
        @php
            $avatarUrl = null;

            if (!empty($user->avatar_url)) {
                // avatar_url agora é o path no S3
                $avatarUrl = Storage::disk('s3')->temporaryUrl(
                    $user->avatar_url,
                    now()->addMinutes(10)
                );
            }
        @endphp

        @if($avatarUrl)
            <img src="{{ $avatarUrl }}"
                 alt="Foto do corretor"
                 class="h-16 w-16 rounded-full object-cover">
        @else
            <div class="h-16 w-16 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-semibold text-indigo-700">
                {{ Str::upper(mb_substr($user->name, 0, 1)) }}
            </div>
        @endif

        <div class="flex flex-col">
            <input
                id="avatar"
                name="avatar"
                type="file"
                class="mt-1 block w-full text-sm"
                accept="image/*"
            >
            <p class="mt-1 text-xs text-slate-500">
                Formatos aceitos: JPG, PNG, WEBP. Tamanho máximo: 2MB.
            </p>

            @error('avatar')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>


        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
