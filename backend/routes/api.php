<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EntrepriseController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\VenteController;
use App\Http\Controllers\ReportController;

Route::post('/token', [AuthController::class, 'login'])->name('token');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return clone $request->user()->load('roles', 'entreprise');
    });

    // Utilisateurs
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/toggle-statut', [UserController::class, 'toggleStatut']);

    // Entreprises
    Route::get('/entreprises', [EntrepriseController::class, 'index']);

    // Catégories
    Route::get('/categories', [CategorieController::class, 'index']);
    Route::post('/categories', [CategorieController::class, 'store']);
    Route::put('/categories/{id}', [CategorieController::class, 'update']);
    Route::delete('/categories/{id}', [CategorieController::class, 'destroy']);

    // Articles
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

    // Livraisons (entrées de stock)
    Route::get('/livraisons', [LivraisonController::class, 'index']);
    Route::post('/livraisons', [LivraisonController::class, 'store']);

    // Ventes (sorties de stock)
    Route::get('/ventes', [VenteController::class, 'index']);
    Route::post('/ventes', [VenteController::class, 'store']);
    Route::patch('/ventes/{id}/annuler', [VenteController::class, 'annuler']);

    // Reporting (gestionnaire + admins)
    Route::get('/reports/financial-state', [ReportController::class, 'financialState']);
});
