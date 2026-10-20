<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = $user?->current_tenant_id;

        abort_unless($tenantId, 409, 'Selecione uma empresa ativa.');

        $membership = Membership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        abort_unless($membership, 403, 'Sem acesso a esta empresa.');
        abort_unless(Tenant::whereKey($tenantId)->where('status', 'active')->exists(), 403, 'Empresa suspensa ou inativa.');

        $request->attributes->set('tenant_id', (int) $tenantId);
        $request->attributes->set('membership_role', $membership->role);

        return $next($request);
    }
}
