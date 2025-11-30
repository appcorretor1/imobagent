<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    public function scopeForRequestCompany(Builder $q): Builder
    {
        $companyId = request()->attributes->get('tenant_company_id');
        return $companyId ? $q->where('company_id', $companyId) : $q;
    }
}
