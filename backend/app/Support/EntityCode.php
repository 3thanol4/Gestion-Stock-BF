<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class EntityCode
{
    public static function forEntreprise(string $table, int $entrepriseId, string $prefix): string
    {
        $pattern = $prefix . '-%';
        $last = DB::table($table)
            ->where('entreprise_id', $entrepriseId)
            ->where('code', 'like', $pattern)
            ->orderByDesc('id')
            ->value('code');

        $n = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $n = (int) $m[1] + 1;
        }

        return $prefix . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function forLivraison(int $entrepriseId): string
    {
        $year = date('Y');
        $prefix = 'LIV-' . $year . '-';
        $last = DB::table('livraisons')
            ->where('entreprise_id', $entrepriseId)
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('code');

        $n = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $n = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function forVente(int $entrepriseId): string
    {
        $year = date('Y');
        $prefix = 'VTE-' . $year . '-';
        $last = DB::table('ventes')
            ->where('entreprise_id', $entrepriseId)
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('code');

        $n = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $n = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function forNewEntreprise(): string
    {
        $last = DB::table('entreprises')
            ->where('code', 'like', 'ENT-%')
            ->orderByDesc('id')
            ->value('code');

        $n = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $n = (int) $m[1] + 1;
        }

        return 'ENT-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
