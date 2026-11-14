<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every client of this API wants JSON. Forcing the Accept header means a
 * request that forgets it still gets a 401/422 JSON body, not a redirect to a
 * login page that doesn't exist.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
