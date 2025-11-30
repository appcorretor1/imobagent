<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'thread_id',
        'company_id',
        'corretor_id',
        'phone',
        'sender',
        'type',
        'body',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function thread()
    {
        return $this->belongsTo(WhatsappThread::class, 'thread_id');
    }
}
