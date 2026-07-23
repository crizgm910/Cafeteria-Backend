<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID');
        if (! is_string($correlationId) || ! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
        }
        $request->headers->set('X-Correlation-ID', $correlationId);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
