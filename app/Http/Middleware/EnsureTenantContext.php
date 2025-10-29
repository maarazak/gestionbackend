<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && !$request->user()->current_tenant_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucun tenant actif. Veuillez sélectionner une organisation.'
            ], 403);
        }

        return $next($request);
    }
}
