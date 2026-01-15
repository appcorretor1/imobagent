<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmAudit extends Model
{
    protected $table = 'crm_audits';

    protected $fillable = [
        'company_id',
        'auditable_type',
        'auditable_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'origem',
        'observacoes',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopePorCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePorAcao($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopePorOrigem($query, string $origem)
    {
        return $query->where('origem', $origem);
    }

    public function scopePorPeriodo($query, $inicio = null, $fim = null)
    {
        if ($inicio) {
            $query->where('created_at', '>=', $inicio);
        }
        if ($fim) {
            $query->where('created_at', '<=', $fim);
        }
        return $query;
    }
}
