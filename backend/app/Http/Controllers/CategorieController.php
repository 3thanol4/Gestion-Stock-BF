<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Support\EntityCode;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    private $warning = 'non_autorise';

    private function getEntrepriseId(Request $request): ?int
    {
        $user = $request->user();
        if ($user->hasRole('administrateur_plateforme')) {
            return $request->query('entreprise_id') ? (int) $request->query('entreprise_id') : null;
        }

        return $user->entreprise_id;
    }

    private function canMutateCatalog(Request $request): bool
    {
        $user = $request->user();

        return $user->hasRole('administrateur_plateforme') || $user->hasRole('administrateur_entreprise');
    }

    /** Inclut le gestionnaire de stock (besoin d’articles pour les livraisons). */
    private function canManageCategories(Request $request): bool
    {
        return $this->canMutateCatalog($request) || $request->user()->hasRole('gestionnaire_stock');
    }

    public function index(Request $request)
    {
        $entrepriseId = $this->getEntrepriseId($request);
        if (! $entrepriseId) {
            return response()->json([]);
        }

        return response()->json(
            Categorie::where('entreprise_id', $entrepriseId)->orderBy('nom')->get()
        );
    }

    public function store(Request $request)
    {
        if (! $this->canManageCategories($request)) {
            return response()->json(['message' => $this->warning], 403);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'code' => 'sometimes|string|max:64',
            'entreprise_id' => 'sometimes|exists:entreprises,id',
        ]);

        $user = $request->user();
        $entrepriseId = $user->hasRole('administrateur_plateforme')
            ? ($validated['entreprise_id'] ?? $user->entreprise_id)
            : $user->entreprise_id;

        if (! $entrepriseId) {
            return response()->json(['message' => 'Entreprise requise'], 422);
        }

        $code = $validated['code'] ?? EntityCode::forEntreprise('categories', $entrepriseId, 'CAT');

        $cat = Categorie::create([
            'entreprise_id' => $entrepriseId,
            'code' => $code,
            'nom' => $validated['nom'],
        ]);

        return response()->json($cat, 201);
    }

    public function update(Request $request, $id)
    {
        if (! $this->canManageCategories($request)) {
            return response()->json(['message' => $this->warning], 403);
        }

        $categorie = Categorie::findOrFail($id);
        $user = $request->user();
        if (! $user->hasRole('administrateur_plateforme') && $categorie->entreprise_id !== $user->entreprise_id) {
            return response()->json(['message' => $this->warning], 403);
        }

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:64',
        ]);

        $categorie->update($validated);

        return response()->json($categorie);
    }

    public function destroy(Request $request, $id)
    {
        if (! $this->canMutateCatalog($request)) {
            return response()->json(['message' => $this->warning], 403);
        }

        $categorie = Categorie::findOrFail($id);
        $user = $request->user();
        if (! $user->hasRole('administrateur_plateforme') && $categorie->entreprise_id !== $user->entreprise_id) {
            return response()->json(['message' => $this->warning], 403);
        }

        $categorie->delete();

        return response()->json(['message' => 'Catégorie supprimée']);
    }
}
