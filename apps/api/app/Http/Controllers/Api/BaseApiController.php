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
