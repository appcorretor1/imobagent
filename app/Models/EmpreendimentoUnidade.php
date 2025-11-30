<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpreendimentoUnidade extends Model
{
    protected $table = 'empreendimento_unidades';

    protected $fillable = [
        'empreendimento_id',
        'grupo_unidade', // ex: "Torre 1", "Quadra A", "Bloco B", "Ala Norte"
        'unidade',       // ex: "101", "Casa 02", "Suíte 304"
        'status',
    ];

    public const STATUS_LIVRE     = 'livre';
    public const STATUS_RESERVADO = 'reservado';
    public const STATUS_FECHADO   = 'fechado';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_LIVRE     => 'Livre',
            self::STATUS_RESERVADO => 'Reservado',
            self::STATUS_FECHADO   => 'Fechado',
        ];
    }

    public function empreendimento()
    {
        return $this->belongsTo(Empreendimento::class);
    }

    // Scopes úteis
    public function scopeLivres($q)
    {
        return $q->where('status', self::STATUS_LIVRE);
    }

    public function scopeReservados($q)
    {
        return $q->where('status', self::STATUS_RESERVADO);
    }

    public function scopeFechados($q)
    {
        return $q->where('status', self::STATUS_FECHADO);
    }
}
