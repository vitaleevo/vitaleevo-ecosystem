<?php

namespace App\Http\Controllers\Api;

use App\Models\Membership;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $items = Membership::where('user_id', $request->user()->id)
            ->join('tenants','tenants.id','=','memberships.tenant_id')
            ->select('tenants.*','memberships.role','memberships.status as membership_status')
            ->orderBy('tenants.name')->get();
        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required','string','max:160']]);
        $tenant = DB::transaction(function () use ($request, $data) {
            $tenant = Tenant::create(['name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);
            Membership::create(['tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'role' => 'owner']);
            $pipeline = Pipeline::create(['tenant_id'=>$tenant->id,'name'=>'Funil Comercial','is_default'=>true]);
            foreach (['Novo','Qualificado','Proposta','Negociação','Ganho'] as $position => $name) {
                PipelineStage::create(['tenant_id'=>$tenant->id,'pipeline_id'=>$pipeline->id,'name'=>$name,'position'=>$position]);
            }
            return $tenant;
        });
        return response()->json($tenant, 201);
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json(Tenant::findOrFail($this->tenantId($request)));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_if($request->attributes->get('membership_role') === 'agent', 403, 'Sem permissão para alterar as configurações da empresa.');
        $tenant = Tenant::findOrFail($this->tenantId($request));
        $data = $request->validate([
            'name' => ['sometimes','string','max:160'],
            'contact_email' => ['nullable','email','max:190'],
            'phone' => ['nullable','string','max:40'],
            'email_notifications' => ['sometimes','boolean'],
        ]);
        $settings = array_merge($tenant->settings ?? [], array_filter([
            'contact_email' => $data['contact_email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email_notifications' => $data['email_notifications'] ?? ($tenant->settings['email_notifications'] ?? true),
        ], fn ($value) => $value !== null));
        $tenant->update([
            'name' => $data['name'] ?? $tenant->name,
            'settings' => $settings,
        ]);
        $this->audit($request, 'updated', 'tenant_settings', $tenant->id);
        return response()->json($tenant->fresh());
    }

    public function switch(Request $request, Tenant $tenant): JsonResponse
    {
        $allowed = Membership::where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status','active')->exists();
        abort_unless($allowed && $tenant->status === 'active', 403, 'Sem acesso a esta empresa.');
        $request->user()->update(['current_tenant_id' => $tenant->id]);
        return response()->json(['current_tenant_id' => $tenant->id, 'tenant' => $tenant]);
    }
}
