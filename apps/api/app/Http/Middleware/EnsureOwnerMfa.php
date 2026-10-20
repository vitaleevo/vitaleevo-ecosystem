<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_platform_owner, 403);

        if (! $user->mfa_enabled_at) {
            return response()->json([
                'message' => 'Configure MFA antes de aceder à administração da plataforma.',
                'code' => 'mfa_setup_required',
            ], 428);
        }

        $verifiedAt = (int) $request->session()->get('mfa_verified_at', 0);
        if ($verifiedAt <= 0 || (now()->timestamp - $verifiedAt) > 43200) {
            $request->session()->forget('mfa_verified_at');

            return response()->json([
                'message' => 'Valide o segundo fator para continuar.',
                'code' => 'mfa_required',
            ], 428);
        }

        return $next($request);
    }
}
