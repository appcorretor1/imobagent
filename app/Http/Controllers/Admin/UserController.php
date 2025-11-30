<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Lista usuários da empresa do diretor.
     */
    public function index()
    {
        $companyId = Auth::user()->company_id;

        $users = User::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Formulário para criar novo usuário.
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Salva o usuário no banco e envia a senha via WhatsApp.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20',
            'role'  => 'required|in:diretor,corretor',
        ]);

        $password = Str::random(8);

        // 🔹 Normaliza telefone e garante DDI 55
        $rawPhone = preg_replace('/\D+/', '', $request->phone); // remove tudo que não é número
        $rawPhone = ltrim($rawPhone, '0'); // remove zeros à esquerda

        // Adiciona o DDI 55 se ainda não existir
        $phone = Str::startsWith($rawPhone, '55') ? $rawPhone : '55' . $rawPhone;

        $companyId = Auth::user()->company_id;

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'phone'      => $phone,
            'whatsapp'   => $phone,
            'role'       => $request->role,
            'company_id' => $companyId,
            'password'   => bcrypt($password),
            'is_active'  => true,
        ]);

        $appUrl = config('app.url') ?? url('/');
        $login  = $user->email;

        $message = "Olá {$user->name}! 👋\n\n"
            ."Seu acesso à plataforma foi criado.\n\n"
            ."🔗 Acesse: {$appUrl}\n"
            ."👤 Login: *{$login}*\n"
            ."🔐 Senha: *{$password}*\n\n"
            ."Por segurança, altere sua senha após o primeiro acesso. 😉";

        // Webhook do Make (cenário que chama /api/make/wpp/send)
        $webhookUrl = 'https://hook.us2.make.com/3hctnex6nju4h2xxqtuwwqds9ogf40rj';

       try {

    $payload = [
        'company_id' => $companyId,
        'phone'      => $phone,
        'message'    => $message,
    ];

   Log::info('USER CREATED → Enviando WhatsApp', [
    'company_id' => $companyId,
    'user_id'    => $user->id,
    'to'         => $phone,
]);

$resp = Http::post($webhookUrl, [
    'company_id' => $companyId,
    'phone'      => $phone,
    'message'    => $message,
]);

Log::info('USER CREATED → WhatsApp enviado', [
    'company_id' => $companyId,
    'user_id'    => $user->id,
    'to'         => $phone,
    'status'     => $resp->status(),
]);

} catch (\Throwable $e) {

    \Log::error('USER CREATED → Erro ao enviar WhatsApp', [
        'company_id' => $companyId,
        'user_id'    => $user->id,
        'to'         => $phone,
        'error'      => $e->getMessage(),
        'trace'      => $e->getTraceAsString(),
    ]);
}

        return redirect()
            ->route('admin.users.index')
            ->with('ok', 'Usuário criado e acesso enviado via WhatsApp!');
    }

    /**
     * Formulário de edição do usuário.
     */
    public function edit(User $user)
    {
        $companyId = Auth::user()->company_id;

        if ($user->company_id !== $companyId) {
            abort(403, 'Você não pode editar usuários de outra empresa.');
        }

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Atualiza os dados do usuário.
     */
    public function update(Request $request, User $user)
{
    $companyId = Auth::user()->company_id;

    if ($user->company_id !== $companyId) {
        abort(403, 'Você não pode editar usuários de outra empresa.');
    }

    $request->validate([
        'name'      => 'required|string|max:255',
        'email'     => 'required|email|max:255|unique:users,email,' . $user->id,
        'phone'     => 'required|string|max:20',
        'role'      => 'required|in:diretor,corretor',
        'is_active' => 'required|in:0,1',
    ]);

    // 🔹 Normaliza telefone igual ao store()
    $rawPhone = preg_replace('/\D+/', '', $request->phone);
    $rawPhone = ltrim($rawPhone, '0');
    $phone = \Illuminate\Support\Str::startsWith($rawPhone, '55') ? $rawPhone : '55' . $rawPhone;

    // 🔹 Converte is_active pra 0/1 de forma explícita
    $isActive = $request->input('is_active') == '1' ? 1 : 0;

    $user->update([
        'name'      => $request->name,
        'email'     => $request->email,
        'phone'     => $phone,
        'whatsapp'  => $phone,
        'role'      => $request->role,
        'is_active' => $isActive,
    ]);

    return redirect()
        ->route('admin.users.index')
        ->with('ok', 'Usuário atualizado com sucesso!');
}


    /**
     * Remover usuário (opcional, se for usar).
     */
    public function destroy(User $user)
    {
        $companyId = Auth::user()->company_id;

        if ($user->company_id !== $companyId) {
            abort(403, 'Você não pode excluir usuários de outra empresa.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('ok', 'Usuário removido com sucesso!');
    }

    /**
     * Alternar status: Ativo / Inativo.
     */
    public function toggleStatus(User $user)
    {
        $companyId = Auth::user()->company_id;

        if ($user->company_id !== $companyId) {
            abort(403, 'Você não pode alterar usuários de outra empresa.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('ok', 'Status do usuário atualizado com sucesso!');
    }

    /**
     * Reenviar acesso por WhatsApp (gera nova senha).
     */
    public function resendAccess(User $user)
    {
        $companyId = Auth::user()->company_id;

        if ($user->company_id !== $companyId) {
            abort(403, 'Você não pode reenviar acesso de outra empresa.');
        }

        $password = Str::random(8);
        $user->password = bcrypt($password);
        $user->save();

        $appUrl = config('app.url') ?? url('/');
        $login  = $user->email;

        $message = "Olá {$user->name}! 👋\n\n"
            ."Seu acesso à plataforma foi atualizado.\n\n"
            ."🔗 Acesse: {$appUrl}\n"
            ."👤 Login: *{$login}*\n"
            ."🔐 Nova senha: *{$password}*\n\n"
            ."Por segurança, altere sua senha após o próximo acesso. 😉";

        // Normaliza telefone antes de enviar
        $rawPhone = preg_replace('/\D+/', '', $user->phone);
        $rawPhone = ltrim($rawPhone, '0');
        $phone = Str::startsWith($rawPhone, '55') ? $rawPhone : '55' . $rawPhone;

        $webhookUrl = 'https://hook.us2.make.com/3hctnex6nju4h2xxqtuwwqds9ogf40rj';

       try {

    $payload = [
        'company_id' => $companyId,
        'phone'      => $user->phone,
        'message'    => $message,
    ];

 Log::info('RESEND ACCESS → Enviando WhatsApp', [
    'company_id' => $companyId,
    'user_id'    => $user->id,
    'to'         => $user->phone,
]);

$resp = Http::post($webhookUrl, [
    'company_id' => $companyId,
    'phone'      => $user->phone,
    'message'    => $message,
]);

Log::info('RESEND ACCESS → WhatsApp enviado', [
    'company_id' => $companyId,
    'user_id'    => $user->id,
    'to'         => $user->phone,
    'status'     => $resp->status(),
]);


} catch (\Throwable $e) {

    \Log::error('RESEND ACCESS → Erro ao enviar WhatsApp', [
        'company_id' => $companyId,
        'user_id'    => $user->id,
        'to'         => $user->phone,
        'error'      => $e->getMessage(),
        'trace'      => $e->getTraceAsString(),
    ]);
}

        return redirect()
            ->route('admin.users.index')
            ->with('ok', 'Acesso reenviado por WhatsApp!');
    }
}
