<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer les rôles
        \Spatie\Permission\Models\Role::create(['name' => 'administrateur_plateforme']);
        \Spatie\Permission\Models\Role::create(['name' => 'administrateur_entreprise']);
        \Spatie\Permission\Models\Role::create(['name' => 'gestionnaire_stock']);
        \Spatie\Permission\Models\Role::create(['name' => 'vendeur']);

        // Créer une entreprise par défaut (Optionnelle pour la plateforme Admin, mais bonne pour le test)
        $entreprise = \App\Models\Entreprise::create([
            'nom' => 'Ma Super Entreprise',
            'nom_base_donnee' => 'bdd_entreprise_1',
        ]);

        // Créer le super admin (qui gère la plateforme, n'a pas forcément d'entreprise assignée)
        $superAdmin = User::create([
            'name' => 'Admin Plateforme',
            'username' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('motdepasse'),
            'entreprise_id' => null, // Pas d'entreprise, il supervise
        ]);
        $superAdmin->assignRole('administrateur_plateforme');

        // Créer un admin entreprise
        $adminEntreprise = User::create([
            'name' => 'Admin Entreprise',
            'username' => 'admin_ent',
            'email' => 'admin_ent@admin.com',
            'password' => Hash::make('motdepasse'),
            'entreprise_id' => $entreprise->id, // Assigné
        ]);
        $adminEntreprise->assignRole('administrateur_entreprise');
    }
}
