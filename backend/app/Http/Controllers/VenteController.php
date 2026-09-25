<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Vente;
use App\Models\DetailVente;
use App\Support\EntityCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenteController extends Controller
{
    private $warning = 'non_autorise';

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
        if ($request->user()->hasRole('gestionnaire_stock')) {
            return response()->json(['message' => $this->warning], 403);
        }

        $entrepriseId = $this->getEntrepriseId($request);
        if (!$entrepriseId) {
            return response()->json([]);
        }

        return response()->json(
            Vente::where('entreprise_id', $entrepriseId)
                ->with(['details.article', 'user'])
                ->orderByDesc('date_vente')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();
        // Seuls vendeur, admin_entreprise, admin_plateforme
        if ($user->hasRole('gestionnaire_stock')) {
            return response()->json(['message' => $this->warning], 403);
        }

        $validated = $request->validate([
            'date_vente' => 'required|date',
            'client_nom' => 'required|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'entreprise_id' => 'sometimes|exists:entreprises,id',
            'details' => 'required|array|min:1',
            'details.*.article_id' => 'required|exists:articles,id',
            'details.*.quantite' => 'required|numeric|min:0.01',
            'details.*.prix_vente_unitaire' => 'required|numeric|min:0',
            'details.*.remise' => 'nullable|numeric|min:0',
        ]);

        $entrepriseId = $user->hasRole('administrateur_plateforme')
            ? ($validated['entreprise_id'] ?? $user->entreprise_id)
            : $user->entreprise_id;

        if (! $entrepriseId) {
            return response()->json(['message' => 'Entreprise requise'], 422);
        }

        return DB::transaction(function () use ($validated, $entrepriseId, $user) {
            $montantTotal = 0;

            foreach ($validated['details'] as $detail) {
                $article = Article::findOrFail($detail['article_id']);
                if ($article->entreprise_id !== $entrepriseId) {
                    throw ValidationException::withMessages([
                        'details' => ['Un article ne correspond pas à l’entreprise sélectionnée.'],
                    ]);
                }
                if ($article->quantite_stock < $detail['quantite']) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insuffisant pour l'article \"" . e($article->nom) . "\". Stock disponible: "
                            . e($article->quantite_stock) . " " . e($article->unite_mesure) . "."
                    ]);
                }
            }

            $vente = Vente::create([
                'code' => EntityCode::forVente($entrepriseId),
                'date_vente' => $validated['date_vente'],
                'client_nom' => $validated['client_nom'] ?? null,
                'client_contact' => $validated['client_contact'] ?? null,
                'montant_total' => 0,
                'statut' => 'validee',
                'entreprise_id' => $entrepriseId,
                'user_id' => $user->id,
            ]);

            foreach ($validated['details'] as $detail) {
                $article = Article::findOrFail($detail['article_id']);
                $remise = $detail['remise'] ?? 0;
                $sousTotal = ($detail['prix_vente_unitaire'] - $remise) * $detail['quantite'];
                $montantTotal += $sousTotal;

                DetailVente::create([
                    'vente_id' => $vente->id,
                    'article_id' => $detail['article_id'],
                    'quantite' => $detail['quantite'],
                    'unite_mesure' => $article->unite_mesure,
                    'prix_vente_unitaire' => $detail['prix_vente_unitaire'],
                    'remise' => $remise,
                ]);

                Article::where('id', $detail['article_id'])
                    ->decrement('quantite_stock', $detail['quantite']);
            }

            $vente->update(['montant_total' => $montantTotal]);
            return response()->json($vente->load('details.article', 'user'), 201);
        });
    }

    public function annuler(Request $request, $id)
    {
        $user = $request->user();
        // Seuls admin_entreprise et admin_plateforme
        if (!$user->hasRole('administrateur_plateforme') && !$user->hasRole('administrateur_entreprise')) {
            return response()->json(['message' => $this->warning], 403);
        }

        $vente = Vente::with('details')->findOrFail($id);

        if (!$user->hasRole('administrateur_plateforme') && $vente->entreprise_id !== $user->entreprise_id) {
            return response()->json(['message' => $this->warning], 403);
        }

        if ($vente->statut === 'annulee') {
            return response()->json(['message' => 'Cette vente est déjà annulée.'], 422);
        }

        return DB::transaction(function () use ($vente) {
            // Restaurer le stock
            foreach ($vente->details as $detail) {
                Article::where('id', $detail->article_id)
                    ->increment('quantite_stock', $detail->quantite);
            }

            $vente->update(['statut' => 'annulee']);
            return response()->json([
                'message' => 'Vente annulée et stock restauré.',
                'vente' => $vente->load('details.article', 'user')
            ]);
        });
    }
}
