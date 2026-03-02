<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\SimpleTokenAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public — login
Route::post('/login', [AuthController::class, 'login']);

// Protected — require valid Bearer token
Route::middleware(SimpleTokenAuth::class)->group(function () {
    Route::get('/tasks',         [TaskController::class, 'index']);
    Route::post('/tasks',        [TaskController::class, 'store']);
    Route::put('/tasks/{id}',    [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
});