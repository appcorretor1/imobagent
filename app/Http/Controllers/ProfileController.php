<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
   public function update(ProfileUpdateRequest $request): RedirectResponse
{
    $user = $request->user();

    // Pega os dados validados
    $data = $request->validated();

    // Não queremos tentar dar fill em 'avatar' (é arquivo)
    unset($data['avatar']);

    // Preenche os campos normais (name, email, etc.)
    $user->fill($data);

    // Se mudou o email, zera verificação
    if ($user->isDirty('email')) {
        $user->email_verified_at = null;
    }

    // Upload da foto, se enviada
    if ($request->hasFile('avatar')) {
        $companyId = $user->company_id ?? 0;

        $dir  = "documentos/tenants/{$companyId}/avatars";
        $ext  = $request->file('avatar')->getClientOriginalExtension();
        $name = 'avatar_' . uniqid() . '.' . $ext;

        $file = $request->file('avatar');

        // IMPORTANTE: sem 'public' aqui, para não usar ACL
        $path = \Illuminate\Support\Facades\Storage::disk('s3')->putFileAs(
            $dir,
            $file,
            $name
        );

        // Em vez de salvar a URL completa, salvamos o PATH no bucket
        // Ex.: documentos/tenants/1/avatars/avatar_xxx.png
        $user->avatar_url = $path;
    }

    $user->save();

    return Redirect::route('profile.edit')->with('status', 'profile-updated');
}


    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
