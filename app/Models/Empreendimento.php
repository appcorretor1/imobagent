<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Log;

class Empreendimento extends Model
{
    protected $table = 'empreendimentos';

   protected $fillable = [
    'company_id',
    'incorporadora_id',
    'nome',
    'cidade',
    'uf',
     'banner_thumb',
    'logo_path',
    'endereco',
    'cep',
    'tipologia',
    'metragem',
    'preco_base',
    'tabela_descontos',
    'amenidades',
    'imagens',
    'descricao',
    'disponibilidade_texto',
    'pdf_url',
    'contexto_ia',
    'texto_ia',
    'ativo'
];

    protected $casts = [
        'tabela_descontos' => 'array',
        'amenidades'       => 'array',
        'imagens'          => 'array',
        'preco_base'       => 'decimal:2',
        'ativo'            => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeAtivos($query)
    {
        if (Schema::hasColumn($this->getTable(), 'ativo')) {
            return $query->where('ativo', true);
        }
        return $query;
    }

    public function incorporadora()
{
    return $this->belongsTo(Incorporadora::class);
}

public function unidades()
{
    return $this->hasMany(\App\Models\EmpreendimentoUnidade::class);
}

public function assets()
{
    return $this->hasMany(Asset::class);
}



}
