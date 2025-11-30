<?php

namespace App\Http\Controllers;

use App\Models\Incorporadora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IncorporadoraController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'nome'        => ['required', 'string', 'max:255'],
            'endereco'    => ['nullable', 'string', 'max:255'],
            'cidade'      => ['nullable', 'string', 'max:255'],
            'uf'          => ['nullable', 'string', 'max:2'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'logo'        => ['nullable', 'image', 'max:2048'], // 2MB
        ]);

        $companyId = auth()->user()->company_id ?? 1;
        $data['company_id'] = $companyId;

        $data['logo_path'] = null;
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store(
                "documentos/tenants/{$companyId}/incorporadoras",
                's3' // ou 'public', se preferir
            );
        }

        Incorporadora::create($data);

        return redirect()
            ->route('admin.empreendimentos.index')
            ->with('ok', 'Incorporadora cadastrada com sucesso!');
    }
}
