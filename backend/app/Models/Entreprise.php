<?php

namespace App\Models;

use App\Support\EntityCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entreprise extends Model
{
    protected $fillable = [
        'code',
        'nom',
        'logo',
        'nom_base_donnee',
    ];

    protected static function booted(): void
    {
        static::creating(function (Entreprise $e) {
            if (empty($e->code)) {
                $e->code = EntityCode::forNewEntreprise();
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Categorie::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
