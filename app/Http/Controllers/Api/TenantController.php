<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TenantController extends BaseController
{
    public function index()
    {
        $tenants = Tenant::with(['users', 'projects', 'tasks'])->get();
        return $this->success($tenants, 'Tenants récupérés');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:tenants,slug',
            'domain' => 'nullable|string|max:255',
            'settings' => 'nullable|array'
        ]);

        $tenant = Tenant::create($validated);
        return $this->created($tenant, 'Tenant créé avec succès');
    }

    public function show(Tenant $tenant)
    {
        return $this->success($tenant->load(['users', 'projects', 'tasks']), 'Tenant récupéré');
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => 'string|unique:tenants,slug,' . $tenant->id,
            'domain' => 'nullable|string|max:255',
            'settings' => 'nullable|array'
        ]);

        $tenant->update($validated);
        return $this->success($tenant, 'Tenant mis à jour');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return $this->noContent('Tenant supprimé');
    }

    public function addUser(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'role' => 'in:admin,member'
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'member'
        ]);

        return $this->created($user, 'Utilisateur ajouté au tenant');
    }

    public function users(Tenant $tenant)
    {
        $users = $tenant->users;
        return $this->success($users, 'Utilisateurs du tenant récupérés');
    }

    public function projects(Tenant $tenant)
    {
        $projects = $tenant->projects()->with('tasks')->get();
        return $this->success($projects, 'Projets du tenant récupérés');
    }

    public function tasks(Tenant $tenant)
    {
        $tasks = $tenant->tasks()->with(['project', 'assignedUser'])->get();
        return $this->success($tasks, 'Tâches du tenant récupérées');
    }
}
