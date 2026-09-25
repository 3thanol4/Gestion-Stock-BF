<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livraison extends Model
{
    protected $fillable = [
        'entreprise_id',
        'user_id',
        'code',
        'date_livraison',
        'fournisseur_nom',
        'fournisseur_contact',
    ];

    protected function casts(): array
    {
        return [
            'date_livraison' => 'date',
        ];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(DetailLivraison::class, 'livraison_id');
    }
}
