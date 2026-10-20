<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_platform_owner, 403, 'Acesso reservado ao Platform Owner.');
        return $next($request);
    }
}
