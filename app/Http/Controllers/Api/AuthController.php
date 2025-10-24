<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'tenant_name' => 'required|string|max:255',
            'tenant_slug' => 'required|string|unique:tenants,slug',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8'
        ]);

        // Créer le tenant
        $tenant = Tenant::create([
            'name' => $validated['tenant_name'],
            'slug' => $validated['tenant_slug']
        ]);

        // Créer l'utilisateur admin
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole('admin');

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->created([
            'user' => $user->load('tenant'),
            'token' => $token
        ], 'Compte créé avec succès');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'tenant_slug' => 'required'
        ]);

        $tenant = Tenant::where('slug', $request->tenant_slug)->first();
        
        if (!$tenant) {
            throw ValidationException::withMessages([
                'tenant_slug' => ['Organisation non trouvée.']
            ]);
        }

        $user = User::where('tenant_id', $tenant->id)
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.']
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => $user->load('tenant'),
            'token' => $token
        ], 'Connexion réussie');
    }

    public function me(Request $request)
    {
        return $this->success($request->user()->load('tenant'), 'Utilisateur récupéré');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Déconnecté');
    }
}
