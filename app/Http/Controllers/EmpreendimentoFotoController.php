<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class EmpreendimentoFotoController extends Controller
{
    public function index($company, $empreend)
    {
        $disk = Storage::disk('s3');

        $prefix = "documentos/tenants/{$company}/empreendimentos/{$empreend}/";
        $files  = $disk->files($prefix);

        $allowedImages = ['jpg','jpeg','png','gif','webp'];

        $photos = array_values(array_filter(
            $files,
            fn ($p) => in_array(
                strtolower(pathinfo($p, PATHINFO_EXTENSION)),
                $allowedImages,
                true
            )
        ));

        $urls = array_map(
            fn ($path) => $disk->url($path),
            $photos
        );

        return view('empreendimentos.fotos', [
            'urls'      => $urls,
            'company'   => $company,
            'empreend'  => $empreend,
        ]);
    }
}
