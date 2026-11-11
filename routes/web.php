<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScanQrController;
use App\Livewire\Socios\ListarSocios;
use App\Livewire\Socios\CrearSocio;
use App\Livewire\Socios\EditarSocio;
use App\Livewire\Socios\VerSocio;
use App\Livewire\Membresias\ListarMembresias;

Route::view('/', 'welcome');

// Rutas protegidas por autenticación
Route::middleware(['auth'])->group(function () {
    // Dashboard principal usando el nuevo componente Livewire
    Route::get('dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    Route::get('membresias', ListarMembresias::class)->name('membresias.index');
    
    // Perfil del operador
    Route::view('profile', 'profile')->name('profile');

    // Registro de Socios (Accesible por Dueña y Recepcionista)
    Route::get('socios/crear', CrearSocio::class)->name('socios.create');

    // Operaciones exclusivas de la Dueña / Administradora
    Route::middleware(['rol:admin'])->group(function () {
        Route::get('socios/{socio}/editar', EditarSocio::class)->name('socios.edit');

        // Gestión de Operadores
        Route::get('operadores', \App\Livewire\Operadores\ListarOperadores::class)->name('operadores.index');
        Route::get('operadores/crear', \App\Livewire\Operadores\CrearOperador::class)->name('operadores.create');
        Route::get('operadores/{user}/editar', \App\Livewire\Operadores\EditarOperador::class)->name('operadores.edit');

        // CRUD de Aptos Físicos: Escritura (Solo Administradores)
        Route::get('aptos-fisicos/{aptoFisico}/editar', \App\Livewire\AptosFisicos\EditarAptoFisico::class)->name('aptos-fisicos.edit');

        // CRUD de Disciplinas y Grupos: Escritura (Solo Dueña / Administradora)
        Route::get('disciplinas/crear', \App\Livewire\Disciplinas\CrearDisciplina::class)->name('disciplinas.create');
        Route::get('disciplinas/{disciplina}/editar', \App\Livewire\Disciplinas\EditarDisciplina::class)->name('disciplinas.edit');

        Route::get('grupos/crear', \App\Livewire\Grupos\CrearGrupo::class)->name('grupos.create');
        Route::get('grupos/{grupo}/editar', \App\Livewire\Grupos\EditarGrupo::class)->name('grupos.edit');
    });

    // CRUD de Socios: Lectura (Accesible por ambos roles)
    Route::get('socios', ListarSocios::class)->name('socios.index');
    Route::get('socios/{socio}', VerSocio::class)->name('socios.show');

    // CRUD de Disciplinas y Grupos: Lectura (Accesible por ambos roles)
    Route::get('disciplinas', \App\Livewire\Disciplinas\ListarDisciplinas::class)->name('disciplinas.index');
    Route::get('grupos', \App\Livewire\Grupos\ListarGrupos::class)->name('grupos.index');

    // Gestión de Inscripciones, Aprobaciones y Cupos (Accesible por ambos roles)
    Route::get('inscripciones', \App\Livewire\Inscripciones\ListarInscripciones::class)->name('inscripciones.index');
    Route::get('inscripciones/crear', \App\Livewire\Inscripciones\CrearInscripcion::class)->name('inscripciones.create');

    // CRUD de Aptos Físicos: Lectura y Carga (Accesible por ambos roles)
    Route::get('aptos-fisicos', \App\Livewire\AptosFisicos\ListarAptosFisicos::class)->name('aptos-fisicos.index');
    Route::get('socios/{socio}/aptos-fisicos/crear', \App\Livewire\AptosFisicos\CrearAptoFisico::class)->name('aptos-fisicos.create');
    Route::get('aptos-fisicos/{aptoFisico}/descargar', [\App\Http\Controllers\AptoFisicoController::class, 'descargar'])->name('aptos-fisicos.download');

    // CRUD de Pagos: Lectura y Carga (Accesible por ambos roles) – Fase 5
    Route::get('pagos', \App\Livewire\Pagos\ListarPagos::class)->name('pagos.index');
    Route::get('socios/{socio}/pagos/crear', \App\Livewire\Pagos\CrearPago::class)->name('pagos.create');
    Route::get('pagos/crear', \App\Livewire\Pagos\CrearPago::class)->name('pagos.create-general');

    // Coberturas por socio: consulta de estado económico de cada inscripción (Fase 5)
    Route::get('socios/{socio}/coberturas', \App\Livewire\Pagos\CoberturasSocio::class)->name('pagos.coberturas-socio');

    // Control de Ingreso y Salida (Accesible por ambos roles)
    Route::get('control-acceso', \App\Livewire\ControlAcceso::class)->name('control-acceso');

    // Historial de Asistencias (Accesible por ambos roles)
    Route::get('asistencias', \App\Livewire\Asistencias\ListarAsistencias::class)->name('asistencias.index');
    Route::get('solicitudes', \App\Livewire\Solicitudes\GestionarSolicitudes::class)->name('solicitudes.index');
    Route::get('auditoria', \App\Livewire\Auditoria\PanelAuditoria::class)->name('auditoria.index');

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
Route::get('/terminal/scan/{token}', [ScanQrController::class, 'scan'])->middleware('throttle:30,1')->name('terminal.scan');
Route::post('/terminal/scan/ingreso', [ScanQrController::class, 'ingresar'])->middleware('throttle:8,1')->name('terminal.scan.ingreso');
Route::post('/terminal/scan/salida', [ScanQrController::class, 'salir'])->middleware('throttle:8,1')->name('terminal.scan.salida');

// --- RUTAS DE PRUEBA DE ROLES ---
Route::get('solo-admin', function () {
    return 'Hola Administrador, esta es una ruta protegida de prueba.';
})->middleware(['auth', 'rol:admin']);

Route::get('solo-recepcionista', function () {
    return 'Hola Recepcionista, esta es una ruta protegida de prueba.';
})->middleware(['auth', 'rol:recepcionista']);

require __DIR__.'/auth.php';
