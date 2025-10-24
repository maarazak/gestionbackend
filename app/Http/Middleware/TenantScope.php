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

        // Injecter le tenant_id dans toutes les requêtes
        $request->merge(['tenant_id' => $user->tenant_id]);
        
        // Scope global pour toutes les queries
        Project::addGlobalScope('tenant', function ($query) use ($user) {
            $query->where('tenant_id', $user->tenant_id);
        });
        // Scope global pour toutes les queries
        Task::addGlobalScope('tenant', function ($query) use ($user) {
            $query->where('tenant_id', $user->tenant_id);
        });

        return $next($request);
    }
}
