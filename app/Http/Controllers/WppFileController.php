<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\WppSender;
use App\Models\Empreendimento;
use App\Models\EmpreendimentoAsset;

class WppFileController extends Controller
{
    /**
     * POST /api/wpp/send-file
     *
     * Params:
     * - phone (string) - obrigatório
     * - empreendimento_id (int) - obrigatório
     * - type (string|null) - opcional: pdf|doc|image|video|sheet|slide|any
     * - q (string|null) - opcional palavra-chave: "planta", "tabela", "brochura", "apresentação", etc.
     */
    public function sendFile(Request $r, WppSender $sender)
    {
        $data = $r->validate([
            'phone'             => ['required','string'],
            'empreendimento_id' => ['required','integer','exists:empreendimentos,id'],
            'type'              => ['nullable','string'],
            'q'                 => ['nullable','string'],
        ]);

        $e = Empreendimento::findOrFail($data['empreendimento_id']);

        // Filtros por tipo -> mapeia para MIMEs comuns
        $type = $data['type'] ? strtolower($data['type']) : 'any';

        $query = EmpreendimentoAsset::query()
            ->where('empreendimento_id', $e->id);

        $query->where(function($q2) use ($type) {
            switch ($type) {
                case 'pdf':
                    $q2->where('mime', 'application/pdf');
                    break;
                case 'doc':
                    $q2->whereIn('mime', [
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/plain',
                    ]);
                    break;
                case 'image':
                    $q2->where('mime', 'like', 'image/%');
                    break;
                case 'video':
                    $q2->where('mime', 'like', 'video/%');
                    break;
                case 'sheet':
                    $q2->whereIn('mime', [
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                    ]);
                    break;
                case 'slide':
                    $q2->whereIn('mime', [
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    ]);
                    break;
                case 'any':
                default:
                    // sem filtro de mime
                    break;
            }
        });

        if (!empty($data['q'])) {
            $kw = Str::lower($data['q']);
            $query->where(function($qq) use ($kw) {
                $like = "%{$kw}%";
                $qq->whereRaw('LOWER(file_name) LIKE ?', [$like])
                   ->orWhereRaw('LOWER(path) LIKE ?', [$like])
                   ->orWhereRaw('LOWER(COALESCE(title, "")) LIKE ?', [$like]);
            });
        }

        // pega o mais recente
        $asset = $query->orderByDesc('id')->first();

        if (!$asset) {
            return response()->json([
                'ok' => false,
                'message' => 'Nenhum arquivo encontrado com esses critérios.',
            ], 404);
        }

        // Descobre um nome de arquivo e envia
        $fileName = $asset->file_name ?: basename($asset->path);
        $out = $sender->sendFileFromS3($data['phone'], $asset->path, $fileName);

        return response()->json($out, $out['ok'] ? 200 : 422);
    }
}
