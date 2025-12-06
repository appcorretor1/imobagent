<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpreendimentoMidia extends Model
{
    protected $table = 'empreendimento_midias';

    protected $fillable = [
        'empreendimento_id',
        'corretor_id',
        'arquivo_path',
        'arquivo_tipo',
    ];

   
    public function empreendimento()
    {
        return $this->belongsTo(Empreendimento::class, 'empreendimento_id');
    }

    public function corretor()
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }
}
