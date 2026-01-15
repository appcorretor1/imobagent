<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CrmInteraction extends Model
{
    protected $table = 'crm_interactions';

    protected $fillable = [
        'company_id',
        'lead_id',
        'corretor_id',
        'deal_id',
        'activity_id',
        'tipo',
        'direcao',
        'conteudo',
        'origem',
        'metadata',
        'ocorrido_em',
    ];

    protected $casts = [
        'metadata' => 'array',
        'ocorrido_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function corretor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CrmActivity::class, 'activity_id');
    }

    // Scopes
    public function scopePorCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePorLead($query, int $leadId)
    {
        return $query->where('lead_id', $leadId);
    }

    public function scopePorCorretor($query, int $corretorId)
    {
        return $query->where('corretor_id', $corretorId);
    }

    public function scopePorPeriodo($query, ?Carbon $inicio = null, ?Carbon $fim = null)
    {
        if ($inicio) {
            $query->where('ocorrido_em', '>=', $inicio);
        }
        if ($fim) {
            $query->where('ocorrido_em', '<=', $fim);
        }
        return $query;
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
