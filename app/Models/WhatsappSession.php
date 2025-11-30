<?php 

// app/Models/WhatsappSession.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model {
    protected $fillable = ['phone','state','empreendimento_id','last_interaction_at'];
    protected $casts = ['last_interaction_at' => 'datetime'];
}
