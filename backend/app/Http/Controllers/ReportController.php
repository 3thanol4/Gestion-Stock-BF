<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private $detail_livraison = 'detail_livraisons as dl';
    private $detail_vente = 'detail_ventes as dv';
    private $livraison = 'livraisons as l';
    private $vente = 'ventes as v';

    private function getEntrepriseId(Request $request): ?int
    {
        $user = $request->user();
        if ($user->hasRole('administrateur_plateforme')) {
            $eid = $request->query('entreprise_id');
            return $eid ? (int) $eid : null;
        }

        return $user->entreprise_id ? (int) $user->entreprise_id : null;
    }

    private function canView(Request $request): bool
    {
        $user = $request->user();
        return $user->hasRole('gestionnaire_stock')
            || $user->hasRole('administrateur_entreprise')
            || $user->hasRole('administrateur_plateforme');
    }

    public function financialState(Request $request)
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $entrepriseId = $this->getEntrepriseId($request);
        if (! $entrepriseId) {
            return response()->json(['message' => 'Entreprise requise'], 422);
        }

        $today = Carbon::today();
        $periods = [
            'journalier' => [$today->copy(), $today->copy()->endOfDay()],
            'hebdomadaire' => [$today->copy()->startOfWeek(Carbon::MONDAY), $today->copy()->endOfDay()],
            'mensuel' => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
            'annuel' => [$today->copy()->startOfYear(), $today->copy()->endOfDay()],
        ];

        $data = [];
        foreach ($periods as $key => [$start, $end]) {
            $startDate = $start->toDateString();
            $endDate = $end->toDateString();

            $entriesQty = (float) DB::table($this->detail_livraison)
                ->join($this->livraison, 'l.id', '=', 'dl.livraison_id')
                ->where('l.entreprise_id', $entrepriseId)
                ->whereBetween('l.date_livraison', [$startDate, $endDate])
                ->sum('dl.quantite');

            $entriesValue = (float) DB::table($this->detail_livraison)
                ->join($this->livraison, 'l.id', '=', 'dl.livraison_id')
                ->where('l.entreprise_id', $entrepriseId)
                ->whereBetween('l.date_livraison', [$startDate, $endDate])
                ->selectRaw('COALESCE(SUM(dl.quantite * dl.prix_achat_unitaire), 0) as v')
                ->value('v');

            $entriesArticleCount = (int) DB::table($this->detail_livraison)
                ->join($this->livraison, 'l.id', '=', 'dl.livraison_id')
                ->where('l.entreprise_id', $entrepriseId)
                ->whereBetween('l.date_livraison', [$startDate, $endDate])
                ->distinct('dl.article_id')
                ->count('dl.article_id');

            $entriesArticles = DB::table($this->detail_livraison)
                ->join($this->livraison, 'l.id', '=', 'dl.livraison_id')
                ->join('articles as a', 'a.id', '=', 'dl.article_id')
                ->where('l.entreprise_id', $entrepriseId)
                ->whereBetween('l.date_livraison', [$startDate, $endDate])
                ->groupBy('a.id', 'a.code', 'a.nom', 'a.unite_mesure')
                ->orderByDesc(DB::raw('SUM(dl.quantite)'))
                ->limit(10)
                ->selectRaw(
                    'a.id as article_id, a.code as article_code, a.nom as article_nom, a.unite_mesure as unite_mesure, ' .
                    'COALESCE(SUM(dl.quantite), 0) as quantite, ' .
                    'COALESCE(SUM(dl.quantite * dl.prix_achat_unitaire), 0) as montant'
                )
                ->get()
                ->map(function ($row) {
                    $row->quantite = (float) ($row->quantite ?? 0);
                    $row->montant = (float) ($row->montant ?? 0);
                    return $row;
                });

            $salesQty = (float) DB::table($this->detail_vente)
                ->join($this->vente, 'v.id', '=', 'dv.vente_id')
                ->where('v.entreprise_id', $entrepriseId)
                ->where('v.statut', 'validee')
                ->whereBetween('v.date_vente', [$startDate, $endDate])
                ->sum('dv.quantite');

            $salesValue = (float) DB::table($this->detail_vente)
                ->join($this->vente, 'v.id', '=', 'dv.vente_id')
                ->where('v.entreprise_id', $entrepriseId)
                ->where('v.statut', 'validee')
                ->whereBetween('v.date_vente', [$startDate, $endDate])
                ->selectRaw('COALESCE(SUM(dv.quantite * (dv.prix_vente_unitaire - COALESCE(dv.remise, 0))), 0) as v')
                ->value('v');

            $salesArticleCount = (int) DB::table($this->detail_vente)
                ->join($this->vente, 'v.id', '=', 'dv.vente_id')
                ->where('v.entreprise_id', $entrepriseId)
                ->where('v.statut', 'validee')
                ->whereBetween('v.date_vente', [$startDate, $endDate])
                ->distinct('dv.article_id')
                ->count('dv.article_id');

            $salesArticles = DB::table($this->detail_vente)
                ->join($this->vente, 'v.id', '=', 'dv.vente_id')
                ->join('articles as a', 'a.id', '=', 'dv.article_id')
                ->where('v.entreprise_id', $entrepriseId)
                ->where('v.statut', 'validee')
                ->whereBetween('v.date_vente', [$startDate, $endDate])
                ->groupBy('a.id', 'a.code', 'a.nom', 'a.unite_mesure')
                ->orderByDesc(DB::raw('SUM(dv.quantite)'))
                ->limit(10)
                ->selectRaw(
                    'a.id as article_id, a.code as article_code, a.nom as article_nom, a.unite_mesure as unite_mesure, ' .
                    'COALESCE(SUM(dv.quantite), 0) as quantite, ' .
                    'COALESCE(SUM(dv.quantite * (dv.prix_vente_unitaire - COALESCE(dv.remise, 0))), 0) as montant'
                )
                ->get()
                ->map(function ($row) {
                    $row->quantite = (float) ($row->quantite ?? 0);
                    $row->montant = (float) ($row->montant ?? 0);
                    return $row;
                });

            $data[$key] = [
                'debut' => $startDate,
                'fin' => $endDate,
                'entrees_articles_quantite' => $entriesQty,
                'entrees_articles_references' => $entriesArticleCount,
                'sorties_articles_quantite' => $salesQty,
                'sorties_articles_references' => $salesArticleCount,
                'achats_total' => $entriesValue,
                'ventes_total' => $salesValue,
                'marge_brute_estimee' => $salesValue - $entriesValue,
                'entrees_articles' => $entriesArticles,
                'sorties_articles' => $salesArticles,
            ];
        }

        $stockQty = (float) DB::table('articles')
            ->where('entreprise_id', $entrepriseId)
            ->sum('quantite_stock');

        $stockReferences = (int) DB::table('articles')
            ->where('entreprise_id', $entrepriseId)
            ->count();

        return response()->json([
            'entreprise_id' => $entrepriseId,
            'etat_stock_courant' => [
                'references' => $stockReferences,
                'quantite_totale' => $stockQty,
            ],
            'periodes' => $data,
        ]);
    }
}
