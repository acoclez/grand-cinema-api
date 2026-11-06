<?php

use App\Http\Controllers\Administration\FilmController;
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

// Back-office: réservé aux comptes connectés avec le rôle requis.
Route::prefix('admin')->name('admin.')->middleware('auth:api')->group(function (): void {
    // Gérer les films
    Route::middleware('role:administrateur')->group(function (): void {
        Route::get('films', [FilmController::class, 'index'])->name('films.index');
        Route::post('films', [FilmController::class, 'store'])->name('films.store');
        Route::get('films/{film}', [FilmController::class, 'show'])->name('films.show');
        Route::put('films/{film}', [FilmController::class, 'update'])->name('films.update');
        Route::post('films/{film}/archiver', [FilmController::class, 'archiver'])->name('films.archiver');
        Route::post('films/{film}/restaurer', [FilmController::class, 'restaurer'])->name('films.restaurer');
    });
});
