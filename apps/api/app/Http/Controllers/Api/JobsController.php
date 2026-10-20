<?php

namespace App\Http\Controllers\Api;

use App\Models\IdempotencyKey;
use App\Models\Membership;
use App\Models\Professional;
use App\Models\ServiceAssignment;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Notifications\ServiceRequestStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JobsController extends BaseApiController
{
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'storefront_id' => ['required', 'integer', 'exists:storefronts,id'],
            'listing_id' => ['nullable', 'integer', 'exists:storefront_listings,id'],
            'category_id' => ['required', Rule::exists('service_categories', 'id')->where('active', true)],
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
        ]);

        $shop = DB::table('storefronts')->where('id', $data['storefront_id'])
            ->where('app_code', 'jobs')->where('status', 'published')->first();
        abort_unless($shop, 422, 'Esta vitrine não está disponível para receber pedidos.');
        if (! empty($data['listing_id'])) {
            abort_unless(DB::table('storefront_listings')->where('id', $data['listing_id'])
                ->where('storefront_id', $shop->id)->where('active', true)->exists(), 422,
                'O serviço não pertence à vitrine selecionada.');
        }

        $key = trim((string) $request->header('Idempotency-Key'));
        abort_unless(strlen($key) >= 8 && strlen($key) <= 100, 422, 'Identificador do pedido inválido.');

        [$item, $created] = DB::transaction(function () use ($key, $data, $shop) {
            $existing = IdempotencyKey::where('scope', 'jobs_request')->where('key', $key)->lockForUpdate()->first();
            if ($existing?->resource_id) {
                return [ServiceRequest::findOrFail($existing->resource_id), false];
            }
            $entry = $existing ?: IdempotencyKey::create([
                'scope' => 'jobs_request', 'key' => $key, 'expires_at' => now()->addDay(),
            ]);
            $item = ServiceRequest::create([
                'tenant_id' => null,
                'category_id' => $data['category_id'],
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'description' => $data['description'],
                'consent' => true,
                'status' => 'submitted',
                'public_reference' => 'JB-'.strtoupper(Str::random(10)),
            ]);
            DB::table('jobs_request_targets')->insert([
                'service_request_id' => $item->id,
                'storefront_id' => $shop->id,
                'storefront_listing_id' => $data['listing_id'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $entry->update(['resource_type' => 'service_request', 'resource_id' => $item->id]);

            return [$item, true];
        });

        if ($created && filled($item->customer_email)) {
            Notification::route('mail', $item->customer_email)->notify(
                new ServiceRequestStatusNotification($item->public_reference, $item->status)
            );
        }

        return response()->json([
            'reference' => $item->public_reference,
            'status' => $item->status,
            'message' => 'Pedido recebido. A equipa Vitaleevo irá validar e encaminhar.',
        ], $created ? 201 : 200);
    }

    public function professionalProfile(Request $request): JsonResponse
    {
        $profile = Professional::where('user_id', $request->user()->id)->first();

        return response()->json(['profile' => $profile]);
    }

    public function applyProfessional(Request $request): JsonResponse
    {
        $data = $request->validate([
            'specialty' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
        ]);
        $profile = Professional::where('user_id', $request->user()->id)->first();
        if (! $profile) {
            $this->enforceBetaCapacity('professionals', Professional::count());
            $profile = Professional::create([
                'user_id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'phone' => $data['phone'],
                'specialty' => $data['specialty'],
                'status' => 'active',
                'verification_state' => 'pending',
            ]);
        } else {
            $changed = $profile->phone !== $data['phone'] || $profile->specialty !== $data['specialty'];
            $profile->update([
                'phone' => $data['phone'], 'specialty' => $data['specialty'],
                'verification_state' => $changed ? 'pending' : $profile->verification_state,
            ]);
        }
        $this->audit($request, 'applied', 'professional', $profile->id);

        return response()->json(['profile' => $profile->fresh(), 'message' => 'Perfil registado para validação.'], $profile->wasRecentlyCreated ? 201 : 200);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        $adminTenants = Membership::where('user_id', $user->id)->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])->pluck('tenant_id');

        $stores = DB::table('storefronts')->where('app_code', 'jobs')->where(function ($q) use ($user, $adminTenants) {
            $q->where(function ($p) use ($user) {
                $p->where('owner_kind', 'personal')->where('user_id', $user->id);
            })->orWhere(function ($b) use ($adminTenants) {
                $b->where('owner_kind', 'business')->whereIn('tenant_id', $adminTenants);
            });
        })->pluck('id');

        $requests = DB::table('jobs_request_targets as jt')
            ->join('service_requests as sr', 'sr.id', '=', 'jt.service_request_id')
            ->join('storefronts as st', 'st.id', '=', 'jt.storefront_id')
            ->whereIn('jt.storefront_id', $stores)
            ->select('sr.public_reference', 'sr.status', 'sr.description', 'sr.created_at',
                'st.name as storefront_name', 'st.slug as storefront_slug')
            ->orderByDesc('sr.created_at')->paginate(30);

        // Contact details are not exposed until the platform assigns the job.
        return response()->json($requests);
    }

    public function operatorRequests(): JsonResponse
    {
        $requests = DB::table('jobs_request_targets as jt')
            ->join('service_requests as sr', 'sr.id', '=', 'jt.service_request_id')
            ->join('storefronts as st', 'st.id', '=', 'jt.storefront_id')
            ->leftJoin('storefront_listings as li', 'li.id', '=', 'jt.storefront_listing_id')
            ->select('sr.id', 'sr.public_reference', 'sr.status', 'sr.customer_name',
                'sr.customer_phone', 'sr.description', 'sr.created_at',
                'st.name as storefront_name', 'st.owner_kind', 'st.user_id as provider_user_id',
                'st.tenant_id as provider_tenant_id', 'li.title as listing_title')
            ->orderByDesc('sr.created_at')->paginate(50);

        return response()->json($requests);
    }

    public function routeRequest(Request $request, int $id): JsonResponse
    {
        $item = DB::transaction(function () use ($id, $request) {
            $item = ServiceRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            $target = DB::table('jobs_request_targets as jt')
                ->join('storefronts as st', 'st.id', '=', 'jt.storefront_id')
                ->where('jt.service_request_id', $id)
                ->select('st.owner_kind', 'st.user_id', 'st.tenant_id')->first();
            abort_unless($target, 404);
            abort_unless(in_array($item->status, ['submitted', 'triage'], true), 409,
                'O pedido já foi encaminhado ou encerrado.');

            if ($target->owner_kind === 'business') {
                $tenant = Tenant::whereKey($target->tenant_id)->where('status', 'active')->first();
                abort_unless($tenant, 422, 'A empresa não está activa.');
                abort_unless($item->tenant_id === null || (int) $item->tenant_id === (int) $tenant->id,
                    409, 'O pedido já pertence a outra empresa.');
                $item->update(['tenant_id' => $tenant->id, 'status' => 'triage']);
            } else {
                $professional = Professional::where('user_id', $target->user_id)
                    ->where('status', 'active')->where('verification_state', 'verified')->first();
                abort_unless($professional, 422,
                    'O profissional precisa de validação antes de receber trabalhos.');
                $assignment = ServiceAssignment::where('request_id', $item->id)
                    ->where('professional_id', $professional->id)->first();
                if ($assignment) {
                    abort_unless($assignment->status === 'rejected', 409, 'O pedido já possui uma atribuição.');
                    $assignment->update(['status' => 'assigned']);
                } else {
                    abort_if(ServiceAssignment::where('request_id', $item->id)->exists(), 409,
                        'O pedido já possui outra atribuição.');
                    ServiceAssignment::create([
                        'request_id' => $item->id, 'professional_id' => $professional->id,
                        'status' => 'assigned',
                    ]);
                }
                $item->update(['status' => 'assigned']);
            }
            $this->audit($request, 'routed', 'jobs_request', $item->id);

            return $item->fresh();
        });

        return response()->json(['reference' => $item->public_reference, 'status' => $item->status,
            'tenant_id' => $item->tenant_id]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $item = DB::transaction(function () use ($id, $request) {
            $item = ServiceRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(DB::table('jobs_request_targets')->where('service_request_id', $id)->exists(), 404);
            abort_unless($item->status === 'delivered', 409,
                'A conclusão exige entrega confirmada pelo profissional.');
            abort_unless(ServiceAssignment::where('request_id', $id)
                ->where('status', 'completed')->exists(), 409, 'Não existe entrega para validar.');
            $item->update(['status' => 'completed']);
            $this->audit($request, 'validated', 'jobs_request', $id);

            return $item;
        });
        if (filled($item->customer_email)) {
            Notification::route('mail', $item->customer_email)->notify(
                new ServiceRequestStatusNotification($item->public_reference, 'completed')
            );
        }

        return response()->json(['reference' => $item->public_reference, 'status' => $item->status]);
    }
}
