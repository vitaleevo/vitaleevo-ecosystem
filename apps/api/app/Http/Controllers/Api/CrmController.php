<?php

namespace App\Http\Controllers\Api;

use App\Models\Contact;
use App\Models\Membership;
use App\Models\Opportunity;
use App\Models\OpportunityHistory;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmController extends BaseApiController
{
    public function dashboard(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);

        return response()->json([
            'contacts' => Contact::where('tenant_id', $tenant)->count(),
            'open_opportunities' => Opportunity::where('tenant_id', $tenant)->where('status', 'open')->count(),
            'pipeline_value_kz' => (int) Opportunity::where('tenant_id', $tenant)->where('status', 'open')->sum('value'),
            'pending_tasks' => Task::where('tenant_id', $tenant)->where('status', 'pending')->count(),
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $query = Contact::where('tenant_id', $this->tenantId($request));
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('company', 'like', $like);
            });
        }

        return response()->json($query->latest()->paginate(50));
    }

    public function createContact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'], 'company' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $tenantId = $this->tenantId($request);
        $duplicate = false;
        if (filled($data['email'] ?? null) || filled($data['phone'] ?? null)) {
            $duplicate = Contact::where('tenant_id', $tenantId)
                ->where(function ($query) use ($data) {
                    if (filled($data['email'] ?? null)) {
                        $query->orWhere('email', mb_strtolower($data['email']));
                    }
                    if (filled($data['phone'] ?? null)) {
                        $query->orWhere('phone', $data['phone']);
                    }
                })->exists();
        }
        abort_if($duplicate, 422, 'Já existe um contacto com este email ou telefone neste workspace.');
        if (filled($data['email'] ?? null)) {
            $data['email'] = mb_strtolower($data['email']);
        }
        $item = Contact::create($data + ['tenant_id' => $tenantId]);
        $this->audit($request, 'created', 'contact', $item->id);

        return response()->json($item, 201);
    }

    public function updateContact(Request $request, int $id): JsonResponse
    {
        $item = Contact::where('tenant_id', $this->tenantId($request))->findOrFail($id);
        $item->update($request->validate([
            'name' => ['sometimes', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'], 'company' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]));
        $this->audit($request, 'updated', 'contact', $item->id);

        return response()->json($item);
    }

    public function pipelines(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $pipelines = Pipeline::where('tenant_id', $tenant)->get();
        foreach ($pipelines as $pipeline) {
            $pipeline->stages = PipelineStage::where('tenant_id', $tenant)->where('pipeline_id', $pipeline->id)->orderBy('position')->get();
        }

        return response()->json($pipelines);
    }

    public function opportunities(Request $request): JsonResponse
    {
        $query = Opportunity::where('tenant_id', $this->tenantId($request));
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }
        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }
        if (filled($request->query('owner_id'))) {
            $query->where('owner_id', (int) $request->query('owner_id'));
        }
        if (filled($request->query('pipeline_stage_id'))) {
            $query->where('pipeline_stage_id', (int) $request->query('pipeline_stage_id'));
        }

        return response()->json($query->latest()->paginate(50));
    }

    public function createOpportunity(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'contact_id' => ['nullable', 'integer'],
            'pipeline_id' => ['required', 'integer'], 'pipeline_stage_id' => ['required', 'integer'],
            'owner_id' => ['nullable', 'integer'], 'value' => ['nullable', 'integer', 'min:0'], 'expected_close_at' => ['nullable', 'date'],
        ]);
        abort_unless(Pipeline::where('tenant_id', $tenant)->whereKey($data['pipeline_id'])->exists(), 404);
        abort_unless(PipelineStage::where('tenant_id', $tenant)->whereKey($data['pipeline_stage_id'])->where('pipeline_id', $data['pipeline_id'])->exists(), 404);
        if (! empty($data['contact_id'])) {
            abort_unless(Contact::where('tenant_id', $tenant)->whereKey($data['contact_id'])->exists(), 404);
        }
        $ownerId = (int) ($data['owner_id'] ?? $request->user()->id);
        abort_unless(Membership::where('tenant_id', $tenant)->where('user_id', $ownerId)->where('status', 'active')->exists(), 422, 'Responsável inválido para este workspace.');
        $data['owner_id'] = $ownerId;
        $item = Opportunity::create($data + ['tenant_id' => $tenant, 'status' => 'open']);
        OpportunityHistory::create([
            'tenant_id' => $tenant, 'opportunity_id' => $item->id, 'user_id' => $request->user()->id,
            'event' => 'created', 'to_stage_id' => $item->pipeline_stage_id, 'to_status' => $item->status,
        ]);
        $this->audit($request, 'created', 'opportunity', $item->id);

        return response()->json($item, 201);
    }

    public function updateOpportunity(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $item = Opportunity::where('tenant_id', $tenant)->findOrFail($id);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'], 'pipeline_stage_id' => ['sometimes', 'integer'],
            'owner_id' => ['sometimes', 'integer'], 'value' => ['sometimes', 'integer', 'min:0'], 'status' => ['sometimes', 'in:open,won,lost'],
            'expected_close_at' => ['nullable', 'date'],
        ]);
        if (isset($data['pipeline_stage_id'])) {
            abort_unless(PipelineStage::where('tenant_id', $tenant)->where('pipeline_id', $item->pipeline_id)->whereKey($data['pipeline_stage_id'])->exists(), 404);
        }
        if (isset($data['owner_id'])) {
            abort_unless(Membership::where('tenant_id', $tenant)->where('user_id', $data['owner_id'])->where('status', 'active')->exists(), 422, 'Responsável inválido para este workspace.');
        }
        $fromStage = $item->pipeline_stage_id;
        $fromStatus = $item->status;
        $item->update($data);
        $item->refresh();
        OpportunityHistory::create([
            'tenant_id' => $tenant, 'opportunity_id' => $item->id, 'user_id' => $request->user()->id,
            'event' => 'updated', 'from_stage_id' => $fromStage, 'to_stage_id' => $item->pipeline_stage_id,
            'from_status' => $fromStatus, 'to_status' => $item->status,
            'metadata' => ['changed' => array_keys($data)],
        ]);
        $this->audit($request, 'updated', 'opportunity', $item->id);

        return response()->json($item);
    }

    public function opportunityHistory(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        abort_unless(Opportunity::where('tenant_id', $tenant)->whereKey($id)->exists(), 404);

        return response()->json(
            OpportunityHistory::where('tenant_id', $tenant)
                ->where('opportunity_id', $id)
                ->latest('created_at')
                ->get()
        );
    }

    public function tasks(Request $request): JsonResponse
    {
        $query = Task::where('tenant_id', $this->tenantId($request));
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }
        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }
        if (filled($request->query('assigned_to'))) {
            $query->where('assigned_to', (int) $request->query('assigned_to'));
        }

        return response()->json($query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('due_at')->paginate(50));
    }

    public function createTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:5000'],
            'opportunity_id' => ['nullable', 'integer'], 'assigned_to' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'], 'priority' => ['nullable', 'in:low,normal,high'],
        ]);
        $tenant = $this->tenantId($request);
        if (! empty($data['opportunity_id'])) {
            abort_unless(Opportunity::where('tenant_id', $tenant)->whereKey($data['opportunity_id'])->exists(), 404);
        }
        $assignee = (int) ($data['assigned_to'] ?? $request->user()->id);
        abort_unless(Membership::where('tenant_id', $tenant)->where('user_id', $assignee)->where('status', 'active')->exists(), 422, 'Responsável inválido para este workspace.');
        $data['assigned_to'] = $assignee;
        $item = Task::create($data + ['tenant_id' => $tenant, 'status' => 'pending']);
        $this->audit($request, 'created', 'task', $item->id);

        return response()->json($item, 201);
    }

    public function updateTask(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $item = Task::where('tenant_id', $tenant)->findOrFail($id);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['sometimes', 'integer'], 'due_at' => ['nullable', 'date'],
            'priority' => ['sometimes', 'in:low,normal,high'], 'status' => ['sometimes', 'in:pending,done'],
        ]);
        if (isset($data['assigned_to'])) {
            abort_unless(Membership::where('tenant_id',$tenant)->where('user_id',$data['assigned_to'])->where('status','active')->exists(),422,'Responsável inválido para este workspace.');
        }
        $item->update($data);
        $this->audit($request,'updated','task',$item->id);

        return response()->json($item);
    }
}
