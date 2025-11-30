<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incorporadora extends Model
{
    protected $table = 'incorporadoras';

    protected $fillable = [
        'company_id',
        'nome',
        'endereco',
        'cidade',
        'uf',
        'responsavel',
        'logo_path',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
