<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $fillable = [
        'entreprise_id',
        'categorie_id',
        'code',
        'nom',
        'unite_mesure',
        'quantite_stock',
    ];

    protected function casts(): array
    {
        return [
            'quantite_stock' => 'decimal:3',
        ];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    public function dernierPrixVente(): ?float
    {
        $d = DetailLivraison::query()
            ->where('article_id', $this->id)
            ->orderByDesc('id')
            ->value('prix_vente_unitaire');

        return $d !== null ? (float) $d : null;
    }

    public function dernierPrixAchat(): ?float
    {
        $d = DetailLivraison::query()
            ->where('article_id', $this->id)
            ->orderByDesc('id')
            ->value('prix_achat_unitaire');

        return $d !== null ? (float) $d : null;
    }
}
