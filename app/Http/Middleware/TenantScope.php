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

        $currentTenantId = $user->current_tenant_id ?? $user->tenant_id;
        
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
