<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenancyScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->role !== 'super_admin') {
            // Usuário comum -> empresa própria
            $request->attributes->set('tenant_company_id', $user->company_id);
        } else {
            // Super admin pode alternar empresa via query
            $companyId = (int) $request->query('company', 0);
            if ($companyId > 0) {
                $request->attributes->set('tenant_company_id', $companyId);
            }
        }

        return $next($request);
    }
}
