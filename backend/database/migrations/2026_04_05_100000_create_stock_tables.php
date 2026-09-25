<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique()->after('id');
        });

        DB::table('entreprises')->whereNull('code')->orderBy('id')->get()->each(function ($row) {
            DB::table('entreprises')->where('id', $row->id)->update([
                'code' => 'ENT-' . str_pad((string) $row->id, 5, '0', STR_PAD_LEFT),
            ]);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('nom');
            $table->timestamps();
            $table->unique(['entreprise_id', 'code']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('categorie_id')->constrained('categories')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('nom');
            $table->string('unite_mesure', 50);
            $table->decimal('quantite_stock', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['entreprise_id', 'code']);
        });

        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->date('date_livraison');
            $table->string('fournisseur_nom');
            $table->string('fournisseur_contact')->nullable();
            $table->timestamps();
            $table->unique(['entreprise_id', 'code']);
        });

        Schema::create('detail_livraisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livraison_id')->constrained('livraisons')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->decimal('quantite', 14, 3);
            $table->string('unite_mesure', 50);
            $table->decimal('prix_achat_unitaire', 14, 2);
            $table->decimal('prix_vente_unitaire', 14, 2);
            $table->timestamps();
        });

        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->date('date_vente');
            $table->string('client_nom')->nullable();
            $table->string('client_contact')->nullable();
            $table->decimal('montant_total', 14, 2)->default(0);
            $table->string('statut', 32)->default('validee');
            $table->timestamps();
            $table->unique(['entreprise_id', 'code']);
        });

        Schema::create('detail_ventes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->decimal('quantite', 14, 3);
            $table->string('unite_mesure', 50);
            $table->decimal('prix_vente_unitaire', 14, 2);
            $table->decimal('remise', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_ventes');
        Schema::dropIfExists('ventes');
        Schema::dropIfExists('detail_livraisons');
        Schema::dropIfExists('livraisons');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('categories');

        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
