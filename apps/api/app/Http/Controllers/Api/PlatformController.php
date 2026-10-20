<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\Professional;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformController extends BaseApiController
{
    public function metrics(): JsonResponse
    {
        return response()->json([
            'tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'users' => User::count(),
            'contacts' => Contact::count(),
            'service_requests' => ServiceRequest::count(),
            'professionals' => Professional::count(),
            'verified_professionals' => Professional::where('verification_state', 'verified')->count(),
            'trialing_subscriptions' => Subscription::where('status', 'trialing')->count(),
        ]);
    }

    public function tenants(): JsonResponse
    {
        return response()->json(Tenant::latest()->paginate(100));
    }

    public function updateTenant(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate([
            'status' => ['sometimes', 'in:active,suspended'],
            'plan' => ['sometimes', 'in:trial,basic,advanced'],
        ]);
        if (isset($data['plan'])) {
            $plan = Plan::where('code', $data['plan'])->where('active', true)->firstOrFail();
            Subscription::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'plan_id' => $plan->id,
                    'status' => $data['plan'] === 'trial' ? 'trialing' : 'active',
                    'trial_ends_at' => $data['plan'] === 'trial' ? now()->addDays(3) : null,
                    'ends_at' => null,
                ]
            );
        }
        $tenant->update($data);
        $this->audit($request, 'updated', 'tenant', $tenant->id, $data);

        return response()->json($tenant);
    }

    public function plans(): JsonResponse
    {
        return response()->json(Plan::orderBy('price_kz')->get());
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'price_kz' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $plan->update($data);
        $this->audit($request, 'updated', 'plan', $plan->id, $data);

        return response()->json($plan);
    }

    public function categories(): JsonResponse
    {
        return response()->json(ServiceCategory::orderBy('name')->get());
    }

    public function createCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:service_categories,name'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $category = ServiceCategory::create($data);
        $this->audit($request, 'created', 'service_category', $category->id);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = ServiceCategory::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('service_categories', 'name')->ignore($category->id)],
            'active' => ['sometimes', 'boolean'],
        ]);
        $category->update($data);
        $this->audit($request, 'updated', 'service_category', $category->id, $data);

        return response()->json($category);
    }

    public function professionals(): JsonResponse
    {
        return response()->json(Professional::orderBy('name')->paginate(100));
    }

    public function updateProfessional(Request $request, int $id): JsonResponse
    {
        $professional = Professional::findOrFail($id);
        $data = $request->validate([
            'verification_state' => ['sometimes', 'in:pending,verified,rejected'],
            'status' => ['sometimes', 'in:active,inactive'],
            'specialty' => ['sometimes', 'string', 'max:160'],
        ]);
        $professional->update($data);
        $this->audit($request, 'updated', 'professional', $professional->id, $data);

        return response()->json($professional);
    }

    public function auditLogs(): JsonResponse
    {
        return response()->json(
            AuditLog::query()
                ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->leftJoin('tenants', 'tenants.id', '=', 'audit_logs.tenant_id')
                ->select('audit_logs.*', 'users.name as actor_name', 'users.email as actor_email', 'tenants.name as tenant_name')
                ->latest('audit_logs.created_at')
                ->paginate(100)
        );
    }

    public function publicRequests(): JsonResponse
    {
        return response()->json(ServiceRequest::whereNull('tenant_id')->latest()->paginate(100));
    }

    public function assignRequest(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'exists:tenants,id']]);
        $tenant = Tenant::whereKey($data['tenant_id'])->where('status', 'active')->firstOrFail();
        $item = ServiceRequest::whereNull('tenant_id')->findOrFail($id);
        $item->update(['tenant_id' => $tenant->id, 'status' => 'triage']);
        $this->audit($request, 'assigned', 'service_request', $item->id, ['tenant_id' => $tenant->id]);

        return response()->json($item);
    }
}
