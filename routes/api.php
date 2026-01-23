<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InformacionPersonalDController;
use App\Http\Controllers\InformacionPersonalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CarreraController;
use App\Http\Controllers\HikcentralController;


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
    Route::get('fotografia/{ci}', [InformacionPersonalController::class, 'getFotografia2'])->middleware('throttle:10000,1');
    Route::get('fotografiaHK/{ci}', [InformacionPersonalController::class, 'getFotografiaHC'])->middleware('throttle:10000,1');
    Route::get('fotografiadoc/{ci}', [InformacionPersonalDController::class, 'getFotografia'])->middleware('throttle:5000,1');
    Route::get('gethick/{ci}', [HikcentralController::class, 'testPhotoBase64'])->middleware('throttle:10000,1');
    Route::get('getperson/{ci}', [HikcentralController::class, 'checkHikStatus'])->middleware('throttle:1000000,1');
    Route::get('compare-hikdoc/{ci}', [HikcentralController::class, 'compararFotosHCKWithDBDOC'])->middleware('throttle:20000,1');
    Route::post('sync-hikcentral/{ci}', [HikcentralController::class, 'syncToHikCentral'])->middleware('throttle:20000,1');
    Route::get('clear-cache/{ci}', [HikcentralController::class, 'clearDocenteCache'])->middleware('throttle:10000,1');
    Route::get('/get-pending-sync', [HikcentralController::class, 'getPendingSync'])->middleware('throttle:10000,1');
    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('carrerasList', [CarreraController::class, 'carrerasconsula'])->middleware('throttle:10000,1');
        Route::get('getdocentes', [InformacionPersonalDController::class, 'getdocentes'])->middleware('throttle:10000,1');
        Route::get('estudiantesfoto', [InformacionPersonalController::class, 'estudiantesfoto'])->middleware('throttle:10000,1');
        Route::get('estudiantes-foto-lista', [InformacionPersonalController::class, 'listarEstudiantesConFoto']);
        Route::get('descargarfotosmasiva', [InformacionPersonalController::class, 'descargarFotosMasiva'])->middleware('throttle:10000,1');
        Route::get('descargarfotosmasivadoc', [InformacionPersonalDController::class, 'descargarFotosMasiva'])->middleware('throttle:5000,1');
    });
});
