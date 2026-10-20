<?php

namespace App\Http\Controllers\Api;

use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class MfaController extends BaseApiController
{
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_platform_owner, 403, 'MFA reforçado está reservado ao Platform Owner.');

        if ($user->mfa_enabled_at) {
            return response()->json(['enabled' => true]);
        }

        $secret = $request->session()->get('mfa_pending_secret');
        if (! is_string($secret) || $secret === '') {
            $secret = Totp::generateSecret();
            $request->session()->put('mfa_pending_secret', $secret);
        }

        return response()->json([
            'enabled' => false,
            'secret' => $secret,
            'provisioning_uri' => Totp::provisioningUri($secret, $user->email),
        ]);
    }

    public function rotateSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_platform_owner, 403, 'MFA reforçado está reservado ao Platform Owner.');
        abort_if($user->mfa_enabled_at, 422, 'MFA já está ativo.');

        $secret = Totp::generateSecret();
        $request->session()->put('mfa_pending_secret', $secret);
        $this->audit($request, 'rotated_setup_secret', 'mfa', $user->id);

        return response()->json([
            'enabled' => false,
            'secret' => $secret,
            'provisioning_uri' => Totp::provisioningUri($secret, $user->email),
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_platform_owner, 403);
        abort_if($user->mfa_enabled_at, 422, 'MFA já está ativo.');

        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->get('mfa_pending_secret');
        abort_unless(is_string($secret) && Totp::verify($secret, $data['code']), 422, 'Código inválido.');

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_recovery_codes' => array_map(fn (string $code) => hash('sha256', $this->normalizeRecoveryCode($code)), $recoveryCodes),
            'mfa_enabled_at' => now(),
        ])->save();

        $request->session()->forget('mfa_pending_secret');
        $request->session()->put('mfa_verified_at', now()->timestamp);
        $this->audit($request, 'enabled', 'mfa', $user->id);

        return response()->json([
            'message' => 'MFA ativado.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_platform_owner && $user->mfa_enabled_at && $user->mfa_secret, 422, 'MFA ainda não está configurado.');
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $secret = Crypt::decryptString($user->mfa_secret);
        $valid = Totp::verify($secret, trim($data['code']));
        $usedRecovery = false;

        if (! $valid) {
            $recoveryHash = hash('sha256', $this->normalizeRecoveryCode($data['code']));
            $codes = $user->mfa_recovery_codes ?? [];
            $index = array_search($recoveryHash, $codes, true);
            if ($index !== false) {
                unset($codes[$index]);
                $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();
                $valid = true;
                $usedRecovery = true;
            }
        }

        abort_unless($valid, 422, 'Código MFA inválido.');
        $request->session()->put('mfa_verified_at', now()->timestamp);
        $this->audit($request, 'verified', 'mfa', $user->id, ['recovery_code' => $usedRecovery]);

        return response()->json([
            'message' => 'Segundo fator validado.',
            'recovery_code_used' => $usedRecovery,
            'recovery_codes_remaining' => count($user->fresh()->mfa_recovery_codes ?? []),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_platform_owner && $user->mfa_enabled_at && $user->mfa_secret, 422);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        abort_unless(Totp::verify(Crypt::decryptString($user->mfa_secret), $data['code']), 422, 'Código MFA inválido.');

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->forceFill([
            'mfa_recovery_codes' => array_map(fn (string $code) => hash('sha256', $this->normalizeRecoveryCode($code)), $recoveryCodes),
        ])->save();
        $this->audit($request, 'regenerated_recovery_codes', 'mfa', $user->id);

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))->all();
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)) ?? '');
    }
}
