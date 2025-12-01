<?php

namespace App\Http\Controllers;

use App\Models\Incorporadora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IncorporadoraController extends Controller
{   


    public function index()
{
    $incorporadoras = Incorporadora::where('company_id', auth()->user()->company_id)
        ->orderBy('nome')
        ->get();

    return view('admin.incorporadoras.index', compact('incorporadoras'));
}

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

        // novos campos de arquivo
        'banner_thumb'         => ['nullable', 'image'],
        'logo_path'            => ['nullable', 'image'],
    ]);

    // Ajuste multi-tenant correto (NUNCA usar $request->company_id)
    $companyId = auth()->user()->company_id;
    $data['company_id'] = $companyId;
    $data['ativo'] = $request->has('ativo') ? 1 : 0;

    // Normalizar campos JSON
    foreach (['tabela_descontos', 'amenidades', 'imagens'] as $jsonField) {
        if (!empty($data[$jsonField])) {
            $decoded = json_decode($data[$jsonField], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data[$jsonField] = json_encode($decoded);
            } else {
                $data[$jsonField] = null;
            }
        }
    }

    // --------------------------------------------------------------
    // UPLOAD LOGO + BANNER (S3 / Tenant)
    // --------------------------------------------------------------

    // Upload de banner
    if ($request->hasFile('banner_thumb')) {
        $data['banner_thumb'] = $request->file('banner_thumb')->store(
            "documentos/tenants/{$companyId}/empreendimentos/tmp",
            's3'
        );
    }

    // Upload de logo
    if ($request->hasFile('logo_path')) {
        $data['logo_path'] = $request->file('logo_path')->store(
            "documentos/tenants/{$companyId}/empreendimentos/tmp",
            's3'
        );
    }

    // Criar empreendimento
    Empreendimento::create($data);

    return redirect()
        ->route('admin.empreendimentos.index')
        ->with('ok', 'Empreendimento criado com sucesso!');
}

    public function edit($id)
{
    $inc = Incorporadora::findOrFail($id);

    return view('admin.incorporadoras.edit', compact('inc'));
}

public function update(Request $request, $id)
{
    $inc = Incorporadora::findOrFail($id);

    $inc->update([
        'nome'        => $request->nome,
        'endereco'    => $request->endereco,
        'cidade'      => $request->cidade,
        'uf'          => $request->uf,
        'responsavel' => $request->responsavel,
    ]);

    if ($request->hasFile('logo_path')) {

        if ($inc->logo_path && Storage::disk('s3')->exists($inc->logo_path)) {
            Storage::disk('s3')->delete($inc->logo_path);
        }

        $inc->logo_path = $request->file('logo_path')->store(
            "documentos/tenants/{$inc->company_id}/incorporadoras/{$inc->id}",
            's3'
        );

        $inc->save();
    }

    return back()->with('success', 'Incorporadora atualizada com sucesso.');
}


}
