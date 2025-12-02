<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InformacionPersonalDController;
use App\Http\Controllers\InformacionPersonalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CarreraController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::prefix('biometrico')->group(function () {
    Route::apiResource("users", UserController::class);
    Route::post('login', [AuthController::class, 'login']);
    Route::get('fotografia/{ci}', [InformacionPersonalController::class, 'getFotografia2'])->middleware('throttle:5000,1');
    Route::get('fotografiadoc/{ci}', [InformacionPersonalDController::class, 'getFotografia'])->middleware('throttle:5000,1');
    Route::get('carrerasList', [CarreraController::class, 'carrerasconsula'])->middleware('throttle:5000,1');
    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('getdocentes', [InformacionPersonalDController::class, 'getdocentes'])->middleware('throttle:5000,1');
        Route::get('estudiantesfoto', [InformacionPersonalController::class, 'estudiantesfoto'])->middleware('throttle:5000,1');
        Route::get('estudiantes-foto-lista', [InformacionPersonalController::class, 'listarEstudiantesConFoto']);
        Route::get('comparar-foto/{ci}', [InformacionPersonalController::class, 'compararFotos'])->middleware('throttle:10000,1');
        Route::get('comparar-fotodoc/{ci}', [InformacionPersonalDController::class, 'compararFotos'])->middleware('throttle:10000,1');
        Route::get('descargarfotosmasiva', [InformacionPersonalController::class, 'descargarFotosMasiva'])->middleware('throttle:5000,1');
        Route::get('descargarfotosmasivadoc', [InformacionPersonalDController::class, 'descargarFotosMasiva'])->middleware('throttle:5000,1');
    });
});
