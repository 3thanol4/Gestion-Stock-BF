<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Livraison;
use App\Models\DetailLivraison;
use App\Support\EntityCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LivraisonController extends Controller
{
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
        if ($request->user()->hasRole('vendeur')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $entrepriseId = $this->getEntrepriseId($request);
        if (!$entrepriseId)
        {
            return response()->json([]);
        } 

        return response()->json(
            Livraison::where('entreprise_id', $entrepriseId)
                ->with(['details.article', 'user'])
                ->orderByDesc('date_livraison')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();
        // Seuls gestionnaire_stock, admin_entreprise, admin_plateforme
        if ($user->hasRole('vendeur')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'date_livraison' => 'required|date',
            'fournisseur_nom' => 'required|string|max:255',
            'fournisseur_contact' => 'nullable|string|max:255',
            'entreprise_id' => 'sometimes|exists:entreprises,id',
            'details' => 'required|array|min:1',
            'details.*.article_id' => 'required|exists:articles,id',
            'details.*.quantite' => 'required|numeric|min:0.01',
            'details.*.prix_achat_unitaire' => 'required|numeric|min:0',
            'details.*.prix_vente_unitaire' => 'required|numeric|min:0',
        ]);

        $entrepriseId = $user->hasRole('administrateur_plateforme')
            ? ($validated['entreprise_id'] ?? $user->entreprise_id)
            : $user->entreprise_id;

        if (! $entrepriseId) {
            return response()->json(['message' => 'Entreprise requise'], 422);
        }

        return DB::transaction(function () use ($validated, $entrepriseId, $user) {
            $livraison = Livraison::create([
                'code' => EntityCode::forLivraison($entrepriseId),
                'date_livraison' => $validated['date_livraison'],
                'fournisseur_nom' => $validated['fournisseur_nom'],
                'fournisseur_contact' => $validated['fournisseur_contact'] ?? null,
                'entreprise_id' => $entrepriseId,
                'user_id' => $user->id,
            ]);

            foreach ($validated['details'] as $detail) {
                $article = Article::findOrFail($detail['article_id']);
                if ($article->entreprise_id !== $entrepriseId) {
                    throw ValidationException::withMessages([
                        'details' => ['Un article ne correspond pas à l’entreprise sélectionnée.'],
                    ]);
                }

                DetailLivraison::create([
                    'livraison_id' => $livraison->id,
                    'article_id' => $detail['article_id'],
                    'quantite' => $detail['quantite'],
                    'unite_mesure' => $article->unite_mesure,
                    'prix_achat_unitaire' => $detail['prix_achat_unitaire'],
                    'prix_vente_unitaire' => $detail['prix_vente_unitaire'],
                ]);

                Article::where('id', $detail['article_id'])
                    ->increment('quantite_stock', $detail['quantite']);
            }

            return response()->json($livraison->load('details.article', 'user'), 201);
        });
    }
}
