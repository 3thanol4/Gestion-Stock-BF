<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Categorie;
use App\Support\EntityCode;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    private $warning = 'non_autorise';
    
    private function canMutateCatalog(Request $request): bool
    {
        $user = $request->user();

        return $user->hasRole('administrateur_plateforme') || $user->hasRole('administrateur_entreprise');
    }

    /** Admin catalogue ou gestionnaire de stock (entrées de stock). */
    private function canCreateOrUpdateArticle(Request $request): bool
    {
        $user = $request->user();

        return $this->canMutateCatalog($request) || $user->hasRole('gestionnaire_stock');
    }

    private function getEntrepriseId(Request $request)
    {
        $user = $request->user();
        if ($user->hasRole('administrateur_plateforme')) {
            return $request->query('entreprise_id', $user->entreprise_id);
        }
        return $user->entreprise_id;
    }

    public function index(Request $request)
    {
        $entrepriseId = $this->getEntrepriseId($request);
        if (!$entrepriseId)
            {
                return response()->json(['message' => 'Entreprise requise'], 422);
            } 

        $articles = Article::where('entreprise_id', $entrepriseId)
            ->with('categorie')
            ->orderBy('nom')
            ->get()
            ->map(function ($article) {
                $article->dernier_prix_vente = $article->dernierPrixVente();
                $article->dernier_prix_achat = $article->dernierPrixAchat();
                return $article;
            });

        return response()->json($articles);
    }

    public function store(Request $request)
    {
        if (! $this->canCreateOrUpdateArticle($request)) {
            return response()->json(['message' => $this->warning], 403);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'unite_mesure' => 'required|string|max:50',
            'categorie_id' => 'required|exists:categories,id',
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

        $categorie = Categorie::findOrFail($validated['categorie_id']);
        if ((int) $categorie->entreprise_id !== (int) $entrepriseId) {
            return response()->json(['message' => 'La catégorie ne correspond pas à votre entreprise.'], 422);
        }

        $code = $validated['code'] ?? EntityCode::forEntreprise('articles', $entrepriseId, 'ART');

        $article = Article::create([
            'nom' => $validated['nom'],
            'unite_mesure' => $validated['unite_mesure'],
            'quantite_stock' => 0,
            'categorie_id' => $validated['categorie_id'],
            'entreprise_id' => $entrepriseId,
            'code' => $code,
        ]);

        return response()->json($article->load('categorie'), 201);
    }

    public function update(Request $request, $id)
    {
        if (! $this->canCreateOrUpdateArticle($request)) {
            return response()->json(['message' => $this->warning], 403);
        }

        $user = $request->user();
        $article = Article::findOrFail($id);
        if (! $user->hasRole('administrateur_plateforme') && $article->entreprise_id !== $user->entreprise_id) {
            return response()->json(['message' => $this->warning], 403);
        }

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'unite_mesure' => 'sometimes|string|max:50',
            'categorie_id' => 'sometimes|exists:categories,id',
            'code' => 'sometimes|string|max:64',
        ]);

        if (isset($validated['categorie_id'])) {
            $cat = Categorie::findOrFail($validated['categorie_id']);
            $entrepriseId = $article->entreprise_id;
            if ((int) $cat->entreprise_id !== (int) $entrepriseId) {
                return response()->json(['message' => 'La catégorie ne correspond pas à l’entreprise de l’article.'], 422);
            }
        }

        $article->update($validated);
        return response()->json($article->load('categorie'));
    }

    public function destroy(Request $request, $id)
    {
        if (! $this->canMutateCatalog($request)) {
            return response()->json(['message' => $this->warning], 403);
        }

        $user = $request->user();
        $article = Article::findOrFail($id);
        if (!$user->hasRole('administrateur_plateforme') && $article->entreprise_id !== $user->entreprise_id) {
            return response()->json(['message' => $this->warning], 403);
        }

        $article->delete();
        return response()->json(['message' => 'Article supprimé']);
    }
}
