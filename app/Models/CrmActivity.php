<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\CrmActivityStatus;
use Carbon\Carbon;

class CrmActivity extends Model
{
    use SoftDeletes;

    protected $table = 'crm_activities';

    protected $fillable = [
        'company_id',
        'lead_id',
        'corretor_id',
        'empreendimento_id',
        'tipo',
        'titulo',
        'descricao',
        'agendado_para',
        'concluido_em',
        'status',
        'prioridade',
        'origem',
        'metadata',
    ];

    protected $casts = [
        'status' => CrmActivityStatus::class,
        'agendado_para' => 'datetime',
        'concluido_em' => 'datetime',
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
        return $this->hasMany(CrmInteraction::class, 'activity_id');
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

    public function scopePorStatus($query, CrmActivityStatus|string $status)
    {
        $statusValue = $status instanceof CrmActivityStatus ? $status->value : $status;
        return $query->where('status', $statusValue);
    }

    public function scopePendentes($query)
    {
        return $query->where('status', CrmActivityStatus::PENDENTE->value);
    }

    public function scopePorPeriodo($query, $inicio, $fim)
    {
        return $query->whereBetween('agendado_para', [$inicio, $fim]);
    }

    public function scopeAgendadas($query, ?Carbon $data = null)
    {
        $query->whereNotNull('agendado_para');
        if ($data) {
            $query->whereDate('agendado_para', $data);
        }
        return $query;
    }

    public function scopeHoje($query)
    {
        return $query->whereDate('agendado_para', Carbon::today());
    }

    public function scopeEstaSemana($query)
    {
        return $query->whereBetween('agendado_para', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
    }

    // Helpers
    public function concluir(): void
    {
        $this->status = CrmActivityStatus::CONCLUIDO;
        $this->concluido_em = Carbon::now();
        $this->save();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function isAtrasada(): bool
    {
        return $this->agendado_para 
            && $this->agendado_para->isPast() 
            && $this->status === CrmActivityStatus::PENDENTE;
    }
}
