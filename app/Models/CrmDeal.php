<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\CrmDealStatus;
use Carbon\Carbon;

class CrmDeal extends Model
{
    use SoftDeletes;

    protected $table = 'crm_deals';

    protected $fillable = [
        'company_id',
        'lead_id',
        'corretor_id',
        'empreendimento_id',
        'empreendimento_nome',
        'empreendimento_nome_normalizado',
        'unidade',
        'torre',
        'tipo',
        'status',
        'valor',
        'valor_entrada',
        'condicoes_pagamento',
        'observacoes',
        'enviado_em',
        'resposta_em',
        'fechado_em',
        'origem',
        'metadata',
    ];

    protected $casts = [
        'status' => CrmDealStatus::class,
        'valor' => 'decimal:2',
        'valor_entrada' => 'decimal:2',
        'enviado_em' => 'datetime',
        'resposta_em' => 'datetime',
        'fechado_em' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    public function empreendimento(): BelongsTo
    {
        return $this->belongsTo(Empreendimento::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CrmInteraction::class, 'deal_id');
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

    public function scopePorStatus($query, CrmDealStatus|string $status)
    {
        $statusValue = $status instanceof CrmDealStatus ? $status->value : $status;
        return $query->where('status', $statusValue);
    }

    public function scopeAguardandoResposta($query)
    {
        return $query->where('status', CrmDealStatus::AGUARDANDO_RESPOSTA->value);
    }

    public function scopePropostas($query)
    {
        return $query->where('tipo', 'proposta');
    }

    public function scopeVendas($query)
    {
        return $query->where('tipo', 'venda');
    }

    public function scopeFechadas($query)
    {
        return $query->where('status', CrmDealStatus::FECHADA->value);
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
    public function fechar(): void
    {
        $this->status = CrmDealStatus::FECHADA;
        $this->fechado_em = Carbon::now();
        $this->save();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getValorFormatadoAttribute(): string
    {
        return $this->valor ? 'R$ ' . number_format($this->valor, 2, ',', '.') : '-';
    }
}
