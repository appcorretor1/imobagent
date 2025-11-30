<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeAsset extends Model
{
    protected $fillable = [
        'company_id',
        'empreendimento_id',
        'original_name',
        'mime',
        'disk',
        'path',
        'kind',
        'status',
        'error',
        'size',
        'openai_file_id',
        'openai_vector_store_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function empreendimento()
    {
        return $this->belongsTo(Empreendimento::class, 'empreendimento_id');
    }

    // helpers opcionais
    public function humanSize(): string
    {
        $bytes = (int) ($this->size ?? 0);
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)       return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
