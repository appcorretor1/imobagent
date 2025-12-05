<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Unidade extends Model
{
    use HasFactory;

    protected $fillable = [
        'empreendimento_id',
        'unidade',
        'torre',
        'status',
        'updated_at_google',
    ];

    protected $casts = [
        'updated_at_google' => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function empreendimento()
    {
        return $this->belongsTo(Empreendimento::class);
    }
}
