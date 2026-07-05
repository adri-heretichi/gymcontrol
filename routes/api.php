<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\SocioApiController;
use App\Http\Controllers\Api\MembresiaApiController;
use App\Http\Controllers\Api\AptoFisicoApiController;
use App\Http\Controllers\Api\PagoApiController;
use App\Http\Controllers\Api\AsistenciaApiController;
use App\Http\Controllers\Api\ControlAccesoApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se registran las rutas de la API para GymControl. Estas rutas son
| cargadas automáticamente por el framework con el prefijo /api.
|
*/

Route::prefix('v1')->group(function () {

    // --- RUTAS PÚBLICAS ---
    Route::post('auth/login', [AuthApiController::class, 'login'])->name('api.login');

    // --- RUTAS PROTEGIDAS POR SANCTUM ---
    Route::middleware(['auth:sanctum'])->group(function () {

        // Perfil del operador autenticado
        Route::post('auth/logout', [AuthApiController::class, 'logout'])->name('api.logout');
        Route::get('auth/me', [AuthApiController::class, 'me'])->name('api.me');

        // --- MÓDULO: SOCIOS ---
        // Lectura (Admin y Recepcionista)
        Route::get('socios', [SocioApiController::class, 'index'])->name('api.socios.index');
        Route::get('socios/{id}', [SocioApiController::class, 'show'])->name('api.socios.show');
        // Escritura y Baja Lógica (Solo Admin)
        Route::middleware(['rol:admin'])->group(function () {
            Route::post('socios', [SocioApiController::class, 'store'])->name('api.socios.store');
            Route::match(['put', 'patch'], 'socios/{id}', [SocioApiController::class, 'update'])->name('api.socios.update');
            Route::delete('socios/{id}', [SocioApiController::class, 'destroy'])->name('api.socios.destroy');
        });

        // --- MÓDULO: MEMBRESÍAS ---
        // Lectura (Admin y Recepcionista)
        Route::get('membresias', [MembresiaApiController::class, 'index'])->name('api.membresias.index');
        Route::get('membresias/{id}', [MembresiaApiController::class, 'show'])->name('api.membresias.show');
        // Escritura y Baja Lógica (Solo Admin)
        Route::middleware(['rol:admin'])->group(function () {
            Route::post('membresias', [MembresiaApiController::class, 'store'])->name('api.membresias.store');
            Route::match(['put', 'patch'], 'membresias/{id}', [MembresiaApiController::class, 'update'])->name('api.membresias.update');
            Route::delete('membresias/{id}', [MembresiaApiController::class, 'destroy'])->name('api.membresias.destroy');
        });

        // --- MÓDULO: APTOS FÍSICOS ---
        // Carga, listados y descargas (Admin y Recepcionista)
        Route::get('aptos-fisicos', [AptoFisicoApiController::class, 'index'])->name('api.aptos-fisicos.index');
        Route::get('aptos-fisicos/{id}', [AptoFisicoApiController::class, 'show'])->name('api.aptos-fisicos.show');
        Route::post('aptos-fisicos', [AptoFisicoApiController::class, 'store'])->name('api.aptos-fisicos.store');
        Route::get('aptos-fisicos/{id}/descargar', [AptoFisicoApiController::class, 'descargar'])->name('api.aptos-fisicos.download');

        // --- MÓDULO: PAGOS ---
        // Consulta y Cobros (Admin y Recepcionista)
        Route::get('pagos', [PagoApiController::class, 'index'])->name('api.pagos.index');
        Route::get('pagos/{id}', [PagoApiController::class, 'show'])->name('api.pagos.show');
        Route::post('pagos', [PagoApiController::class, 'store'])->name('api.pagos.store');
        // Reporte de Recaudación (Solo Admin)
        Route::get('pagos-recaudacion', [PagoApiController::class, 'recaudacion'])->middleware('rol:admin')->name('api.pagos.recaudacion');

        // --- MÓDULO: ASISTENCIAS Y CONTROL ---
        // Historial y marca manual (Admin y Recepcionista)
        Route::get('asistencias', [AsistenciaApiController::class, 'index'])->name('api.asistencias.index');
        Route::get('asistencias/{id}', [AsistenciaApiController::class, 'show'])->name('api.asistencias.show');
        Route::post('asistencias', [AsistenciaApiController::class, 'store'])->name('api.asistencias.store');
        // Edición de asistencia (Solo Admin)
        Route::match(['put', 'patch'], 'asistencias/{id}', [AsistenciaApiController::class, 'update'])->middleware('rol:admin')->name('api.asistencias.update');

        // Control de Acceso: Escáner QR / DNI
        Route::post('control-acceso/scan', [ControlAccesoApiController::class, 'scan'])->name('api.control-acceso.scan');
    });

});
