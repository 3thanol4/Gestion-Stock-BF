<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vente extends Model
{
    protected $fillable = [
        'entreprise_id',
        'user_id',
        'code',
        'date_vente',
        'client_nom',
        'client_contact',
        'montant_total',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_vente' => 'date',
            'montant_total' => 'decimal:2',
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
        return $this->hasMany(DetailVente::class, 'vente_id');
    }
}
