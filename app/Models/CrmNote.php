<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmNote extends Model
{
    use SoftDeletes;

    protected $table = 'crm_notes';

    protected $fillable = [
        'company_id',
        'corretor_id',
        'lead_id',
        'empreendimento_id',
        'conteudo',
        'origem',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function corretor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function empreendimento(): BelongsTo
    {
        return $this->belongsTo(Empreendimento::class);
    }

    // Scopes
    public function scopePorCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePorCorretor($query, int $corretorId)
    {
        return $query->where('corretor_id', $corretorId);
    }

    public function scopeRecentes($query, int $limit = 20)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
