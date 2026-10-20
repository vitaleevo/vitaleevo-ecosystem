<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitaleevoMvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_tenant_membership_and_active_context(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->postJson('/api/v1/auth/register', [
            'name'=>'Alexandre','email'=>'alex@example.test','password'=>'Password123','password_confirmation'=>'Password123','company_name'=>'Vitaleevo Teste',
        ]);
        $response->assertCreated()->assertJsonPath('user.email','alex@example.test');
        $this->assertDatabaseCount('tenants',1);
        $this->assertDatabaseHas('memberships',['role'=>'owner']);
        $this->assertNotNull(User::first()->current_tenant_id);
    }

    public function test_tenant_scope_blocks_cross_tenant_contact_access(): void
    {
        $a = Tenant::create(['name'=>'A','slug'=>'a']);
        $b = Tenant::create(['name'=>'B','slug'=>'b']);
        $user = User::factory()->create(['current_tenant_id'=>$a->id]);
        Membership::create(['tenant_id'=>$a->id,'user_id'=>$user->id,'role'=>'owner']);
        Contact::create(['tenant_id'=>$b->id,'name'=>'Contacto B']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/contacts/1',['name'=>'Intrusão'])
            ->assertNotFound();
        $this->assertDatabaseHas('contacts',['tenant_id'=>$b->id,'name'=>'Contacto B']);
    }

    public function test_user_cannot_switch_to_tenant_without_membership(): void
    {
        $a = Tenant::create(['name'=>'A','slug'=>'a']);
        $b = Tenant::create(['name'=>'B','slug'=>'b']);
        $user = User::factory()->create(['current_tenant_id'=>$a->id]);
        Membership::create(['tenant_id'=>$a->id,'user_id'=>$user->id,'role'=>'owner']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/tenants/'.$b->id.'/switch')->assertForbidden();
    }

    public function test_public_service_request_is_created_with_reference(): void
    {
        $this->seed();
        $category = \App\Models\ServiceCategory::first();
        $payload = [
            'category_id'=>$category->id,'customer_name'=>'Cliente','customer_phone'=>'923000000',
            'description'=>'Preciso de assistência técnica urgente.','consent'=>true,
        ];
        $first = $this->withHeader('Idempotency-Key', 'public-test-001')
            ->postJson('/api/v1/public/service-requests', $payload)
            ->assertCreated()->assertJsonStructure(['reference','status']);
        $second = $this->withHeader('Idempotency-Key', 'public-test-001')
            ->postJson('/api/v1/public/service-requests', $payload)
            ->assertOk();
        $this->assertSame($first->json('reference'), $second->json('reference'));
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_public_request_is_hidden_from_tenant_until_platform_owner_assigns_it(): void
    {
        $this->seed();
        $tenant = Tenant::create(['name'=>'Operação A','slug'=>'operacao-a']);
        $owner = User::factory()->create(['current_tenant_id'=>$tenant->id,'is_platform_owner'=>true]);
        Membership::create(['tenant_id'=>$tenant->id,'user_id'=>$owner->id,'role'=>'owner']);
        $request = \App\Models\ServiceRequest::create([
            'category_id'=>\App\Models\ServiceCategory::first()->id,
            'customer_name'=>'Cliente Público','customer_phone'=>'924000000',
            'description'=>'Pedido público ainda sem empresa atribuída.','status'=>'submitted',
            'public_reference'=>'SR-TESTPUBLIC','consent'=>true,
        ]);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/service-requests')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/platform/service-requests')
            ->assertOk()->assertJsonPath('data.0.id', $request->id);

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/platform/service-requests/'.$request->id.'/assign', ['tenant_id'=>$tenant->id])
            ->assertOk()->assertJsonPath('tenant_id', $tenant->id);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/service-requests')
            ->assertOk()->assertJsonPath('data.0.id', $request->id);
    }

    public function test_invitation_creates_member_only_after_valid_token_is_accepted(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $tenant = Tenant::create(['name'=>'Empresa Convite','slug'=>'empresa-convite']);
        $owner = User::factory()->create(['current_tenant_id'=>$tenant->id]);
        Membership::create(['tenant_id'=>$tenant->id,'user_id'=>$owner->id,'role'=>'owner']);

        $invite = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/memberships', [
            'email'=>'novo@example.test','role'=>'agent',
        ])->assertCreated()->json('invitation');

        $this->assertDatabaseMissing('users',['email'=>'novo@example.test']);

        $this->withHeaders(['Origin'=>'http://localhost:3001','Referer'=>'http://localhost:3001/'])
            ->postJson('/api/v1/auth/invitations/accept', [
                'token'=>$invite['token'],'name'=>'Novo Utilizador','password'=>'Password123','password_confirmation'=>'Password123',
            ])->assertOk();

        $newUser = User::where('email','novo@example.test')->firstOrFail();
        $this->assertDatabaseHas('memberships',['tenant_id'=>$tenant->id,'user_id'=>$newUser->id,'role'=>'agent','status'=>'active']);
    }
}
