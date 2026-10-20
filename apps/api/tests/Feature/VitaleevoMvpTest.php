<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureOwnerMfa;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Plan;
use App\Models\Professional;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitaleevoMvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_tenant_membership_and_active_context(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->postJson('/api/v1/auth/register', [
            'name' => 'Alexandre', 'email' => 'alex@example.test', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'company_name' => 'Vitaleevo Teste', 'terms_accepted' => true,
        ]);
        $response->assertCreated()->assertJsonPath('user.email', 'alex@example.test');
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseHas('memberships', ['role' => 'owner']);
        $this->assertDatabaseHas('subscriptions', ['status' => 'trialing']);
        $this->assertNotNull(User::first()->current_tenant_id);
        $this->assertNotNull(User::first()->accepted_terms_at);
    }

    public function test_tenant_scope_blocks_cross_tenant_contact_access(): void
    {
        $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $user = User::factory()->create(['current_tenant_id' => $a->id]);
        Membership::create(['tenant_id' => $a->id, 'user_id' => $user->id, 'role' => 'owner']);
        Contact::create(['tenant_id' => $b->id, 'name' => 'Contacto B']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/contacts/1', ['name' => 'Intrusão'])
            ->assertNotFound();
        $this->assertDatabaseHas('contacts', ['tenant_id' => $b->id, 'name' => 'Contacto B']);
    }

    public function test_user_cannot_switch_to_tenant_without_membership(): void
    {
        $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $user = User::factory()->create(['current_tenant_id' => $a->id]);
        Membership::create(['tenant_id' => $a->id, 'user_id' => $user->id, 'role' => 'owner']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/tenants/'.$b->id.'/switch')->assertForbidden();
    }

    public function test_public_service_request_is_created_with_reference(): void
    {
        $this->seed();
        $category = ServiceCategory::first();
        $payload = [
            'category_id' => $category->id, 'customer_name' => 'Cliente', 'customer_phone' => '923000000',
            'description' => 'Preciso de assistência técnica urgente.', 'consent' => true,
        ];
        $first = $this->withHeader('Idempotency-Key', 'public-test-001')
            ->postJson('/api/v1/public/service-requests', $payload)
            ->assertCreated()->assertJsonStructure(['reference', 'status']);
        $second = $this->withHeader('Idempotency-Key', 'public-test-001')
            ->postJson('/api/v1/public/service-requests', $payload)
            ->assertOk();
        $this->assertSame($first->json('reference'), $second->json('reference'));
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_public_request_is_hidden_from_tenant_until_platform_owner_assigns_it(): void
    {
        $this->withoutMiddleware(EnsureOwnerMfa::class);
        $this->seed();
        $tenant = Tenant::create(['name' => 'Operação A', 'slug' => 'operacao-a']);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_platform_owner' => true]);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);
        $request = ServiceRequest::create([
            'category_id' => ServiceCategory::first()->id,
            'customer_name' => 'Cliente Público', 'customer_phone' => '924000000',
            'description' => 'Pedido público ainda sem empresa atribuída.', 'status' => 'submitted',
            'public_reference' => 'SR-TESTPUBLIC', 'consent' => true,
        ]);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/service-requests')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/platform/service-requests')
            ->assertOk()->assertJsonPath('data.0.id', $request->id);

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/platform/service-requests/'.$request->id.'/assign', ['tenant_id' => $tenant->id])
            ->assertOk()->assertJsonPath('tenant_id', $tenant->id);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/service-requests')
            ->assertOk()->assertJsonPath('data.0.id', $request->id);
    }

    public function test_invitation_creates_member_only_after_valid_token_is_accepted(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tenant = Tenant::create(['name' => 'Empresa Convite', 'slug' => 'empresa-convite']);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

        $invite = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/memberships', [
            'email' => 'novo@example.test', 'role' => 'agent',
        ])->assertCreated()->json('invitation');

        $this->assertDatabaseMissing('users', ['email' => 'novo@example.test']);

        $this->withHeaders(['Origin' => 'http://localhost:3001', 'Referer' => 'http://localhost:3001/'])
            ->postJson('/api/v1/auth/invitations/accept', [
                'token' => $invite['token'], 'name' => 'Novo Utilizador', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms_accepted' => true,
            ])->assertOk();

        $newUser = User::where('email', 'novo@example.test')->firstOrFail();
        $this->assertDatabaseHas('memberships', ['tenant_id' => $tenant->id, 'user_id' => $newUser->id, 'role' => 'agent', 'status' => 'active']);
    }

    public function test_completed_service_accepts_customer_feedback(): void
    {
        $this->seed();
        $service = ServiceRequest::create([
            'category_id' => ServiceCategory::first()->id,
            'customer_name' => 'Cliente Final', 'customer_phone' => '925000000',
            'description' => 'Serviço concluído para avaliação.', 'status' => 'completed',
            'public_reference' => 'SR-FEEDBACK01', 'consent' => true,
        ]);

        $this->postJson('/api/v1/public/service-requests/feedback', [
            'reference' => $service->public_reference,
            'customer_phone' => $service->customer_phone,
            'rating' => 5,
            'comment' => 'Serviço muito bom.',
        ])->assertOk()->assertJsonPath('rating', 5);

        $this->assertDatabaseHas('service_feedbacks', [
            'service_request_id' => $service->id,
            'rating' => 5,
        ]);
    }

    public function test_registration_can_select_basic_plan(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->postJson('/api/v1/auth/register', [
            'name' => 'Gestor Plano', 'email' => 'plano@example.test', 'password' => 'Password123',
            'password_confirmation' => 'Password123', 'company_name' => 'Empresa Básica', 'plan_code' => 'basic', 'terms_accepted' => true,
        ])->assertCreated();

        $tenant = Tenant::firstOrFail();
        $this->assertSame('basic', $tenant->plan);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
    }

    public function test_opportunity_owner_must_be_active_workspace_member(): void
    {
        $tenant = Tenant::create(['name' => 'CRM A', 'slug' => 'crm-a']);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $assignee = User::factory()->create();
        $outsider = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $assignee->id, 'role' => 'agent']);

        $pipeline = Pipeline::create(['tenant_id' => $tenant->id, 'name' => 'Funil', 'is_default' => true]);
        $stage = PipelineStage::create(['tenant_id' => $tenant->id, 'pipeline_id' => $pipeline->id, 'name' => 'Novo', 'position' => 0]);

        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/opportunities', [
            'title' => 'Nova venda', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'owner_id' => $assignee->id, 'value' => 100000,
        ])->assertCreated()->assertJsonPath('owner_id', $assignee->id);

        $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/opportunities/'.$created->json('id'), [
            'owner_id' => $outsider->id,
        ])->assertStatus(422);
    }

    public function test_only_verified_active_professional_can_be_assigned(): void
    {
        $this->seed();
        $tenant = Tenant::create(['name' => 'Operação', 'slug' => 'operacao']);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);
        $service = ServiceRequest::create([
            'tenant_id' => $tenant->id,
            'category_id' => ServiceCategory::first()->id,
            'customer_name' => 'Cliente', 'customer_phone' => '926000000',
            'description' => 'Pedido destinado a profissional verificado.', 'status' => 'triage',
            'public_reference' => 'SR-VERIFY001', 'consent' => true,
        ]);
        $professional = Professional::create([
            'name' => 'Técnico', 'specialty' => 'TI', 'status' => 'active', 'verification_state' => 'pending',
        ]);

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/service-requests/'.$service->id.'/assignments', [
            'professional_id' => $professional->id,
        ])->assertStatus(422);

        $professional->update(['verification_state' => 'verified']);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/service-requests/'.$service->id.'/assignments', [
            'professional_id' => $professional->id,
        ])->assertCreated();
        $this->assertDatabaseHas('service_requests', ['id' => $service->id, 'status' => 'assigned']);
    }

    public function test_only_platform_owner_can_manage_global_catalog(): void
    {
        $this->withoutMiddleware(EnsureOwnerMfa::class);
        $tenant = Tenant::create(['name' => 'Admin', 'slug' => 'admin']);
        $platformOwner = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_platform_owner' => true]);
        $normalAdmin = User::factory()->create(['current_tenant_id' => $tenant->id]);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $platformOwner->id, 'role' => 'owner']);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $normalAdmin->id, 'role' => 'admin']);

        $this->actingAs($platformOwner, 'sanctum')->postJson('/api/v1/platform/service-categories', [
            'name' => 'Robótica Industrial',
        ])->assertCreated();

        $this->actingAs($normalAdmin, 'sanctum')->postJson('/api/v1/platform/service-categories', [
            'name' => 'Categoria Indevida',
        ])->assertForbidden();
        $this->assertDatabaseMissing('service_categories', ['name' => 'Categoria Indevida']);
    }

    public function test_platform_plan_change_updates_subscription(): void
    {
        $this->withoutMiddleware(EnsureOwnerMfa::class);
        $this->seed();
        $trial = Plan::where('code', 'trial')->firstOrFail();
        $basic = Plan::where('code', 'basic')->firstOrFail();
        $tenant = Tenant::create(['name' => 'Plano Teste', 'slug' => 'plano-teste', 'plan' => 'trial']);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_platform_owner' => true]);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $trial->id, 'status' => 'trialing', 'trial_ends_at' => now()->addDays(3),
        ]);

        $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/platform/tenants/'.$tenant->id, [
            'plan' => 'basic',
        ])->assertOk()->assertJsonPath('plan', 'basic');

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id, 'plan_id' => $basic->id, 'status' => 'active',
        ]);
    }

    public function test_existing_user_must_accept_current_terms_before_operational_access(): void
    {
        $tenant = Tenant::create(['name' => 'Legado', 'slug' => 'legado']);
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
            'accepted_terms_at' => null,
        ]);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'owner']);

        $client = $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->actingAs($user, 'sanctum');

        $client->getJson('/api/v1/dashboard')
            ->assertStatus(428)
            ->assertJsonPath('code', 'terms_acceptance_required');

        $client->postJson('/api/v1/auth/accept-terms', ['terms_accepted' => true])
            ->assertOk();

        $this->assertNotNull($user->fresh()->accepted_terms_at);
        $client->getJson('/api/v1/dashboard')->assertOk();
    }

    public function test_beta_capacity_can_block_new_tenants(): void
    {
        config([
            'vitaleevo.beta.enabled' => true,
            'vitaleevo.beta.max_tenants' => 1,
        ]);
        Tenant::create(['name' => 'Primeiro', 'slug' => 'primeiro']);
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/tenants', ['name' => 'Segundo'])
            ->assertForbidden();

        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_totp_implementation_matches_rfc_vector(): void
    {
        $this->assertSame('287082', Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
    }

    public function test_platform_owner_requires_mfa_and_can_use_recovery_code(): void
    {
        $tenant = Tenant::create(['name' => 'MFA', 'slug' => 'mfa']);
        $owner = User::factory()->create([
            'current_tenant_id' => $tenant->id,
            'is_platform_owner' => true,
            'email_verified_at' => now(),
        ]);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);
        $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->actingAs($owner, 'sanctum');

        $this->getJson('/api/v1/platform/metrics')
            ->assertStatus(428)
            ->assertJsonPath('code', 'mfa_setup_required');

        $setup = $this->getJson('/api/v1/auth/mfa/setup')
            ->assertOk()
            ->assertJsonStructure(['secret', 'provisioning_uri']);
        $secret = $setup->json('secret');

        $rotated = $this->postJson('/api/v1/auth/mfa/setup/rotate')
            ->assertOk()
            ->assertJsonStructure(['secret', 'provisioning_uri']);
        $rotatedSecret = $rotated->json('secret');
        $this->assertNotSame($secret, $rotatedSecret);

        $enabled = $this->postJson('/api/v1/auth/mfa/enable', [
            'code' => Totp::code($rotatedSecret),
        ])->assertOk()->assertJsonCount(8, 'recovery_codes');
        $recoveryCode = $enabled->json('recovery_codes.0');

        $this->getJson('/api/v1/platform/metrics')->assertOk();
        $this->withSession(['mfa_verified_at' => 0]);
        $this->getJson('/api/v1/platform/metrics')
            ->assertStatus(428)
            ->assertJsonPath('code', 'mfa_required');

        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $recoveryCode])
            ->assertOk()
            ->assertJsonPath('recovery_code_used', true)
            ->assertJsonPath('recovery_codes_remaining', 7);
        $this->getJson('/api/v1/platform/metrics')->assertOk();
    }

    public function test_health_endpoint_checks_database_and_cache(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', true)
            ->assertJsonPath('checks.cache', true);
    }

    public function test_test_suite_is_isolated_from_development_database_and_cache(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));
    }
}
