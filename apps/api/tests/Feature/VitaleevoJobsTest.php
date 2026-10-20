<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureOwnerMfa;
use App\Models\Membership;
use App\Models\Professional;
use App\Models\ServiceAssignment;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VitaleevoJobsTest extends TestCase
{
    use RefreshDatabase;

    private function storefront(User $user, string $status = 'published'): int
    {
        return DB::table('storefronts')->insertGetId([
            'user_id' => $user->id,
            'tenant_id' => null,
            'owner_kind' => 'personal',
            'app_code' => 'jobs',
            'slug' => 'jobs-'.$user->id.'-'.uniqid(),
            'name' => 'Técnico de Instalações',
            'province' => 'Luanda',
            'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function payload(int $shopId, int $categoryId): array
    {
        return [
            'storefront_id' => $shopId, 'category_id' => $categoryId,
            'customer_name' => 'Cliente Teste', 'customer_phone' => '923111222',
            'description' => 'Preciso de instalação de câmaras em Luanda.',
            'consent' => true,
        ];
    }

    public function test_public_can_submit_to_published_shop_only_once(): void
    {
        $seller = User::factory()->create();
        $shopId = $this->storefront($seller);
        $cat = ServiceCategory::create(['name' => 'CCTV', 'active' => true]);
        $request = $this->payload($shopId, $cat->id);
        $key = 'test-jobs-idempotent-0001';

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/jobs/requests', $request)
            ->assertCreated()->assertJsonPath('status', 'submitted');
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/jobs/requests', $request)
            ->assertOk()->assertJsonPath('reference', $first->json('reference'));
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertDatabaseHas('jobs_request_targets', ['storefront_id' => $shopId]);

        $this->postJson('/api/v1/public/service-requests/status', [
            'reference' => $first->json('reference'), 'customer_phone' => '923111222',
        ])->assertOk()->assertJsonPath('status', 'submitted');
        $this->postJson('/api/v1/public/service-requests/status', [
            'reference' => $first->json('reference'), 'customer_phone' => '923000000',
        ])->assertNotFound();
    }

    public function test_unpublished_storefront_and_unrelated_listing_are_rejected(): void
    {
        $seller = User::factory()->create();
        $shopId = $this->storefront($seller, 'draft');
        $cat = ServiceCategory::create(['name' => 'Electricidade', 'active' => true]);
        $this->withHeader('Idempotency-Key', 'jobs-test-invalid-0001')
            ->postJson('/api/v1/jobs/requests', $this->payload($shopId, $cat->id))
            ->assertUnprocessable();
        DB::table('storefronts')->where('id', $shopId)->update(['status' => 'published']);
        $other = $this->storefront(User::factory()->create());
        $listing = DB::table('storefront_listings')->insertGetId([
            'storefront_id' => $other, 'title' => 'Outro serviço', 'price_mode' => 'quote', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withHeader('Idempotency-Key', 'jobs-test-invalid-0002')
            ->postJson('/api/v1/jobs/requests', $this->payload($shopId, $cat->id) + ['listing_id' => $listing])
            ->assertUnprocessable();
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_professional_must_be_verified_then_can_deliver_for_owner_validation(): void
    {
        $this->withoutMiddleware(EnsureOwnerMfa::class);
        $professionalUser = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $shopId = $this->storefront($professionalUser);
        $category = ServiceCategory::create(['name' => 'Segurança Electrónica', 'active' => true]);
        $owner = User::factory()->create([
            'is_platform_owner' => true, 'accepted_terms_at' => now(), 'email_verified_at' => now(),
        ]);
        $profile = Professional::create([
            'user_id' => $professionalUser->id, 'name' => $professionalUser->name,
            'specialty' => 'CCTV', 'status' => 'active', 'verification_state' => 'pending',
        ]);
        $result = $this->withHeader('Idempotency-Key', 'jobs-complete-123456')
            ->postJson('/api/v1/jobs/requests', $this->payload($shopId, $category->id))->assertCreated();
        $id = ServiceRequest::where('public_reference', $result->json('reference'))->value('id');
        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/platform/jobs/requests/'.$id.'/route')
            ->assertUnprocessable();

        $profile->update(['verification_state' => 'verified']);
        $this->postJson('/api/v1/platform/jobs/requests/'.$id.'/route')
            ->assertOk()->assertJsonPath('status', 'assigned');
        $assignment = ServiceAssignment::where('request_id', $id)->firstOrFail();
        $this->actingAs($professionalUser, 'sanctum');
        $this->patchJson('/api/v1/professional/jobs/'.$assignment->id, ['status' => 'completed'])
            ->assertStatus(409);
        $this->patchJson('/api/v1/professional/jobs/'.$assignment->id, ['status' => 'accepted'])
            ->assertOk();
        $this->patchJson('/api/v1/professional/jobs/'.$assignment->id, ['status' => 'in_progress'])
            ->assertOk();
        $this->patchJson('/api/v1/professional/jobs/'.$assignment->id, ['status' => 'completed'])
            ->assertOk();
        $this->assertDatabaseHas('service_requests', ['id' => $id, 'status' => 'delivered']);
        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/platform/jobs/requests/'.$id.'/complete')
            ->assertOk()->assertJsonPath('status', 'completed');
        $this->postJson('/api/v1/public/service-requests/feedback', [
            'reference' => $result->json('reference'),
            'customer_phone' => '923111222', 'rating' => 5, 'comment' => 'Boa execução',
        ])->assertOk();
        $this->assertDatabaseHas('service_feedbacks', ['service_request_id' => $id, 'rating' => 5]);
    }

    public function test_other_user_cannot_control_job_or_view_another_provider_inbox(): void
    {
        $professionalUser = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $shopId = $this->storefront($professionalUser);
        $category = ServiceCategory::create(['name' => 'Informática', 'active' => true]);
        $this->withHeader('Idempotency-Key', 'jobs-private-123456')
            ->postJson('/api/v1/jobs/requests', $this->payload($shopId, $category->id))->assertCreated();

        $outsider = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/jobs/my-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/platform/jobs/requests')->assertForbidden();
        $this->assertDatabaseHas('service_requests', ['status' => 'submitted']);
    }

    public function test_search_finds_published_professional_on_sqlite(): void
    {
        $owner = User::factory()->create();
        $this->storefront($owner);
        $this->getJson('/api/v1/ecosystem/storefronts?app=jobs&q=T%C3%A9cnico')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/ecosystem/storefronts?app=market&q=T%C3%A9cnico')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_business_job_is_routed_to_its_tenant_only(): void
    {
        $this->withoutMiddleware(EnsureOwnerMfa::class);
        $operator = User::factory()->create([
            'is_platform_owner' => true, 'accepted_terms_at' => now(), 'email_verified_at' => now(),
        ]);
        $manager = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $other = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Prestadora A', 'slug' => 'prestadora-a', 'status' => 'active']);
        $otherTenant = Tenant::create(['name' => 'Prestadora B', 'slug' => 'prestadora-b', 'status' => 'active']);
        Membership::create(['user_id' => $manager->id, 'tenant_id' => $tenant->id, 'role' => 'owner']);
        Membership::create(['user_id' => $other->id, 'tenant_id' => $otherTenant->id, 'role' => 'owner']);
        $manager->update(['current_tenant_id' => $tenant->id]);
        $other->update(['current_tenant_id' => $otherTenant->id]);
        $shop = DB::table('storefronts')->insertGetId([
            'user_id' => $manager->id, 'tenant_id' => $tenant->id, 'owner_kind' => 'business',
            'app_code' => 'jobs', 'slug' => 'prestadora-empresarial', 'name' => 'Prestadora A',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $category = ServiceCategory::create(['name' => 'Construção', 'active' => true]);
        $this->withHeader('Idempotency-Key', 'business-job-123456')
            ->postJson('/api/v1/jobs/requests', $this->payload($shop, $category->id))->assertCreated();
        $id = ServiceRequest::latest('id')->value('id');

        $this->actingAs($other, 'sanctum')->getJson('/api/v1/service-requests')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/platform/jobs/requests/'.$id.'/route')
            ->assertOk()->assertJsonPath('tenant_id', $tenant->id);
        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/service-requests')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson('/api/v1/service-requests/'.$id, ['status' => 'completed'])
            ->assertStatus(409);
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/service-requests')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_authenticated_person_can_apply_to_be_professional(): void
    {
        $person = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $this->actingAs($person, 'sanctum')
            ->postJson('/api/v1/jobs/professional/apply',[
                'specialty' => 'Fotografia', 'phone' => '923987654',
            ])->assertCreated()->assertJsonPath('profile.verification_state','pending');
        $this->assertDatabaseHas('professionals',['user_id' => $person->id, 'specialty' => 'Fotografia']);
    }
}
