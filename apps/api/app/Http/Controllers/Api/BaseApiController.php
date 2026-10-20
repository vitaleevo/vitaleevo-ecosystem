<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

abstract class BaseApiController extends Controller
{
    protected function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('tenant_id');
    }

    protected function enforceBetaCapacity(string $resource, int $currentCount): void
    {
        if (! config('vitaleevo.beta.enabled')) {
            return;
        }

        $limits = [
            'tenants' => (int) config('vitaleevo.beta.max_tenants', 5),
            'professionals' => (int) config('vitaleevo.beta.max_professionals', 10),
        ];
        $limit = $limits[$resource] ?? 0;
        abort_if($limit > 0 && $currentCount >= $limit, 403, 'O piloto beta atingiu a capacidade configurada.');
    }

    protected function audit(Request $request, string $action, string $type, int|string|null $id = null, array $metadata = []): void
    {
        AuditLog::create([
            'tenant_id' => $request->attributes->get('tenant_id'),
            'user_id' => $request->user()?->id,
            'action' => $action,
            'resource_type' => $type,
            'resource_id' => $id,
            'metadata' => $metadata ?: null,
        ]);
    }
}
