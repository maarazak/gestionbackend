<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends BaseController
{
    /**
     * Liste des utilisateurs du tenant
     */
    public function index(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->unauthorized('Seuls les administrateurs peuvent voir la liste des utilisateurs');
        }

        $users = User::where('tenant_id', $request->user()->tenant_id)
            ->with('roles')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'user',
                    'created_at' => $user->created_at,
                ];
            });

        return $this->success($users, 'Utilisateurs récupérés');
    }

    /**
     * Inviter un nouvel utilisateur (admin uniquement)
     */
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
            ]);

            $existingUser = User::where('tenant_id', $request->user()->tenant_id)
                ->where('email', $validated['email'])
                ->first();

            if ($existingUser) {
                return $this->validationError([
                    'email' => ['Cet email est déjà utilisé dans votre organisation']
                ]);
            }

            DB::beginTransaction();

            $user = User::create([
                'tenant_id' => $request->user()->tenant_id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->assignRole('user');

            DB::commit();

            return $this->created([
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'user',
            ], 'Utilisateur invité avec succès');

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Une erreur est survenue lors de l\'invitation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Supprimer un utilisateur (admin uniquement)
     */
    public function destroy(Request $request, User $user)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->unauthorized('Seuls les administrateurs peuvent supprimer des utilisateurs');
        }

        if ($user->tenant_id !== $request->user()->tenant_id) {
            return $this->unauthorized('Vous ne pouvez pas supprimer cet utilisateur');
        }

        if ($user->id === $request->user()->id) {
            return $this->error('Vous ne pouvez pas supprimer votre propre compte', 400);
        }

        $user->delete();

        return $this->success(null, 'Utilisateur supprimé avec succès');
    }
}
