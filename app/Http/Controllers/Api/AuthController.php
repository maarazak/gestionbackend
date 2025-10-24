<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AuthController extends BaseController
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenant_name' => 'required|string|max:255',
                'tenant_slug' => 'required|string|max:255|unique:tenants,slug|regex:/^[a-z0-9-]+$/',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'tenant_slug.regex' => 'Le slug ne peut contenir que des lettres minuscules, chiffres et tirets',
                'tenant_slug.unique' => 'Ce slug est déjà utilisé',
                'password.confirmed' => 'Les mots de passe ne correspondent pas',
                'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            ]);

            
            $existingUser = User::whereHas('tenant', function($query) use ($validated) {
                $query->where('slug', $validated['tenant_slug']);
            })->where('email', $validated['email'])->first();

            if ($existingUser) {
                return $this->validationError([
                    'email' => ['Cet email est déjà utilisé dans cette organisation']
                ]);
            }

            DB::beginTransaction();

         
            $tenant = Tenant::create([
                'name' => $validated['tenant_name'],
                'slug' => $validated['tenant_slug']
            ]);

         
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

          
            $user->assignRole('admin');

          
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();
            $user->role =  $user->getRoleAttribute();
            return $this->created([
                'user' => $user->load('tenant'),
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

            $user = User::where('tenant_id', $tenant->id)
                ->where('email', $validated['email'])
                ->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Identifiants incorrects.']
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->role =  $user->getRoleAttribute();
            return $this->success([
                'user' => $user->load('tenant'),
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
        $request->user()->role =  $request->user()->getRoleAttribute();
        return $this->success($request->user()->load('tenant'), 'Utilisateur récupéré');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Déconnecté');
    }
}
