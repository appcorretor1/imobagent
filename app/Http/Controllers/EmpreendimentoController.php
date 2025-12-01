<?php

namespace App\Http\Controllers;

use App\Models\Empreendimento;
use App\Models\Incorporadora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class EmpreendimentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index(Request $request)
{
    $companyId = auth()->user()->company_id ?? 1;

    $incorporadoras = Incorporadora::where('company_id', $companyId)
        ->orderBy('nome')
        ->get();

    $q = Empreendimento::where('company_id', $companyId);

    if ($request->filled('incorporadora_id')) {
        $q->where('incorporadora_id', $request->incorporadora_id);
    }

    if ($request->filled('search')) {
        $q->where('nome', 'like', '%' . $request->search . '%');
    }

    $empreendimentos = $q->orderBy('nome')
        ->paginate(15)
        ->withQueryString();

    return view('admin.empreendimentos.index', compact('empreendimentos', 'incorporadoras'));
}



    /**
     * Show the form for creating a new resource.
     */
   public function create()
{
    $companyId = auth()->user()->company_id ?? 1;
    $incorporadoras = Incorporadora::where('company_id', $companyId)
        ->orderBy('nome')
        ->get();

    return view('admin.empreendimentos.create', compact('incorporadoras'));
}


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
{
    $data = $request->validate([
        'ativo'                => ['nullable'],
        'nome'                 => ['required', 'string', 'max:255'],
        'incorporadora_id'     => ['nullable', 'exists:incorporadoras,id'],
        'cidade'               => ['nullable', 'string', 'max:255'],
        'uf'                   => ['nullable', 'string', 'max:2'],
        'endereco'             => ['nullable', 'string', 'max:255'],
        'cep'                  => ['nullable', 'string', 'max:12'],
        'tipologia'            => ['nullable', 'string', 'max:255'],
        'metragem'             => ['nullable', 'string', 'max:255'],
        'preco_base'           => ['nullable', 'numeric'],
        'tabela_descontos'     => ['nullable', 'string'],
        'amenidades'           => ['nullable', 'string'],
        'imagens'              => ['nullable', 'string'],
        'descricao'            => ['nullable', 'string'],
        'disponibilidade_texto'=> ['nullable', 'string'],
        'pdf_url'              => ['nullable', 'string', 'max:255'],
        'contexto_ia'          => ['nullable', 'string'],
        'texto_ia'             => ['nullable', 'string'],

        // novos
        'banner_thumb'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp'],
        'logo_path'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp'],
    ]);

    $data['ativo'] = $request->has('ativo') ? 1 : 0;
    $data['company_id'] = auth()->user()->company_id;

    // Corrigir JSON fields
    foreach (['tabela_descontos', 'amenidades', 'imagens'] as $jsonField) {
        if (!empty($data[$jsonField])) {
            $decoded = json_decode($data[$jsonField], true);
            $data[$jsonField] = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : null;
        }
    }

    // Upload banner
    if ($request->hasFile('banner_thumb')) {
        $data['banner_thumb'] = $request->file('banner_thumb')->store(
            "documentos/tenants/{$data['company_id']}/empreendimentos/banner",
            's3'
        );
    }

       // Upload logo
    if ($request->hasFile('logo_path')) {
        $data['logo_path'] = $request->file('logo_path')->store(
            "documentos/tenants/{$data['company_id']}/empreendimentos/logo",
            's3'
        );
    }

    // 🔹 Cria o empreendimento
    $empreendimento = Empreendimento::create($data);

    // 🔹 Dispara webhook para o Make avisando os corretores
    $this->notifyCorretoresNovoEmpreendimento($empreendimento);

    return redirect()
        ->route('admin.empreendimentos.index')
        ->with('ok', 'Empreendimento criado com sucesso!');
}


    /**
     * Display the specified resource.
     */
    public function show(Empreendimento $empreendimento)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
