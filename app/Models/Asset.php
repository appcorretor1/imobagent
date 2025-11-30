<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'empreend_id', 'tenant_id',
        'path', 'original_name', 'mime', 'size',
        'vector_file_id', 'extracted_at',
    ];

    protected $dates = ['extracted_at'];

    public function text()
    {
        return $this->hasOne(AssetText::class, 'asset_id');
    }

    public function empreend()
    {
        return $this->belongsTo(Empreendimento::class, 'empreend_id');
    }
}
