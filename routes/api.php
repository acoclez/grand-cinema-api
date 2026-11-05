<?php

use App\Http\Controllers\Comptes\AuthentificationController;
use App\Http\Controllers\Comptes\MotDePasseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de l'API
|--------------------------------------------------------------------------
| Toutes les routes sont préfixées par /api. Chaque user story ajoute ses
| routes dans son propre groupe, documenté automatiquement par Scramble.
*/

/**
 * Etat de l'API
 *
 * Utilisée par l'hébergeur pour vérifier que l'API répond.
 *
 * @unauthenticated
 */
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Créer un compte et se connecter
Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::middleware('throttle:comptes')->group(function (): void {
        Route::post('inscription', [AuthentificationController::class, 'inscription'])->name('inscription');
        Route::post('connexion', [AuthentificationController::class, 'connexion'])->name('connexion');
        Route::post('mot-de-passe-oublie', [MotDePasseController::class, 'oublie'])->name('mot-de-passe-oublie');
        Route::post('reinitialiser-mot-de-passe', [MotDePasseController::class, 'reinitialiser'])->name('reinitialiser-mot-de-passe');
    });

    Route::middleware('auth:api')->group(function (): void {
        Route::get('moi', [AuthentificationController::class, 'moi'])->name('moi');
        Route::post('rafraichir', [AuthentificationController::class, 'rafraichir'])->name('rafraichir');
        Route::post('deconnexion', [AuthentificationController::class, 'deconnexion'])->name('deconnexion');
    });
});