public function edit(Empreendimento $e)
{
    $companyId = auth()->user()->company_id ?? 1;
    $incorporadoras = Incorporadora::where('company_id', $companyId)
        ->orderBy('nome')
        ->get();

    return view('admin.empreendimentos.edit', compact('e', 'incorporadoras'));
}
public function update(Request $request, Empreendimento $e)
{
    $data = $request->validate([
        'ativo'                => ['nullable'],
        'nome'                 => ['required', 'string', 'max:255'],
        'incorporadora_id'     => ['nullable', 'exists:incorporadoras,id'],
        'cidade'               => ['nullable', 'string', 'max:255'],
        'uf'                   => ['nullable', 'string', 'max:2'],
        'endereco'             => ['nullable', 'string', 'max:255'],
        'cep'                  => ['nullable', 'string', 'max:12'],
        'tipologia'            => ['nullable', 'string', 'max:255'],
        'metragem'             => ['nullable', 'string', 'max:255'],
        'preco_base'           => ['nullable', 'numeric'],
        'tabela_descontos'     => ['nullable', 'string'],
        'amenidades'           => ['nullable', 'string'],
        'imagens'              => ['nullable', 'string'],
        'descricao'            => ['nullable', 'string'],
        'disponibilidade_texto'=> ['nullable', 'string'],
        'pdf_url'              => ['nullable', 'string', 'max:255'],
        'contexto_ia'          => ['nullable', 'string'],
        'texto_ia'             => ['nullable', 'string'],

        // novos campos
        'banner_thumb'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp'],
        'logo_path'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp'],
    ]);

    $data['ativo'] = $request->has('ativo') ? 1 : 0;

    foreach (['tabela_descontos', 'amenidades', 'imagens'] as $jsonField) {
        if (!empty($data[$jsonField])) {
            $decoded = json_decode($data[$jsonField], true);
            $data[$jsonField] = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : null;
        }
    }

    // Upload banner
    if ($request->hasFile('banner_thumb')) {
        $data['banner_thumb'] = $request->file('banner_thumb')->store(
            "documentos/tenants/{$e->company_id}/empreendimentos/banner",
            's3'
        );
    }

    // Upload logo
    if ($request->hasFile('logo_path')) {
        $data['logo_path'] = $request->file('logo_path')->store(
            "documentos/tenants/{$e->company_id}/empreendimentos/logo",
            's3'
        );
    }

    $e->update($data);

    return redirect()
        ->route('admin.empreendimentos.index')
        ->with('ok', 'Empreendimento atualizado!');
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Empreendimento $empreendimento)
    {
        //
    }

    public function editTexto(Empreendimento $e)
{
    return view('admin.empreendimentos.texto', compact('e'));
}

public function updateTexto(Request $request, Empreendimento $e)
{
    $request->validate([
        'texto_ia' => 'nullable|string',
    ]);

    $e->texto_ia = $request->texto_ia;
    $e->save();

    return redirect()->route('admin.empreendimentos.index')->with('ok', 'Texto atualizado com sucesso!');
}

    /**
     * Dispara webhook para o Make avisando todos os corretores da empresa
     * sobre o novo empreendimento cadastrado.
     */
    protected function notifyCorretoresNovoEmpreendimento(Empreendimento $empreendimento): void
    {
        $companyId = $empreendimento->company_id;

        // 🧑‍💼 Corretores da empresa (ajuste role/campo se for diferente)
        $corretores = DB::table('users')
            ->where('company_id', $companyId)
            ->whereIn('role', ['corretor', 'diretor']) // ajuste conforme seus roles
            ->whereNotNull('phone')
            ->get(['id', 'name', 'phone']);

        if ($corretores->isEmpty()) {
            \Log::info('NOVO EMP: nenhum corretor para notificar', [
                'company_id' => $companyId,
                'emp_id'     => $empreendimento->id,
            ]);
            return;
        }

        // 🏢 Dados da empresa + credenciais Z-API salvas nos dados da empresa
        $company = DB::table('companies')->where('id', $companyId)->first();

        if (!$company) {
            \Log::warning('NOVO EMP: empresa não encontrada para notificação', [
                'company_id' => $companyId,
                'emp_id'     => $empreendimento->id,
            ]);
            return;
        }

        // 🔁 Monta payload para o Make
      $payload = [
    'company_id'     => $companyId,
    'empreendimento' => [
        'id'            => $empreendimento->id,
        'nome'          => $empreendimento->nome,
        'cidade'        => $empreendimento->cidade,
        'uf'            => $empreendimento->uf,
        'tipologia'     => $empreendimento->tipologia,
        'metragem'      => $empreendimento->metragem,
        'preco_base'    => $empreendimento->preco_base,

        // 👇 gera URL completa do S3
        'banner_thumb'  => $empreendimento->banner_thumb
            ? Storage::disk('s3')->url($empreendimento->banner_thumb)
            : null,

        'logo_path'     => $empreendimento->logo_path
            ? Storage::disk('s3')->url($empreendimento->logo_path)
            : null,

        'disponibilidade' => $empreendimento->disponibilidade_texto,
        'incorporadora'   => $empreendimento->incorporadora,
    ],
            'corretores'     => $corretores->map(function ($c) {
                return [
                    'id'    => $c->id,
                    'nome'  => $c->name,
                    'phone' => $c->phone,
                ];
            })->values()->toArray(),

            // 🔐 Credenciais da Z-API da empresa (já salvas em Dados da Empresa)
            'zapi_instance_id' => $company->zapi_instance_id ?? null,
            'zapi_token'       => $company->zapi_token ?? null,
            'zapi_base_url'    => $company->zapi_base_url ?? null,
        ];

        try {
            $webhookUrl = config('services.make.webhook_novo_empreendimento');

            if (!$webhookUrl) {
                \Log::warning('NOVO EMP: webhook do Make não configurado', [
                    'company_id' => $companyId,
                    'emp_id'     => $empreendimento->id,
                ]);
                return;
            }

            Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($webhookUrl, $payload);

            \Log::info('NOVO EMP: payload enviado ao Make', [
                'company_id' => $companyId,
                'emp_id'     => $empreendimento->id,
                'corretores' => count($payload['corretores']),
            ]);
        } catch (\Throwable $e) {
            \Log::error('NOVO EMP: erro ao enviar webhook para Make', [
                'error'      => $e->getMessage(),
                'company_id' => $companyId,
                'emp_id'     => $empreendimento->id,
            ]);
        }
    }


}
