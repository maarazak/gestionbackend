<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;

class TenantScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $currentTenantId = $user->current_tenant_id;

        if (!$currentTenantId) {
            return response()->json(['message' => 'Aucun tenant actif'], 403);
        }


        if (!$user->hasAccessToTenant($currentTenantId)) {
            return response()->json(['message' => 'Accès refusé à ce tenant'], 403);
        }

        $request->merge(['tenant_id' => $currentTenantId]);

       
        Project::addGlobalScope('tenant', function ($query) use ($currentTenantId) {
            $query->where('tenant_id', $currentTenantId);
        });

        Task::addGlobalScope('tenant', function ($query) use ($currentTenantId) {
            $query->where('tenant_id', $currentTenantId);
        });

        return $next($request);
    }
}
