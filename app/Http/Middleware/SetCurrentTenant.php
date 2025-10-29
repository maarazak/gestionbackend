<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // if ($user && !$user->current_tenant_id) {
        //     $firstTenant = $user->tenants()->first();
        //     if ($firstTenant) {
        //         $user->update(['current_tenant_id' => $firstTenant->id]);
        //     }
        // }

        if ($user && $user->current_tenant_id) {
            config(['app.current_tenant_id' => $user->current_tenant_id]);
        }

        return $next($request);
    }
}
