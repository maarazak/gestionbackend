<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenant_name' => 'required|string|max:255',
                'tenant_slug' => 'required|string|max:255|unique:tenants,slug|regex:/^[a-z0-9-]+$/',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'tenant_slug.regex' => 'Le slug ne peut contenir que des lettres minuscules, chiffres et tirets',
                'tenant_slug.unique' => 'Ce slug est déjà utilisé',
                'email.unique' => 'Cet email est déjà utilisé',
                'password.confirmed' => 'Les mots de passe ne correspondent pas',
                'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            ]);

            DB::beginTransaction();

            $tenant = Tenant::create([
                'name' => $validated['tenant_name'],
                'slug' => $validated['tenant_slug']
            ]);

            $user = User::create([
                'current_tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $adminRole = Role::where('name', 'admin')->first();
            $user->assignRole('admin');
            
            $user->tenants()->attach($tenant->id, ['role_id' => $adminRole->id]);

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            $user->role = $user->getRoleAttribute();
            $userData = $user->load(['currentTenant', 'tenants']);

            return $this->created([
                'user' => $userData,
                'token' => $token
            ], 'Compte créé avec succès');

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationError($e->errors(), 'Erreurs de validation');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de l\'inscription: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return $this->error('Une erreur est survenue lors de l\'inscription: ' . $e->getMessage(), 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                'tenant_slug' => 'required'
            ]);

            $tenant = Tenant::where('slug', $validated['tenant_slug'])->first();

            if (!$tenant) {
                throw ValidationException::withMessages([
                    'tenant_slug' => ['Organisation non trouvée.']
                ]);
            }

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Identifiants incorrects.']
                ]);
            }

            if (!$user->hasAccessToTenant($tenant->id)) {
                throw ValidationException::withMessages([
                    'tenant_slug' => ['Vous n\'avez pas accès à cette organisation.']
                ]);
            }

            $user->update(['current_tenant_id' => $tenant->id]);

            $tenantUser = $user->tenants()->where('tenants.id', $tenant->id)->first();
            if ($tenantUser && $tenantUser->pivot && $tenantUser->pivot->role_id) {
                $role = Role::find($tenantUser->pivot->role_id);
                if ($role) {
                    $user->syncRoles([$role->name]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->role = $user->getRoleAttribute();
            $userData = $user->load(['currentTenant', 'tenants']);

            return $this->success([
                'user' => $userData,
                'token' => $token
            ], 'Connexion réussie');

        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion: ' . $e->getMessage());
            return $this->error('Une erreur est survenue lors de la connexion', 500);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->role = $user->getRoleAttribute();
        return $this->success($user->load(['currentTenant', 'tenants']), 'Utilisateur récupéré');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Déconnecté');
    }

    public function switchTenant(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenant_id' => 'required|uuid|exists:tenants,id'
            ]);

            $user = $request->user();

            if (!$user->hasAccessToTenant($validated['tenant_id'])) {
                return $this->error('Vous n\'avez pas accès à cette organisation', 403);
            }

            if (!$user->switchTenant($validated['tenant_id'])) {
                return $this->error('Impossible de changer d\'organisation', 500);
            }

            $user->refresh();
            $user->role = $user->getRoleAttribute();
            $userData = $user->load(['currentTenant', 'tenants']);

            return $this->success($userData, 'Organisation changée avec succès');

        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Erreur lors du changement d\'organisation: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return $this->error('Une erreur est survenue lors du changement d\'organisation', 500);
        }
    }
}
