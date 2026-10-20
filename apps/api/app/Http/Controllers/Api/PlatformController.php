<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Professional;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformController extends BaseApiController
{
    public function metrics(): JsonResponse
    {
        return response()->json([
            'tenants'=>Tenant::count(), 'users'=>User::count(), 'contacts'=>Contact::count(),
            'service_requests'=>ServiceRequest::count(), 'professionals'=>Professional::count(),
            'active_tenants'=>Tenant::where('status','active')->count(),
        ]);
    }

    public function tenants(): JsonResponse
    {
        return response()->json(Tenant::latest()->paginate(100));
    }

    public function updateTenant(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update($request->validate(['status'=>['required','in:active,suspended'],'plan'=>['sometimes','string','max:80']]));
        return response()->json($tenant);
    }

    public function auditLogs(): JsonResponse
    {
        return response()->json(
            AuditLog::query()
                ->leftJoin('users','users.id','=','audit_logs.user_id')
                ->leftJoin('tenants','tenants.id','=','audit_logs.tenant_id')
                ->select('audit_logs.*','users.name as actor_name','users.email as actor_email','tenants.name as tenant_name')
                ->latest('audit_logs.created_at')
                ->paginate(100)
        );
    }

    public function publicRequests(): JsonResponse
    {
        return response()->json(
            ServiceRequest::whereNull('tenant_id')->latest()->paginate(100)
        );
    }

    public function assignRequest(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['tenant_id'=>['required','exists:tenants,id']]);
        $tenant = Tenant::whereKey($data['tenant_id'])->where('status','active')->firstOrFail();
        $item = ServiceRequest::whereNull('tenant_id')->findOrFail($id);
        $item->update(['tenant_id'=>$tenant->id,'status'=>'triage']);
        return response()->json($item);
    }
}
