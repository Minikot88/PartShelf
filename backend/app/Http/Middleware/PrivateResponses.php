<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivateResponses
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        if ($request->is('api/*', 'sanctum/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
