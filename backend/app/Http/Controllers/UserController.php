<?php

namespace App\Http\Controllers;

use App\Data\UserRegistrationData;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private $warning = 'non_autorise';

    public function store(UserRegistrationData $data, Request $request)
    {
        $currentUser = $request->user();

        // Anti-Scraping / Security checks based on roles
        if ($currentUser->hasRole('administrateur_entreprise')) {
            if (!in_array($data->role, ['gestionnaire_stock', 'vendeur'])) {
                throw ValidationException::withMessages(['role' => "Vous ne pouvez pas créer ce niveau de rôle."]);
            }
            $entrepriseIdToAssign = $currentUser->entreprise_id;
        } elseif ($currentUser->hasRole('administrateur_plateforme')) {
            // Créer l'entreprise si un nom est fourni
            $entrepriseIdToAssign = null;
            if ($data->entreprise_nom) {
                $entreprise = Entreprise::firstOrCreate(
                    ['nom' => $data->entreprise_nom]
                );
                $entrepriseIdToAssign = $entreprise->id;
            }
        } else {
            return response()->json(['message' => $this->warning], 403);
        }

        $user = User::create([
            'name' => $data->name,
            'username' => $data->username,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'entreprise_id' => $entrepriseIdToAssign,
            'statut' => true,
        ]);

        $user->assignRole($data->role);

        return response()->json([
            'message' => 'Utilisateur créé avec succès',
            'user' => $user->load('roles', 'entreprise')
        ], 201);
    }

    public function index(Request $request)
    {
        $currentUser = $request->user();

        if ($currentUser->hasRole('administrateur_entreprise')) {
            $users = User::where('entreprise_id', $currentUser->entreprise_id)->with('roles', 'entreprise')->get();
        } elseif ($currentUser->hasRole('administrateur_plateforme')) {
            $users = User::with('roles', 'entreprise')->get();
        } else {
            return response()->json(['message' => $this->warning], 403);
        }

        return response()->json($users);
    }

    public function toggleStatut(Request $request, $id)
    {
        $currentUser = $request->user();
        $targetUser = User::with('roles', 'entreprise')->findOrFail($id);

        // Vérifier les permissions
        if ($currentUser->hasRole('administrateur_entreprise')) {
            // Ne peut modifier que les utilisateurs de son entreprise
            if ($targetUser->entreprise_id !== $currentUser->entreprise_id) {
                return response()->json(['message' => $this->warning], 403);
            }
            // Ne peut pas désactiver un autre admin entreprise
            if ($targetUser->hasRole('administrateur_entreprise') || $targetUser->hasRole('administrateur_plateforme')) {
                return response()->json(['message' => $this->warning], 403);
            }
        } elseif (!$currentUser->hasRole('administrateur_plateforme')) {
            return response()->json(['message' => $this->warning], 403);
        }

        // Ne pas pouvoir se désactiver soi-même
        if ($currentUser->id === $targetUser->id) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre statut.'], 422);
        }

        $targetUser->statut = !$targetUser->statut;
        $targetUser->save();

        return response()->json([
            'message' => $targetUser->statut ? 'Utilisateur activé' : 'Utilisateur désactivé',
            'user' => $targetUser
        ]);
    }

    public function show(Request $request, $id)
    {
        $currentUser = $request->user();
        $targetUser = User::with('roles', 'entreprise')->findOrFail($id);

        // Vérifier les permissions
        if ($currentUser->hasRole('administrateur_entreprise')) {
            if ($targetUser->entreprise_id !== $currentUser->entreprise_id) {
                return response()->json(['message' => $this->warning], 403);
            }
        } elseif (!$currentUser->hasRole('administrateur_plateforme')) {
            return response()->json(['message' => $this->warning], 403);
        }

        return response()->json($targetUser);
    }

    public function update(Request $request, $id)
    {
        $currentUser = $request->user();
        $targetUser = User::with('roles')->findOrFail($id);

        $validated = $this->validateData($request, $id);

        if (!$this->canUpdateUser($currentUser, $targetUser, $validated)) {
            return response()->json(['message' => $this->warning], 403);
        }

        $validated = $this->handleEntreprise($validated);
        $validated = $this->handlePassword($validated);

        $this->handleRole($targetUser, $validated);

        $targetUser->update($validated);
        $targetUser->load('roles', 'entreprise');

        return response()->json([
            'message' => 'Utilisateur mis à jour avec succès',
            'user' => $targetUser
        ]);
    }

    private function validateData($request, $id)
    {
        return $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $id,
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|string|in:administrateur_plateforme,administrateur_entreprise,gestionnaire_stock,vendeur',
            'entreprise_nom' => 'sometimes|nullable|string|max:255',
        ]);
    }

    private function canUpdateUser($currentUser, $targetUser, &$validated)
    {
        if ($currentUser->hasRole('administrateur_entreprise')) {

            if ($targetUser->entreprise_id !== $currentUser->entreprise_id) {
                return false;
            }

            if (
                ($targetUser->hasRole('administrateur_entreprise') ||
                $targetUser->hasRole('administrateur_plateforme')) &&
                $currentUser->id !== $targetUser->id
            ) {
                return false;
            }

            if (
                isset($validated['role']) &&
                !in_array($validated['role'], ['gestionnaire_stock', 'vendeur'])
            ) {
                return false;
            }

            unset($validated['entreprise_nom']);
        }

        return $currentUser->hasRole('administrateur_plateforme') ||
            $currentUser->hasRole('administrateur_entreprise');
    }

    private function handleEntreprise($validated)
    {
        if (!isset($validated['entreprise_nom'])) {
            return $validated;
        }

        if ($validated['entreprise_nom']) {
            $entreprise = Entreprise::firstOrCreate([
                'nom' => $validated['entreprise_nom']
            ]);
            $validated['entreprise_id'] = $entreprise->id;
        } else {
            $validated['entreprise_id'] = null;
        }

        unset($validated['entreprise_nom']);

        return $validated;
    }

    private function handlePassword($validated)
    {
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        return $validated;
    }

    private function handleRole($targetUser, &$validated)
    {
        if (isset($validated['role'])) {
            $targetUser->syncRoles([$validated['role']]);
            unset($validated['role']);
        }
    }
}
