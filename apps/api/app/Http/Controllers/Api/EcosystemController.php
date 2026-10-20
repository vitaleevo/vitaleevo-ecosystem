<?php

namespace App\Http\Controllers\Api;

use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EcosystemController extends BaseApiController
{
    private const MODULES = [
        ['code' => 'business', 'name' => 'Vitaleevo Business', 'summary' => 'Empresas, empreendedorismo e crescimento', 'phase' => 'planned'],
        ['code' => 'jobs', 'name' => 'Vitaleevo Jobs', 'summary' => 'Profissionais, empresas prestadoras e serviços', 'phase' => 'foundation'],
        ['code' => 'academy', 'name' => 'Vitaleevo Academy', 'summary' => 'Formação, certificação e oportunidades', 'phase' => 'planned'],
        ['code' => 'market', 'name' => 'Vitaleevo Market', 'summary' => 'Comércio, fornecedores e revendedores', 'phase' => 'foundation'],
        ['code' => 'vitaerp', 'name' => 'VITAERP', 'summary' => 'Gestão empresarial e facturação', 'phase' => 'planned'],
        ['code' => 'holycrm', 'name' => 'HolyCRM', 'summary' => 'Vendas, clientes e automação', 'phase' => 'existing'],
    ];

    public function modules(): JsonResponse
    {
        return response()->json(['data' => self::MODULES]);
    }

    public function publicStorefronts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'app' => ['nullable', Rule::in(['jobs', 'market'])],
            'province' => ['nullable', 'string', 'max:100'],
        ]);
        $query = DB::table('storefronts')->where('status', 'published');
        if (! empty($data['app'])) {
            $query->where('app_code', $data['app']);
        }
        if (! empty($data['province'])) {
            $query->where('province', $data['province']);
        }
        if (! empty($data['q'])) {
            $term = addcslashes($data['q'], '%_\\');
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn ($x) => $x->where('name', $operator, '%'.$term.'%')
                ->orWhere('description', $operator, '%'.$term.'%'));
        }

        return response()->json($query->select('id', 'slug', 'name', 'description', 'app_code', 'province', 'municipality', 'created_at')
            ->orderByDesc('created_at')->paginate(12));
    }

    public function publicStorefront(string $slug): JsonResponse
    {
        $shop = DB::table('storefronts')->where('slug', $slug)->where('status', 'published')
            ->select('id', 'slug', 'name', 'description', 'app_code', 'province', 'municipality')->first();
        abort_unless($shop, 404);
        $items = DB::table('storefront_listings')->where('storefront_id', $shop->id)->where('active', true)
            ->select('id', 'title', 'description', 'price_kz', 'price_mode')->orderByDesc('id')->get();

        return response()->json(['storefront' => $shop, 'listings' => $items]);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantIds = Membership::where('user_id', $user->id)->where('status', 'active')->pluck('tenant_id');
        $shops = DB::table('storefronts')
            ->where(fn ($q) => $q->where(fn ($s) => $s->where('owner_kind', 'personal')->where('user_id', $user->id))
                ->orWhere(fn ($s) => $s->where('owner_kind', 'business')->whereIn('tenant_id', $tenantIds)))
            ->orderByDesc('id')->get();

        return response()->json(['data' => $shops]);
    }

    public function createStorefront(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_kind' => ['required', Rule::in(['personal', 'business'])],
            'app_code' => ['required', Rule::in(['jobs', 'market'])],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1800'],
            'province' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'tenant_id' => ['nullable', 'integer'],
        ]);
        $tenant = null;
        if ($data['owner_kind'] === 'business') {
            $tenant = (int) ($data['tenant_id'] ?? $request->user()->current_tenant_id);
            abort_unless($tenant > 0, 422, 'Escolha uma empresa.');
            abort_unless(Membership::where('tenant_id', $tenant)->where('user_id', $request->user()->id)
                ->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists(), 403);
        }
        $slug = Str::slug($data['name']).'-'.Str::lower(Str::random(8));
        $id = DB::table('storefronts')->insertGetId([
            'user_id' => $request->user()->id, 'tenant_id' => $tenant,
            'owner_kind' => $data['owner_kind'], 'app_code' => $data['app_code'],
            'slug' => $slug, 'name' => $data['name'], 'description' => $data['description'] ?? null,
            'province' => $data['province'] ?? null, 'municipality' => $data['municipality'] ?? null,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(DB::table('storefronts')->where('id', $id)->first(), 201);
    }

    public function updateStorefront(Request $request, int $id): JsonResponse
    {
        $shop = $this->authorizedShop($request, $id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1800'],
            'province' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
        ]);
        abort_if(! $data, 422, 'Nenhuma alteração.');
        $data['updated_at'] = now();
        DB::table('storefronts')->where('id', $shop->id)->update($data);

        return response()->json(DB::table('storefronts')->where('id', $id)->first());
    }

    public function addListing(Request $request, int $id): JsonResponse
    {
        $shop = $this->authorizedShop($request, $id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1800'],
            'price_kz' => ['nullable', 'integer', 'min:0'],
            'price_mode' => ['required', Rule::in(['quote', 'fixed'])],
        ]);
        abort_if($data['price_mode'] === 'fixed' && ! isset($data['price_kz']), 422, 'Preço obrigatório.');
        $itemId = DB::table('storefront_listings')->insertGetId([
            'storefront_id' => $shop->id, 'title' => $data['title'],
            'description' => $data['description'] ?? null, 'price_kz' => $data['price_kz'] ?? null,
            'price_mode' => $data['price_mode'], 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(DB::table('storefront_listings')->where('id', $itemId)->first(), 201);
    }

    private function authorizedShop(Request $request, int $id): object
    {
        $shop = DB::table('storefronts')->where('id', $id)->first();
        abort_unless($shop, 404);
        if ($shop->owner_kind === 'personal') {
            abort_unless((int) $shop->user_id === (int) $request->user()->id, 404);
        } else {
            abort_unless(Membership::where('tenant_id', $shop->tenant_id)
                ->where('user_id',$request->user()->id)->where('status','active')
                ->whereIn('role',['owner', 'admin'])->exists(), 404);
        }

        return $shop;
    }
}
