<?php

namespace App\Http\Controllers;

use App\Models\Empreendimento;
use App\Models\Incorporadora;
use Illuminate\Http\Request;

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
          'incorporadora_id' => ['nullable', 'exists:incorporadoras,id'],
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
        ]);

        $data['ativo'] = $request->has('ativo') ? 1 : 0;
        $data['company_id'] = auth()->user()->company_id ?? 1; // ajusta se tiver multi-tenant diferente

        // Normalizar campos JSON (se vierem preenchidos)
        foreach (['tabela_descontos', 'amenidades', 'imagens'] as $jsonField) {
            if (!empty($data[$jsonField])) {
                // Tentativa de validar/normalizar JSON
                $decoded = json_decode($data[$jsonField], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$jsonField] = json_encode($decoded);
                } else {
                    // Se der erro, você pode ou limpar, ou lançar validation error; por enquanto limpa
                    $data[$jsonField] = null;
                }
            }
        }

        Empreendimento::create($data);

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
        'nome'                 => ['required', 'string', 'max:255'],
        'incorporadora_id' => ['nullable', 'exists:incorporadoras,id'],
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
        'ativo'                => ['nullable'],
    ]);

    $data['ativo'] = $request->has('ativo') ? 1 : 0;

    // Normalizar JSON
    foreach (['tabela_descontos', 'amenidades', 'imagens'] as $field) {
        if (!empty($data[$field])) {
            $decoded = json_decode($data[$field], true);
            $data[$field] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } else {
            $data[$field] = null;
        }
    }

    $e->update($data);

   return redirect()
    ->route('admin.empreendimentos.edit', $e->id)
    ->with('ok', 'Empreendimento atualizado com sucesso!');

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

}
