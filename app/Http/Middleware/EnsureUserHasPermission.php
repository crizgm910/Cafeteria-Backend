<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response|JsonResponse
    {
        $authorized = collect($permissions)->contains(
            fn (string $permission) => $request->user()?->hasPermission($permission)
        );

        if (! $authorized) {
            return response()->json([
                'message' => 'No tienes permiso para realizar esta acción.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
