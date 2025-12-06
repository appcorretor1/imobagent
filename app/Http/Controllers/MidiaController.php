<?php

namespace App\Http\Controllers;

use App\Models\EmpreendimentoMidia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MidiaController extends Controller
{
    /**
     * Upload de fotos/vídeos de um empreendimento
     * ligado a um corretor específico.
     *
     * Espera:
     *  - empreendimento_id (int)
     *  - midia[] (array de arquivos)
     *  - opcional: corretor_id (se não tiver auth(), ex: fluxo WhatsApp)
     */
    public function store(Request $request)
    {
        $request->validate([
            'empreendimento_id' => 'required|integer',
            'midia'             => 'required',
            'midia.*'           => 'file',
        ]);

        $empreendimentoId = (int) $request->input('empreendimento_id');

        // Pega o corretor logado OU do request (caso esteja chamando via integração)
        $corretorId = Auth::id() ?? $request->input('corretor_id');

        if (!$corretorId) {
            return response()->json([
                'message' => 'Corretor não identificado.',
            ], 403);
        }

        // Pasta separada por empreendimento + corretor
        // Ex: midias/empreendimentos/10/corretores/5/
        $pasta = "midias/empreendimentos/{$empreendimentoId}/corretores/{$corretorId}/";

        $linksArquivos = [];

        foreach ((array) $request->file('midia') as $arquivo) {
            if (!$arquivo) {
                continue;
            }

            $mime = $arquivo->getMimeType();
            $tipo = str_contains($mime, 'video')
                ? 'video'
                : (str_contains($mime, 'image') ? 'foto' : 'outro');

            // Upload para S3
            $path = $arquivo->store($pasta, 's3');

            // Salva no banco
            EmpreendimentoMidia::create([
                'empreendimento_id' => $empreendimentoId,
                'corretor_id'       => $corretorId,
                'arquivo_path'      => $path,
                'arquivo_tipo'      => $tipo,
            ]);

            $linksArquivos[] = Storage::disk('s3')->url($path);
        }

        // Link “base” da galeria desse corretor naquele empreendimento
        $linkGaleria = rtrim(Storage::disk('s3')->url($pasta), '/');

        return response()->json([
            'empreendimento_id' => $empreendimentoId,
            'corretor_id'       => $corretorId,
            'galeria'           => $linkGaleria,
            'arquivos'          => $linksArquivos,
        ]);
    }

    /**
     * Lista mídias de um empreendimento para o corretor dono.
     *
     * Se tiver auth, usa auth()->id().
     * Se for via integração externa, pode receber corretor_id por query.
     */
    public function listarPorEmpreendimento(Request $request, int $empreendimentoId)
    {
        $corretorId = Auth::id() ?? $request->input('corretor_id');

        if (!$corretorId) {
            return response()->json([
                'message' => 'Corretor não identificado.',
            ], 403);
        }

        $midias = EmpreendimentoMidia::where('empreendimento_id', $empreendimentoId)
            ->where('corretor_id', $corretorId)
            ->get();

        $arquivos = $midias->map(function ($m) {
            return [
                'tipo' => $m->arquivo_tipo,
                'url'  => Storage::disk('s3')->url($m->arquivo_path),
            ];
        });

        $pasta = "midias/empreendimentos/{$empreendimentoId}/corretores/{$corretorId}/";
        $linkGaleria = rtrim(Storage::disk('s3')->url($pasta), '/');

        return response()->json([
            'empreendimento_id' => $empreendimentoId,
            'corretor_id'       => $corretorId,
            'galeria'           => $linkGaleria,
            'arquivos'          => $arquivos,
        ]);
    }
}
