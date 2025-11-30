<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InformacionPersonalDController;
use App\Http\Controllers\InformacionPersonalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

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
    Route::get('fotografia/{ci}', [InformacionPersonalController::class, 'getFotografia2']);
    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('getdocentes', [InformacionPersonalDController::class, 'getdocentes']);
        Route::get('estudiantesfoto', [InformacionPersonalController::class, 'estudiantesfoto']);
        Route::get('descargarfotosmasiva', [InformacionPersonalController::class, 'descargarFotosMasiva']);
    });
});
