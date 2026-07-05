<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScanQrController;
use App\Livewire\Socios\ListarSocios;
use App\Livewire\Socios\CrearSocio;
use App\Livewire\Socios\EditarSocio;
use App\Livewire\Socios\VerSocio;

Route::view('/', 'welcome');

// Rutas protegidas por autenticación
Route::middleware(['auth'])->group(function () {
    // Dashboard principal usando el nuevo componente Livewire
    Route::get('dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    
    // Perfil del operador
    Route::view('profile', 'profile')->name('profile');

    // CRUD de Socios: Escritura (Solo accesible por Administradores - Opción B)
    Route::middleware(['rol:admin'])->group(function () {
        Route::get('socios/crear', CrearSocio::class)->name('socios.create');
        Route::get('socios/{socio}/editar', EditarSocio::class)->name('socios.edit');

        // CRUD de Membresías: Escritura
        Route::get('membresias/crear', \App\Livewire\Membresias\CrearMembresia::class)->name('membresias.create');
        Route::get('membresias/{membresia}/editar', \App\Livewire\Membresias\EditarMembresia::class)->name('membresias.edit');

        // Gestión de Operadores
        Route::get('operadores', \App\Livewire\Operadores\ListarOperadores::class)->name('operadores.index');
        Route::get('operadores/crear', \App\Livewire\Operadores\CrearOperador::class)->name('operadores.create');
        Route::get('operadores/{user}/editar', \App\Livewire\Operadores\EditarOperador::class)->name('operadores.edit');

        // CRUD de Aptos Físicos: Escritura (Solo Administradores)
        Route::get('aptos-fisicos/{aptoFisico}/editar', \App\Livewire\AptosFisicos\EditarAptoFisico::class)->name('aptos-fisicos.edit');
    });

    // CRUD de Socios: Lectura (Accesible por ambos roles)
    Route::get('socios', ListarSocios::class)->name('socios.index');
    Route::get('socios/{socio}', VerSocio::class)->name('socios.show');

    // CRUD de Membresías: Lectura (Accesible por ambos roles)
    Route::get('membresias', \App\Livewire\Membresias\ListarMembresias::class)->name('membresias.index');

    // CRUD de Aptos Físicos: Lectura y Carga (Accesible por ambos roles)
    Route::get('aptos-fisicos', \App\Livewire\AptosFisicos\ListarAptosFisicos::class)->name('aptos-fisicos.index');
    Route::get('socios/{socio}/aptos-fisicos/crear', \App\Livewire\AptosFisicos\CrearAptoFisico::class)->name('aptos-fisicos.create');
    Route::get('aptos-fisicos/{aptoFisico}/descargar', [\App\Http\Controllers\AptoFisicoController::class, 'descargar'])->name('aptos-fisicos.download');

    // CRUD de Pagos: Lectura y Carga (Accesible por ambos roles)
    Route::get('pagos', \App\Livewire\Pagos\ListarPagos::class)->name('pagos.index');
    Route::get('socios/{socio}/pagos/crear', \App\Livewire\Pagos\CrearPago::class)->name('pagos.create');
    Route::get('pagos/crear', \App\Livewire\Pagos\CrearPago::class)->name('pagos.create-general');

    // Control de Ingreso y Salida (Accesible por ambos roles)
    Route::get('control-acceso', \App\Livewire\ControlAcceso::class)->name('control-acceso');

    // Historial de Asistencias (Accesible por ambos roles)
    Route::get('asistencias', \App\Livewire\Asistencias\ListarAsistencias::class)->name('asistencias.index');

    // Reportes PDF (Etapa 12)
    Route::get('socios/{socio}/reporte-pdf', [\App\Http\Controllers\ReporteController::class, 'fichaSocio'])->name('reportes.ficha-socio');
    Route::get('asistencias/reporte-pdf', [\App\Http\Controllers\ReporteController::class, 'asistencias'])->name('reportes.asistencias');
    Route::get('pagos/reporte-pdf', [\App\Http\Controllers\ReporteController::class, 'pagos'])->name('reportes.pagos')->middleware('rol:admin');

    // Código QR y Tarjeta PDF (Parte 2 y 3)
    Route::get('socios/{socio}/qr', [\App\Http\Controllers\QrController::class, 'show'])->name('socios.qr');
    Route::get('socios/{socio}/tarjeta-pdf', [\App\Http\Controllers\ReporteController::class, 'tarjeta'])->name('socios.tarjeta-pdf');
});

// Terminal Pública de Acceso (Parte 4)
Route::get('terminal', \App\Livewire\TerminalAcceso::class)->name('terminal')->middleware('red.local');
Route::get('/terminal/scan/{token}', [ScanQrController::class, 'scan'])->name('terminal.scan');

// --- RUTAS DE PRUEBA DE ROLES ---
Route::get('solo-admin', function () {
    return 'Hola Administrador, esta es una ruta protegida de prueba.';
})->middleware(['auth', 'rol:admin']);

Route::get('solo-recepcionista', function () {
    return 'Hola Recepcionista, esta es una ruta protegida de prueba.';
})->middleware(['auth', 'rol:recepcionista']);

require __DIR__.'/auth.php';
