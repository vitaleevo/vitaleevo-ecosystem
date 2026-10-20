<?php

namespace App\Http\Controllers\Api;

use App\Models\Membership;
use App\Models\MembershipInvitation;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Plan;
use App\Models\Professional;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends BaseApiController
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'company_name' => ['required', 'string', 'max:160'],
            'plan_code' => ['nullable', 'in:trial,basic,advanced'],
            'terms_accepted' => ['accepted'],
        ]);
        $this->enforceBetaCapacity('tenants', Tenant::count());

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'is_platform_owner' => (app()->environment('local') && User::count() === 0)
                    || (filled(env('VITALEEVO_BOOTSTRAP_OWNER_EMAIL'))
                    && strcasecmp($data['email'], env('VITALEEVO_BOOTSTRAP_OWNER_EMAIL')) === 0),
            ]);
            $user->forceFill(['accepted_terms_at' => now()])->save();

            $base = Str::slug($data['company_name']) ?: 'empresa';
            $slug = $base.'-'.Str::lower(Str::random(6));
            $planCode = $data['plan_code'] ?? 'trial';
            $defaults = [
                'trial' => ['name' => 'Teste', 'price_kz' => 0],
                'basic' => ['name' => 'Básico', 'price_kz' => 50000],
                'advanced' => ['name' => 'Avançado', 'price_kz' => 200000],
            ][$planCode];
            $plan = Plan::firstOrCreate(['code' => $planCode], $defaults + ['active' => true]);
            $tenant = Tenant::create(['name' => $data['company_name'], 'slug' => $slug, 'plan' => $planCode]);
            Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'owner']);
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $planCode === 'trial' ? 'trialing' : 'active',
                'trial_ends_at' => $planCode === 'trial' ? now()->addDays(3) : null,
            ]);
            $user->update(['current_tenant_id' => $tenant->id]);

            $pipeline = Pipeline::create(['tenant_id' => $tenant->id, 'name' => 'Funil Comercial', 'is_default' => true]);
            foreach (['Novo', 'Qualificado', 'Proposta', 'Negociação', 'Ganho'] as $position => $name) {
                PipelineStage::create(['tenant_id' => $tenant->id, 'pipeline_id' => $pipeline->id, 'name' => $name, 'position' => $position]);
            }

            return $user;
        });

        if (app()->environment('local')) {
            $user->markEmailAsVerified();
        } else {
            $user->sendEmailVerificationNotification();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json($this->payload($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        abort_unless(Auth::guard('web')->attempt(['email' => mb_strtolower($credentials['email']), 'password' => $credentials['password']]), 422, 'Credenciais inválidas.');
        $request->session()->regenerate();
        $request->session()->forget(['mfa_verified_at', 'mfa_pending_secret']);

        return response()->json($this->payload($request->user()));
    }

    public function acceptInvitation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms_accepted' => ['accepted'],
        ]);

        $invitation = MembershipInvitation::where('token_hash', hash('sha256', $data['token']))
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $user = DB::transaction(function () use ($data, $invitation) {
            $user = User::where('email', $invitation->email)->first();
            if (! $user) {
                abort_unless(filled($data['name'] ?? null) && filled($data['password'] ?? null), 422, 'Nome e palavra-passe são obrigatórios para novos utilizadores.');
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($data['password']),
                ]);
            }

            if (! $user->accepted_terms_at) {
                $user->forceFill(['accepted_terms_at' => now()])->save();
            }

            Membership::updateOrCreate(
                ['tenant_id' => $invitation->tenant_id, 'user_id' => $user->id],
                ['role' => $invitation->role, 'status' => 'active']
            );
            if (! $user->current_tenant_id) {
                $user->update(['current_tenant_id' => $invitation->tenant_id]);
            }
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json($this->payload($user));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        \Illuminate\Support\Facades\Password::sendResetLink(['email' => mb_strtolower($data['email'])]);

        return response()->json(['message' => 'Se o email existir, enviaremos instruções para recuperar a palavra-passe.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $status = \Illuminate\Support\Facades\Password::reset(
            ['email' => mb_strtolower($data['email']), 'password' => $data['password'], 'password_confirmation' => $data['password_confirmation'], 'token' => $data['token']],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        abort_unless($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET, 422, __($status));

        return response()->json(['message' => 'Palavra-passe atualizada.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Se necessário, um novo link de verificação foi enviado.']);
    }

    public function acceptTerms(Request $request): JsonResponse
    {
        $request->validate(['terms_accepted' => ['accepted']]);
        $request->user()->forceFill(['accepted_terms_at' => now()])->save();
        $this->audit($request, 'accepted', 'terms', $request->user()->id);

        return response()->json($this->payload($request->user()->fresh()));
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals((string) $hash, sha1($user->getEmailForVerification())), 403);
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:3001'), '/').'/?verified=1');
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sessão terminada.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    private function payload(User $user): array
    {
        $memberships = Membership::query()
            ->where('memberships.user_id', $user->id)
            ->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')
            ->select('memberships.tenant_id', 'memberships.role', 'memberships.status', 'tenants.name', 'tenants.slug', 'tenants.status as tenant_status')
            ->orderBy('tenants.name')->get();

        $userData = $user->only(['id', 'name', 'email', 'email_verified_at', 'accepted_terms_at', 'current_tenant_id', 'is_platform_owner']);
        $verifiedAt = request()->hasSession() ? (int) request()->session()->get('mfa_verified_at', 0) : 0;
        $mfaFresh = $verifiedAt > 0 && (now()->timestamp - $verifiedAt) <= 43200;
        $userData['mfa_enabled'] = (bool) $user->mfa_enabled_at;
        $userData['mfa_verified'] = ! $user->is_platform_owner || ((bool) $user->mfa_enabled_at && $mfaFresh);

        return [
            'user' => $userData,
            'memberships' => $memberships,
            'professional_id' => Professional::where('user_id', $user->id)->value('id'),
        ];
    }
}
