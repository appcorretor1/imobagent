<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\CrmLeadStatus;
use Carbon\Carbon;

class CrmLead extends Model
{
    use SoftDeletes;

    protected $table = 'crm_leads';

    protected $fillable = [
        'company_id',
        'corretor_id',
        'nome',
        'email',
        'phone',
        'whatsapp',
        'observacoes',
        'status',
        'score',
        'metadata',
        'ultimo_contato_at',
    ];

    protected $casts = [
        'status' => CrmLeadStatus::class,
        'score' => 'integer',
        'metadata' => 'array',
        'ultimo_contato_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relacionamentos
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function corretor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CrmInteraction::class, 'lead_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'lead_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'lead_id');
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

    public function scopePorStatus($query, CrmLeadStatus|string $status)
    {
        $statusValue = $status instanceof CrmLeadStatus ? $status->value : $status;
        return $query->where('status', $statusValue);
    }

    public function scopeAtivos($query)
    {
        return $query->whereNotIn('status', [
            CrmLeadStatus::PERDIDO->value,
            CrmLeadStatus::ARQUIVADO->value,
        ]);
    }

    public function scopeSemContato($query, int $dias = 7)
    {
        return $query->where(function($q) use ($dias) {
            $q->whereNull('ultimo_contato_at')
              ->orWhere('ultimo_contato_at', '<', Carbon::now()->subDays($dias));
        });
    }

    public function scopePorPeriodo($query, ?Carbon $inicio = null, ?Carbon $fim = null)
    {
        if ($inicio) {
            $query->where('created_at', '>=', $inicio);
        }
        if ($fim) {
            $query->where('created_at', '<=', $fim);
        }
        return $query;
    }

    // Helpers
    public function atualizarUltimoContato(): void
    {
        $this->ultimo_contato_at = Carbon::now();
        $this->save();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }
}
