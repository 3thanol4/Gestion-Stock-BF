<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailLivraison extends Model
{
    protected $table = 'detail_livraisons';

    protected $fillable = [
        'livraison_id',
        'article_id',
        'quantite',
        'unite_mesure',
        'prix_achat_unitaire',
        'prix_vente_unitaire',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
            'prix_achat_unitaire' => 'decimal:2',
            'prix_vente_unitaire' => 'decimal:2',
        ];
    }

    public function livraison(): BelongsTo
    {
        return $this->belongsTo(Livraison::class, 'livraison_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
