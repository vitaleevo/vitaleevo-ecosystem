<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasVerifiedEmail(), 403, 'Verifique o seu email antes de continuar.');

        return $next($request);
    }
}
