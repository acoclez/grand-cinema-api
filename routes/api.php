<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de l'API
|--------------------------------------------------------------------------
| Toutes les routes sont préfixées par /api. Chaque user story ajoute ses
| routes dans son propre groupe, documenté automatiquement par Scramble.
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));
