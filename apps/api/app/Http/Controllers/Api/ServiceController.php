<?php

namespace App\Http\Controllers\Api;

use App\Models\IdempotencyKey;
use App\Models\Professional;
use App\Models\ServiceAssignment;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Notifications\ServiceRequestStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ServiceController extends BaseApiController
{
    public function categories(): JsonResponse
    {
        return response()->json(ServiceCategory::where('active',true)->orderBy('name')->get());
    }

    public function publicStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id'=>['required','exists:service_categories,id'], 'customer_name'=>['required','string','max:160'],
            'customer_email'=>['nullable','email','max:190'], 'customer_phone'=>['required','string','max:40'],
            'description'=>['required','string','min:10','max:5000'], 'consent'=>['accepted'],
        ]);

        $key = trim((string) $request->header('Idempotency-Key'));
        abort_if($key === '' || strlen($key) > 100, 422, 'Idempotency-Key é obrigatório.');

        $result = DB::transaction(function () use ($key, $data) {
            $existing = IdempotencyKey::where('scope', 'public_service_request')->where('key', $key)->lockForUpdate()->first();
            if ($existing?->resource_id) {
                return [ServiceRequest::findOrFail($existing->resource_id), false];
            }

            $record = $existing ?: IdempotencyKey::create([
                'scope' => 'public_service_request',
                'key' => $key,
                'expires_at' => now()->addDay(),
            ]);

            $item = ServiceRequest::create($data + [
                'public_reference'=>'SR-'.strtoupper(Str::random(10)),
                'status'=>'submitted',
            ]);
            $record->update(['resource_type'=>'service_request','resource_id'=>$item->id]);
            return [$item, true];
        });

        [$item, $created] = $result;
        if ($created) {
            $this->notifyCustomer($item);
        }
        return response()->json(['reference'=>$item->public_reference,'status'=>$item->status], $created ? 201 : 200);
    }

    public function publicStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required','string','max:40'],
            'customer_phone' => ['required','string','max:40'],
        ]);

        $item = ServiceRequest::where('public_reference', strtoupper($data['reference']))
            ->where('customer_phone', $data['customer_phone'])
            ->firstOrFail();

        return response()->json([
            'reference' => $item->public_reference,
            'status' => $item->status,
            'updated_at' => $item->updated_at,
        ]);
    }

    public function requests(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        return response()->json(
            ServiceRequest::where('tenant_id', $tenant)->latest()->paginate(50)
        );
    }

    public function updateRequest(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $item = ServiceRequest::where('tenant_id', $tenant)->findOrFail($id);
        $data = $request->validate([
            'status'=>['sometimes','in:submitted,triage,assigned,in_progress,completed,cancelled'],
        ]);
        $item->update($data);
        $this->notifyCustomer($item->fresh());
        $this->audit($request,'updated','service_request',$item->id);
        return response()->json($item);
    }

    public function professionals(): JsonResponse
    {
        return response()->json(Professional::orderBy('name')->paginate(100));
    }

    public function createProfessional(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_platform_owner, 403, 'Apenas o Platform Owner pode criar profissionais globais.');
        $data = $request->validate([
            'name'=>['required','string','max:160'], 'email'=>['nullable','email','max:190'], 'phone'=>['nullable','string','max:40'],
            'specialty'=>['required','string','max:160'], 'verification_state'=>['nullable','in:pending,verified,rejected'],
        ]);
        $userId = filled($data['email'] ?? null)
            ? \App\Models\User::where('email', mb_strtolower($data['email']))->value('id')
            : null;
        $item = Professional::create($data + ['user_id'=>$userId]);
        $this->audit($request,'created','professional',$item->id);
        return response()->json($item,201);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        abort_if($request->attributes->get('membership_role') === 'agent',403,'Sem permissão.');
        $tenant = $this->tenantId($request);
        $serviceRequest = ServiceRequest::where('tenant_id', $tenant)->findOrFail($id);
        $data = $request->validate(['professional_id'=>['required','exists:professionals,id'],'notes'=>['nullable','string','max:2000']]);
        $assignment = ServiceAssignment::updateOrCreate(
            ['request_id'=>$serviceRequest->id,'professional_id'=>$data['professional_id']],
            ['status'=>'assigned','notes'=>$data['notes'] ?? null]
        );
        $serviceRequest->update(['status'=>'assigned']);
        $this->notifyCustomer($serviceRequest->fresh());
        $this->audit($request,'assigned','service_request',$serviceRequest->id,['professional_id'=>$data['professional_id']]);
        return response()->json($assignment,201);
    }

    public function professionalJobs(Request $request): JsonResponse
    {
        $professional = Professional::where('user_id', $request->user()->id)->firstOrFail();
        $jobs = ServiceAssignment::query()
            ->where('professional_id', $professional->id)
            ->join('service_requests', 'service_requests.id', '=', 'service_assignments.request_id')
            ->select(
                'service_assignments.id', 'service_assignments.status', 'service_assignments.notes',
                'service_requests.public_reference', 'service_requests.customer_name', 'service_requests.customer_phone',
                'service_requests.description', 'service_requests.status as request_status', 'service_requests.updated_at'
            )
            ->latest('service_assignments.created_at')
            ->paginate(50);
        return response()->json($jobs);
    }

    public function updateProfessionalJob(Request $request, int $id): JsonResponse
    {
        $professional = Professional::where('user_id', $request->user()->id)->firstOrFail();
        $assignment = ServiceAssignment::where('professional_id', $professional->id)->findOrFail($id);
        $data = $request->validate(['status'=>['required','in:accepted,rejected,in_progress,completed']]);
        $assignment->update($data);

        $serviceRequest = ServiceRequest::findOrFail($assignment->request_id);
        if ($data['status'] === 'in_progress') $serviceRequest->update(['status'=>'in_progress']);
        if ($data['status'] === 'completed') $serviceRequest->update(['status'=>'completed']);
        if ($data['status'] === 'accepted') $serviceRequest->update(['status'=>'assigned']);
        if ($data['status'] !== 'rejected') {
            $this->notifyCustomer($serviceRequest->fresh());
        }

        return response()->json($assignment);
    }

    private function notifyCustomer(ServiceRequest $item): void
    {
        if (!filled($item->customer_email)) return;

        if ($item->tenant_id) {
            $tenant = Tenant::find($item->tenant_id);
            if (($tenant?->settings['email_notifications'] ?? true) === false) return;
        }

        Notification::route('mail', $item->customer_email)->notify(
            new ServiceRequestStatusNotification($item->public_reference, $item->status)
        );
    }
}
