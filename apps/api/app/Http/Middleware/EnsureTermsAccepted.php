<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->accepted_terms_at) {
            return response()->json([
                'message' => 'Aceite os Termos de Utilização e a Política de Privacidade para continuar.',
                'code' => 'terms_acceptance_required',
            ], 428);
        }

        return $next($request);
    }
}
