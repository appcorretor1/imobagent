<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model {
protected $fillable = [
    'name',
    'slug',
    'whatsapp_number',
    'logo_path',
    'website_url',
    'instagram_url',
    'facebook_url',
    'linkedin_url',
    'zapi_instance_id',
    'zapi_token',
    'zapi_base_url',
    'settings',
];

    protected $casts = ['settings'=>'array'];
    public function users(){ return $this->hasMany(User::class); }
    public function empreendimentos(){ return $this->hasMany(Empreendimento::class); }




    
}
