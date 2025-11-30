<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappThread extends Model
{
    protected $table = 'whatsapp_threads';

    protected $fillable = [
        'phone',
        'thread_id',
        'state',
        'context',
        'selected_empreendimento_id',
        'empreendimento_id',
    ];

    protected $casts = [
        'context' => 'array',
        'selected_empreendimento_id' => 'integer',
        'empreendimento_id' => 'integer',
    ];
}
