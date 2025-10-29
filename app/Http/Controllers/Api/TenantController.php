<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TenantController extends BaseController
{
    private function generateUniqueSlug(string $name, ?string $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = Tenant::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            if (!$query->exists()) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function index()
    {
        $user = auth()->user();
        $tenants = $user->tenants()->with(['members'])->get();
        
        return $this->success($tenants, 'Tenants récupérés');
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            DB::beginTransaction();

            $slug = $this->generateUniqueSlug($validated['name']);

            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => $slug,
            ]);

            $user = $request->user();
            $adminRole = Role::where('name', 'admin')->first();
            
            $user->tenants()->attach($tenant->id, ['role_id' => $adminRole->id]);

            DB::commit();

            return $this->created($tenant->load('members'), 'Organisation créée avec succès');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la création du tenant: ' . $e->getMessage());
            return $this->error('Une erreur est survenue lors de la création', 500);
        }
    }

    public function show(Tenant $tenant)
    {
        $user = auth()->user();
        
        if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            return $this->forbidden('Vous n\'avez pas accès à cette organisation');
        }

        return $this->success($tenant->load(['members', 'projects', 'tasks']), 'Tenant récupéré');
    }

    public function update(Request $request, Tenant $tenant)
    {
        try {
            $user = auth()->user();
            
            if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
                return $this->forbidden('Vous n\'avez pas accès à cette organisation');
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $slug = $this->generateUniqueSlug($validated['name'], $tenant->id);

            $tenant->update([
                'name' => $validated['name'],
                'slug' => $slug,
            ]);
            
            return $this->success($tenant->load('members'), 'Organisation mise à jour');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du tenant: ' . $e->getMessage());
            return $this->error('Une erreur est survenue lors de la mise à jour', 500);
        }
    }

    public function destroy(Tenant $tenant)
    {
        $user = auth()->user();
        
        if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            return $this->forbidden('Vous n\'avez pas accès à cette organisation');
        }

        if ($tenant->id === $user->tenant_id) {
            return $this->error('Vous ne pouvez pas supprimer votre organisation principale', 400);
        }

        $tenant->delete();
        return $this->noContent('Organisation supprimée');
    }

    public function addUser(Request $request, Tenant $tenant)
    {
        try {
            $user = auth()->user();
            
            if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
                return $this->forbidden('Vous n\'avez pas accès à cette organisation');
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'password' => 'required|min:8',
                'role' => 'required|in:admin,user'
            ]);

            $newUser = User::create([
                'tenant_id' => $tenant->id,
                'current_tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $role = Role::where('name', $validated['role'])->first();
            $newUser->assignRole($validated['role']);
            $newUser->tenants()->attach($tenant->id, ['role_id' => $role->id]);

            return $this->created($newUser, 'Utilisateur ajouté à l\'organisation');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'ajout d\'utilisateur: ' . $e->getMessage());
            return $this->error('Une erreur est survenue', 500);
        }
    }

    public function users(Tenant $tenant)
    {
        $user = auth()->user();
        
        if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            return $this->forbidden('Vous n\'avez pas accès à cette organisation');
        }

        $users = $tenant->members()->with('roles')->get();
        return $this->success($users, 'Utilisateurs de l\'organisation récupérés');
    }

    public function projects(Tenant $tenant)
    {
        $user = auth()->user();
        
        if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            return $this->forbidden('Vous n\'avez pas accès à cette organisation');
        }

        $projects = $tenant->projects()->with('tasks')->get();
        return $this->success($projects, 'Projets de l\'organisation récupérés');
    }

    public function tasks(Tenant $tenant)
    {
        $user = auth()->user();
        
        if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            return $this->forbidden('Vous n\'avez pas accès à cette organisation');
        }

        $tasks = $tenant->tasks()->with(['project', 'assignedUser'])->get();
        return $this->success($tasks, 'Tâches de l\'organisation récupérées');
    }
}
