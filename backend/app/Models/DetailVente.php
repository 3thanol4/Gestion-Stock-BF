<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailVente extends Model
{
    protected $table = 'detail_ventes';

    protected $fillable = [
        'vente_id',
        'article_id',
        'quantite',
        'unite_mesure',
        'prix_vente_unitaire',
        'remise',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
            'prix_vente_unitaire' => 'decimal:2',
            'remise' => 'decimal:2',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
