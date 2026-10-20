<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcosystemStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_is_public_and_lists_six_modules(): void
    {
        $this->getJson('/api/v1/ecosystem/modules')
            ->assertOk()->assertJsonCount(6, 'data');
    }

    public function test_guest_cannot_create_a_storefront(): void
    {
        $this->postJson('/api/v1/ecosystem/storefronts', [
            'owner_kind' => 'personal', 'app_code' => 'jobs', 'name' => 'Teste',
        ])->assertUnauthorized();
    }

    public function test_personal_storefront_is_private_until_published(): void
    {
        $owner = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $this->actingAs($owner);
        $response = $this->postJson('/api/v1/ecosystem/storefronts', [
            'owner_kind' => 'personal', 'app_code' => 'jobs', 'name' => 'Reparações Kiala', 'province' => 'Luanda',
        ])->assertCreated()->assertJsonPath('status', 'draft');
        $id = $response->json('id');

        $this->getJson('/api/v1/ecosystem/storefronts')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson('/api/v1/ecosystem/storefronts/'.$id, ['status' => 'published'])->assertOk();
        $this->getJson('/api/v1/ecosystem/storefronts')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_other_user_cannot_edit_personal_storefront(): void
    {
        $owner = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $this->actingAs($owner);
        $id = $this->postJson('/api/v1/ecosystem/storefronts', [
            'owner_kind' => 'personal', 'app_code' => 'market', 'name' => 'Loja Centro',
        ])->assertCreated()->json('id');

        $intruder = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $this->actingAs($intruder);
        $this->patchJson('/api/v1/ecosystem/storefronts/'.$id, ['status' => 'published'])->assertNotFound();
        $this->assertDatabaseHas('storefronts', ['id' => $id, 'status' => 'draft']);
    }

    public function test_another_tenant_cannot_create_business_storefront(): void
    {
        $owner = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Empresa A', 'slug' => 'empresa-a', 'status' => 'active', 'plan' => 'trial']);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
        $intruder = User::factory()->create(['accepted_terms_at' => now(), 'email_verified_at' => now()]);
        $this->actingAs($intruder);
        $this->postJson('/api/v1/ecosystem/storefronts', [
            'owner_kind' => 'business', 'app_code' => 'jobs', 'tenant_id' => $tenant->id, 'name' => 'Falso anúncio',
        ])->assertForbidden();
        $this->assertDatabaseCount('storefronts', 0);
    }

    public function test_registration_does_not_require_an_organization(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withHeaders([
            'Origin' => 'http://localhost:3001',
            'Referer' => 'http://localhost:3001/',
        ])->postJson('/api/v1/auth/register', [
            'name' => 'Ana Cliente',
            'email' => 'ana.ecosystem@example.test',
            'password' => 'Segura12345',
            'password_confirmation' => 'Segura12345',
            'terms_accepted' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ana.ecosystem@example.test', 'current_tenant_id' => null]);
        $this->assertDatabaseCount('tenants',0);
    }
}
