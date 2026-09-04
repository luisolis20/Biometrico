<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InformacionPersonalDController;
use App\Http\Controllers\InformacionPersonalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CarreraController;
use App\Http\Controllers\HikcentralController;
use App\Http\Controllers\PeriodoLectivoController;
use App\Http\Controllers\Asistencia_empleadoController;



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
    Route::get('fotografia-pre-est/{ci}', [InformacionPersonalController::class, 'getPreFotografia2'])->middleware('throttle:10000,1');
    Route::get('fotografiaHK/{ci}', [InformacionPersonalController::class, 'getFotografiaHC'])->middleware('throttle:10000,1');
    Route::get('fotografiadoc/{ci}', [InformacionPersonalDController::class, 'getFotografia'])->middleware('throttle:5000,1');
    Route::get('getindivDoc/{ci}', [InformacionPersonalDController::class, 'getDocenteByCI'])->middleware('throttle:5000,1');
    Route::get('getindivDocUTLVTE/{ci}', [InformacionPersonalDController::class, 'getPersonaUTLVTEByCI'])->middleware('throttle:5000,1');
    Route::get('getindivEst/{ci}', [InformacionPersonalController::class, 'getEstudianteByCI'])->middleware('throttle:10000,1');
    Route::get('getindivEst-pre-est/{ci}', [InformacionPersonalController::class, 'getEstudiantesPreCI'])->middleware('throttle:10000,1');
    Route::get('gethick/{ci}', [HikcentralController::class, 'testPhotoBase64'])->middleware('throttle:10000,1');
    Route::get('gethick-pre-est/{ci}', [HikcentralController::class, 'testPhotoPreEstBase64'])->middleware('throttle:10000,1');
    Route::get('get-periodos-rec', [PeriodoLectivoController::class, 'getActivos'])->middleware('throttle:10000,1');
    Route::get('devices', [HikcentralController::class, 'getAllAcsDevices'])->middleware('throttle:10000,1');
    Route::get('search-devices/search', [HikcentralController::class, 'searchAcsDevice'])->middleware('throttle:10000,1');
    Route::get('real-time-events', [HikcentralController::class, 'getRealTimeEvents'])->middleware('throttle:10000,1');
    Route::get('asistencia', [HikcentralController::class, 'getAllAsistence'])->middleware('throttle:10000,1');
    Route::post('attendance-report', [HikcentralController::class, 'getAttendanceReport'])->middleware('throttle:10000,1');
    Route::post('attendance-sync', [Asistencia_empleadoController::class, 'syncAttendance'])->middleware('throttle:10000,1');
    Route::post('check-local-attendance', [Asistencia_empleadoController::class, 'checkLocal'])->middleware('throttle:10000,1');
    Route::get('get-access-levels', [HikcentralController::class, 'getAllAccessLevels'])->middleware('throttle:10000,1');
    Route::get('get-access-person', [HikcentralController::class, 'getAccesLevelGymPerson'])->middleware('throttle:10000,1');
    


    Route::get('getperson/{ci}', [HikcentralController::class, 'checkHikStatus'])->middleware('throttle:1000000,1');
    Route::get('getperson-est/{ci}', [HikcentralController::class, 'checkHikStatusEst'])->middleware('throttle:1000000,1');
    Route::get('getperson-pre-est/{ci}', [HikcentralController::class, 'checkHikStatusPreEst'])->middleware('throttle:1000000,1');
    Route::get('compare-hikdoc/{ci}', [HikcentralController::class, 'compararFotosHCKWithDBDOC'])->middleware('throttle:20000,1');
    Route::get('compare-hikdoc-est/{ci}', [HikcentralController::class, 'compararFotosHCKWithDBEstudiante'])->middleware('throttle:20000,1');
    Route::get('compare-hikdoc-pre-est/{ci}', [HikcentralController::class, 'compareFotosHCKWithDBPreEstudiante'])->middleware('throttle:20000,1');
    Route::post('sync-hikcentral/{ci}', [HikcentralController::class, 'syncToHikCentral'])->middleware('throttle:20000,1');
    Route::post('sync-hikcentral-pre-est/{ci}', [HikcentralController::class, 'syncToHikCentralPreEst'])->middleware('throttle:20000,1');
    Route::post('sync-hikdoc/{ci}', [HikcentralController::class, 'syncToHikCentralEst'])->middleware('throttle:20000,1');
    Route::post('sync-hikdoc-update/{ci}', [HikcentralController::class, 'syncToHikCentralUpdateEst'])->middleware('throttle:20000,1');
    Route::post('sync-hikdoc-update-pre-est/{ci}', [HikcentralController::class, 'syncToHikCentralUpdatePreEst'])->middleware('throttle:20000,1');
    Route::post('sync-hikdupdatedoce/{ci}', [HikcentralController::class, 'syncToHikUpdateCentral'])->middleware('throttle:20000,1'); // Estudiantes individuales
    Route::post('sync-hikdoc-est-id/{ci}', [HikcentralController::class, 'syncToHikCentralIndvEst'])->middleware('throttle:20000,1');
    Route::post('add_level_access_gym', [HikcentralController::class, 'ADDAccesLevelGymPerson'])->middleware('throttle:20000,1');
    Route::post('sync-hikdoc-est-id-pre-est/{ci}', [HikcentralController::class, 'syncToHikCentralIndPreEst'])->middleware('throttle:20000,1');
    Route::get('clear-cache/{ci}', [HikcentralController::class, 'clearDocenteCache'])->middleware('throttle:10000,1');
    Route::get('/get-pending-sync', [HikcentralController::class, 'getPendingSync'])->middleware('throttle:10000,1');
    Route::get('/get-pending-sync-est', [HikcentralController::class, 'getPendingSyncEst'])->middleware('throttle:10000,1');
    Route::get('/get-pending-sync-pre-est', [HikcentralController::class, 'getPendingSyncPreEst'])->middleware('throttle:10000,1');
    Route::get('carrerasList', [CarreraController::class, 'carrerasconsula'])->middleware('throttle:10000,1');
    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('getdocentes', [InformacionPersonalDController::class, 'getdocentes'])->middleware('throttle:10000,1');
        Route::get('getpersonal-utlvte', [InformacionPersonalDController::class, 'getPersonalUTLVTE'])->middleware('throttle:10000,1');
        Route::get('estudiantesfoto', [InformacionPersonalController::class, 'estudiantesfoto'])->middleware('throttle:10000,1');
        Route::get('estudiantesfoto-pre-est', [InformacionPersonalController::class, 'getEstudiantesPre'])->middleware('throttle:10000,1');
        Route::get('estudiantes-foto-lista', [InformacionPersonalController::class, 'listarEstudiantesConFoto']);
        Route::get('descargarfotosmasiva', [InformacionPersonalController::class, 'descargarFotosMasiva'])->middleware('throttle:10000,1');
        Route::get('descargarfotosmasivadoc', [InformacionPersonalDController::class, 'descargarFotosMasiva'])->middleware('throttle:5000,1');
    });
});
