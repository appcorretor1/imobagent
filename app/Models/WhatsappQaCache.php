<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappQaCache extends Model
{
    protected $table = 'whatsapp_qa_cache';

    protected $fillable = [
        'empreendimento_id',
        'question_hash',
        'question_norm',
        'answer',
        'source',
        'hits',
        'last_hit_at',
    ];
}
