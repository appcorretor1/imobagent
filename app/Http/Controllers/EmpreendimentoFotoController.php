<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Models\Empreendimento;

class EmpreendimentoFotoController extends Controller
{
    public function index($company, $empreend)
    {
        $disk   = Storage::disk('s3');
        $prefix = "documentos/tenants/{$company}/empreendimentos/{$empreend}/";

        $files  = $disk->files($prefix);

        $allowedImages = ['jpg','jpeg','png','gif','webp'];

        $photos = array_values(array_filter(
            $files,
            function ($p) use ($allowedImages) {
                $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                return in_array($ext, $allowedImages, true);
            }
        ));

        $urls = array_map(
            fn ($path) => $disk->url($path),
            $photos
        );

        // Busca nome do empreendimento (ajusta o model/campos se for diferente)
        $empreendimento = Empreendimento::with('incorporadora')->find($empreend);

        // Se você já tiver rota/arquivo ZIP pode montar aqui, senão deixa null
        $zipUrl = null;
        // Exemplo se você tiver um ZIP salvo:
        // $zipPath = "documentos/tenants/{$company}/empreendimentos/{$empreend}/fotos.zip";
        // if ($disk->exists($zipPath)) {
        //     $zipUrl = $disk->url($zipPath);
        // }

      return view('empreendimentos.fotos', [
    'urls'          => $urls,
    'company'       => $company,
    'empreend'      => $empreend,
    'empreendimento'=> $empreendimento,
    'zipUrl'        => $zipUrl,
]);

    }
}
