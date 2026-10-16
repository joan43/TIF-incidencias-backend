<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IncidentController;
use Illuminate\Support\Facades\Route;

// Login público. El resto de la API exige un token Bearer de Laravel Sanctum.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Categorías precargadas por el seeder. Se mantiene alias en inglés por comodidad.
    Route::get('/categorias', [IncidentController::class, 'categories']);
    Route::get('/categories', [IncidentController::class, 'categories']);

    Route::get('/incidencias', [IncidentController::class, 'index']);
    Route::post('/incidencias', [IncidentController::class, 'store']);
    Route::get('/incidencias/{id}', [IncidentController::class, 'show'])->whereNumber('id');
    Route::put('/incidencias/{id}/tomar', [IncidentController::class, 'tomar'])->whereNumber('id');
    Route::put('/incidencias/{id}/resolver', [IncidentController::class, 'resolver'])->whereNumber('id');

    Route::get('/incidencias/{id}/comentarios', [IncidentController::class, 'getComments'])->whereNumber('id');
    Route::post('/incidencias/{id}/comentarios', [IncidentController::class, 'addComment'])->whereNumber('id');
});

