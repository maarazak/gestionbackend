<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends BaseController
{
    public function index(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->unauthorized('Seuls les administrateurs peuvent voir la liste des utilisateurs');
        }

        $currentTenantId = $request->user()->current_tenant_id;

        $users = User::whereHas('tenants', function($query) use ($currentTenantId) {
            $query->where('tenants.id', $currentTenantId);
        })->with(['tenants' => function($query) use ($currentTenantId) {
            $query->where('tenants.id', $currentTenantId);
        }])->get()->map(function($user) use ($currentTenantId) {
            $tenant = $user->tenants->first();
            $roleId = $tenant?->pivot?->role_id;
            $role = $roleId ? Role::find($roleId) : null;
            
            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role?->name ?? 'user',
                'created_at' => $user->created_at,
            ];
        });

        return $this->success($users, 'Utilisateurs récupérés');
    }

    public function invite(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->unauthorized('Seuls les administrateurs peuvent inviter des utilisateurs');
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8',
                'role' => 'nullable|in:admin,user'
            ]);

            $currentTenantId = $request->user()->current_tenant_id;

            $existingUser = User::where('email', $validated['email'])->first();

            DB::beginTransaction();

            if ($existingUser) {
                if ($existingUser->hasAccessToTenant($currentTenantId)) {
                    DB::rollBack();
                    return $this->validationError([
                        'email' => ['Cet utilisateur a déjà accès à cette organisation']
                    ]);
                }

                $roleName = $validated['role'] ?? 'user';
                $role = Role::where('name', $roleName)->first();
                
                $existingUser->tenants()->attach($currentTenantId, ['role_id' => $role->id]);

                DB::commit();

                return $this->created([
                    'id' => $existingUser->id,
                    'uuid' => $existingUser->uuid,
                    'name' => $existingUser->name,
                    'email' => $existingUser->email,
                    'role' => $roleName,
                ], 'Utilisateur ajouté à l\'organisation avec succès');
            }

            $user = User::create([
                'current_tenant_id' => $currentTenantId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $roleName = $validated['role'] ?? 'user';
            $role = Role::where('name', $roleName)->first();
            
            $user->assignRole($roleName);
            $user->tenants()->attach($currentTenantId, ['role_id' => $role->id]);

            DB::commit();

            return $this->created([
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roleName,
            ], 'Utilisateur invité avec succès');

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Une erreur est survenue lors de l\'invitation: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, User $user)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->unauthorized('Seuls les administrateurs peuvent supprimer des utilisateurs');
        }

        $currentTenantId = $request->user()->current_tenant_id;

        if (!$user->hasAccessToTenant($currentTenantId)) {
            return $this->unauthorized('Cet utilisateur n\'appartient pas à cette organisation');
        }

        if ($user->id === $request->user()->id) {
            return $this->error('Vous ne pouvez pas supprimer votre propre compte', 400);
        }

        $user->tenants()->detach($currentTenantId);

        if ($user->tenants()->count() === 0) {
            $user->delete();
        }

        return $this->success(null, 'Utilisateur supprimé avec succès');
    }
}
