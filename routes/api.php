<?php

use App\Http\Controllers\Administration\FilmController;
use App\Http\Controllers\Administration\SalleController;
use App\Http\Controllers\Administration\SeanceController;
use App\Http\Controllers\Comptes\AuthentificationController;
use App\Http\Controllers\Comptes\MotDePasseController;
use App\Http\Controllers\Comptes\ProfilController;
use App\Http\Controllers\Programmation\FilmAlAfficheController;
use App\Http\Controllers\Programmation\SeancePubliqueController;
use App\Http\Controllers\Reservations\PlanDeSalleController;
use App\Http\Controllers\Reservations\ReservationController;
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

// Consulter les films à l'affiche, sans compte
Route::prefix('films')->name('films.')->group(function (): void {
    Route::get('/', [FilmAlAfficheController::class, 'index'])->name('index');
    Route::get('{film}', [FilmAlAfficheController::class, 'show'])->whereNumber('film')->name('show');
});

// Consulter les séances et les places restantes, sans compte
Route::get('seances', [SeancePubliqueController::class, 'index'])->name('seances.index');

// Choisir ses places sur le plan de salle: le plan est public, le blocage demande un compte spectateur.
Route::get('seances/{seance}/plan', [PlanDeSalleController::class, 'show'])->whereNumber('seance')->name('seances.plan');
Route::middleware('auth:api')->group(function (): void {
    Route::post('seances/{seance}/reservations', [ReservationController::class, 'store'])
        ->whereNumber('seance')->middleware('role:spectateur')->name('reservations.store');
    Route::get('reservations/{reservation}', [ReservationController::class, 'show'])->whereNumber('reservation')->name('reservations.show');
    Route::delete('reservations/{reservation}', [ReservationController::class, 'destroy'])->whereNumber('reservation')->name('reservations.destroy');
});

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

        // Gérer ses données personnelles
        Route::put('moi', [ProfilController::class, 'update'])->name('moi.modifier');
        Route::delete('moi', [ProfilController::class, 'destroy'])->middleware('role:spectateur')->name('moi.supprimer');
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

        // Gérer les salles et les séances
        Route::get('salles', [SalleController::class, 'index'])->name('salles.index');
        Route::get('seances', [SeanceController::class, 'index'])->name('seances.index');
        Route::post('seances', [SeanceController::class, 'store'])->name('seances.store');
        Route::get('seances/{seance}', [SeanceController::class, 'show'])->name('seances.show');
        Route::put('seances/{seance}', [SeanceController::class, 'update'])->name('seances.update');
        Route::delete('seances/{seance}', [SeanceController::class, 'destroy'])->name('seances.destroy');
        Route::post('seances/{seance}/publier', [SeanceController::class, 'publier'])->name('seances.publier');
        Route::post('seances/{seance}/depublier', [SeanceController::class, 'depublier'])->name('seances.depublier');
    });
});
