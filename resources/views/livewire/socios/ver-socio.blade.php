<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        
        <!-- Mensajes Flash de Retroalimentación -->
        @if (session()->has('message') || session()->has('mensaje'))
            <div class="p-4 bg-emerald-50 dark:bg-emerald-950/30 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-300 rounded-r-xl font-bold text-sm shadow-md animate-pulse">
                {{ session('message') ?? session('mensaje') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="p-4 bg-rose-50 dark:bg-rose-950/30 border-l-4 border-rose-500 text-rose-700 dark:text-rose-300 rounded-r-xl font-bold text-sm shadow-md">
                {{ session('error') }}
            </div>
        @endif
        
        <!-- Breadcrumbs & Encabezado Principal -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between pb-6 border-b border-slate-200 dark:border-slate-800 gap-4">
            <div>
                <a href="{{ route('socios.index') }}" class="inline-flex items-center text-sm font-bold text-indigo-700 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 transition-colors duration-150 mb-2 group">
                    <svg class="w-4 h-4 mr-1.5 transform group-hover:-translate-x-1 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Volver al Listado
                </a>
                <h2 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    Ficha del Socio
                </h2>
                <p class="text-sm font-semibold mt-1 text-slate-700 dark:text-slate-400">Consulta detallada de información personal, membresía, pagos y asistencias.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- Botón para Imprimir Tarjeta (Disponible para Admin y Recepcionista) -->
                <a href="{{ route('socios.tarjeta-pdf', $socio->id) }}" target="_blank" class="btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    <span>Imprimir Tarjeta</span>
                </a>

                <!-- Botón de Exportación PDF (Disponible para Admin y Recepcionista) -->
                <a href="{{ route('reportes.ficha-socio', $socio->id) }}" target="_blank" class="btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    <span>Exportar Ficha (PDF)</span>
                </a>

                @if(auth()->check() && auth()->user()->rol === 'admin')
                    <a href="{{ route('socios.edit', $socio->id) }}" class="btn-primary">
                        <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                        <span>Editar Ficha de Socio</span>
                    </a>
                @endif
            </div>
        </div>

        <!-- Grid de dos columnas -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- COLUMNA IZQUIERDA: Credencial Virtual y Códigos -->
            <div class="space-y-8">
                
                <!-- Credencial Digital Premium -->
                <div class="rounded-3xl shadow-2xl border border-slate-800 bg-gradient-to-br from-slate-900 to-indigo-950 text-white relative overflow-hidden group hover:shadow-indigo-950/20 hover:shadow-3xl transition-all duration-300">
                    <!-- Efecto de iluminación de fondo -->
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl group-hover:bg-indigo-500/20 transition-all duration-300"></div>
                    <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-purple-500/10 rounded-full blur-3xl group-hover:bg-purple-500/20 transition-all duration-300"></div>
                    
                    <!-- Línea superior de presencia -->
                    <div class="h-2 w-full {{ $estaPresente ? 'bg-emerald-400' : 'bg-slate-700' }} transition-colors duration-300"></div>
                    
                    <div class="p-8">
                        <!-- Cabecera de la credencial -->
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <span class="text-xs font-black tracking-widest text-indigo-400 uppercase">GYMCONTROL</span>
                                <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">CREDENCIAL VIRTUAL</span>
                            </div>
                            <!-- Badge de presencia premium -->
                            @if($estaPresente)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-2xs font-extrabold border bg-emerald-500/20 text-emerald-400 border-emerald-500/30 animate-pulse">
                                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-400"></span>
                                    EN SALA
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-2xs font-black border bg-rose-500/20 text-rose-400 border-rose-500/30">
                                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-rose-400"></span>
                                    AUSENTE
                                </span>
                            @endif
                        </div>

                        <!-- Foto / Avatar circular -->
                        <div class="flex flex-col items-center mt-6">
                            <div class="relative mb-4">
                                <div class="w-32 h-32 rounded-full flex items-center justify-center p-1.5 shadow-2xl bg-gradient-to-br from-indigo-500 to-purple-600">
                                    <!-- Iniciales en alta resolución -->
                                    <div class="w-full h-full rounded-full flex items-center justify-center border border-indigo-400/20 bg-slate-900">
                                        <span class="text-4xl font-black text-white tracking-wider">
                                            {{ $iniciales }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <h3 class="text-2xl font-black tracking-tight mt-2 text-white">
                                {{ $socio->nombre }} {{ $socio->apellido }}
                            </h3>
                            <p class="text-sm text-indigo-300 font-medium tracking-wide mt-1">DNI: {{ $socio->dni }}</p>
                        </div>

                        <!-- Separador -->
                        <div class="my-6 border-t border-slate-800/80"></div>

                        <!-- Detalles rápidos de estado y token -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400 font-semibold">Estado de cuenta:</span>
                                @if($socio->estado === 'activo')
                                    <span class="px-3 py-1 text-xs font-black bg-emerald-500 text-slate-950 rounded-full shadow-lg shadow-emerald-500/20">
                                        ACTIVO
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-black bg-rose-500 text-white rounded-full shadow-lg shadow-rose-500/20">
                                        INACTIVO
                                    </span>
                                @endif
                            </div>

                            <!-- Token de Acceso Teclado -->
                            <div class="border border-slate-800 p-4 rounded-2xl bg-slate-950">
                                <span class="block text-2xs font-bold uppercase tracking-widest text-center mb-1.5 text-slate-400">
                                    TOKEN PIN DE ACCESO
                                </span>
                                <code class="text-2xl font-mono font-black tracking-widest block text-center text-amber-400">
                                    {{ $socio->token }}
                                </code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Módulo QR de Acceso -->
                <div class="text-center">
                    @if(Storage::disk('local')->exists('private/qrs/qr_' . $socio->id . '.svg'))
                        <img src="{{ route('socios.qr', $socio) }}"
                             alt="QR de acceso"
                             class="mx-auto w-40 h-40">
                    @else
                        <p class="text-sm text-gray-400">QR no generado aún</p>
                    @endif
                    <button wire:click="regenerarQr"
                            class="mt-3 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                                   text-white text-sm rounded-lg">
                        Regenerar QR
                    </button>
                </div>
            </div>

            <!-- COLUMNA DERECHA: Información Detallada y Tablas -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Tarjeta Principal de Información General -->
                <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-8">
                    <div class="border-b border-slate-100 dark:border-slate-700 pb-4 mb-6">
                        <h3 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-wider">
                            Información del Socio
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Grupo 1: Datos Personales (Alta Legibilidad) -->
                        <div class="space-y-6">
                            <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 dark:text-indigo-400">Datos Personales y Contacto</h4>
                            
                            <div class="bg-slate-50 dark:bg-slate-900/40 p-4 rounded-xl space-y-4">
                                <div>
                                    <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">Correo Electrónico</span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200 break-all">
                                        {{ $socio->correo ?? 'No registrado' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">Teléfono Celular</span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                        {{ $socio->celular ?? 'No registrado' }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">Sexo</span>
                                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                            {{ $socio->sexo === 'M' ? 'Masculino' : ($socio->sexo === 'F' ? 'Femenino' : 'Otro') }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">Fecha de Alta</span>
                                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                            {{ $socio->fecha_alta ? $socio->fecha_alta->format('d/m/Y') : 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Grupo 2: Membresía y Estados Médicos -->
                        <div class="space-y-6">
                            <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 dark:text-indigo-400">Estado de Membresía y Apto</h4>
                            
                            <div class="bg-slate-50 dark:bg-slate-900/40 p-4 rounded-xl space-y-4">
                                <div>
                                    <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Membresía Contratada</span>
                                    <span class="inline-flex px-3 py-1 text-xs font-extrabold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-400 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                                        {{ $socio->membresia?->nombre ?? 'Sin membresía contratada' }}
                                    </span>
                                </div>
                                
                                <div>
                                    <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">Vencimiento de Membresía</span>
                                    @if($socio->fecha_vencimiento)
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                                {{ $socio->fecha_vencimiento->format('d/m/Y') }}
                                            </span>
                                            @if($socio->fecha_vencimiento->lt(\Carbon\Carbon::today()))
                                                <span class="px-2 py-0.5 text-[10px] font-black bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400 rounded-md border border-rose-200 dark:border-rose-900">
                                                    EXPIRADA
                                                </span>
                                            @elseif($socio->fecha_vencimiento->diffInDays(\Carbon\Carbon::today()) <= 7)
                                                <span class="px-2 py-0.5 text-[10px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400 rounded-md border border-amber-200 dark:border-amber-900 animate-pulse">
                                                    PROXIMA A VENCER
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 text-[10px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400 rounded-md border border-emerald-200 dark:border-emerald-900">
                                                    VIGENTE
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-sm font-bold text-slate-400 dark:text-slate-500 italic">No especificado</span>
                                    @endif
                                </div>

                                <div>
                                    <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">Estado de Apto Físico</span>
                                    @if($ultimoApto)
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                                Vence: {{ $ultimoApto->fecha_vencimiento->format('d/m/Y') }}
                                            </span>
                                            @if($ultimoApto->fecha_vencimiento->isPast() || $ultimoApto->estado === 'vencido')
                                                <span class="px-2 py-0.5 text-[10px] font-black bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400 rounded-md border border-rose-200 dark:border-rose-900 animate-pulse">
                                                    VENCIDO
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 text-[10px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400 rounded-md border border-emerald-200 dark:border-emerald-900">
                                                    APROBADO
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="inline-flex items-center mt-1 px-2.5 py-1 rounded-md text-2xs font-black bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400 border border-amber-200 dark:border-amber-900">
                                            PENDIENTE DE ENTREGA
                                        </span>
                                    @endif
                                </div>

                                <div>
                                    <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">Permanencia Promedio</span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200 block mt-1">
                                        {{ $permanenciaPromedio > 0 ? $permanenciaPromedio . ' minutos' : 'Sin registros suficientes' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de Pagos -->
                <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-8">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4 mb-4">
                        <h3 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-wider">Últimos Pagos</h3>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('pagos.create', $socio->id) }}" class="btn-primary">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Registrar Cobro
                            </a>
                            <span class="text-2xs font-extrabold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 px-2 py-1 rounded-md">Últimos 5</span>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr class="custom-table-head">
                                    <th class="custom-table-th">Fecha</th>
                                    <th class="custom-table-th">Importe</th>
                                    <th class="custom-table-th">Método</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($ultimosPagos as $pago)
                                    <tr class="custom-table-row">
                                        <td class="custom-table-td font-bold">{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                                        <td class="custom-table-td font-extrabold text-emerald-600 dark:text-emerald-400">${{ number_format($pago->importe, 2, ',', '.') }}</td>
                                        <td class="custom-table-td capitalize font-medium">{{ $pago->metodo_pago }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">
                                            No se han registrado pagos para este socio.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Historial de Aptos Físicos -->
                <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-8">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4 mb-4">
                        <h3 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-wider">Historial de Aptos Físicos</h3>
                        <a href="{{ route('aptos-fisicos.create', $socio->id) }}" class="btn-primary">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h.01M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2zm3-3h.01M9 17h.01M9 13h.01M12 13h.01M15 13h.01M9 9h.01M12 9h.01M15 9h.01"></path>
                            </svg>
                            Registrar Apto
                        </a>
                    </div>
                    
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr class="custom-table-head">
                                    <th class="custom-table-th">Emisión</th>
                                    <th class="custom-table-th">Vencimiento</th>
                                    <th class="custom-table-th">Estado</th>
                                    <th class="custom-table-th text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($historialAptos as $apto)
                                    @php
                                        $esVencido = $apto->fecha_vencimiento->isPast() || $apto->estado === 'vencido';
                                    @endphp
                                    <tr class="custom-table-row">
                                        <td class="custom-table-td font-bold">{{ $apto->fecha_emision->format('d/m/Y') }}</td>
                                        <td class="custom-table-td font-bold">
                                            @if($esVencido)
                                                <span class="text-rose-600 dark:text-rose-400">{{ $apto->fecha_vencimiento->format('d/m/Y') }}</span>
                                            @else
                                                <span class="text-emerald-600 dark:text-emerald-400">{{ $apto->fecha_vencimiento->format('d/m/Y') }}</span>
                                            @endif
                                        </td>
                                        <td class="custom-table-td">
                                            @if(!$esVencido)
                                                <span class="badge-success">
                                                    VIGENTE
                                                </span>
                                            @else
                                                <span class="badge-danger">
                                                    VENCIDO
                                                </span>
                                            @endif
                                        </td>
                                        <td class="custom-table-td text-right space-x-1">
                                            <a href="{{ route('aptos-fisicos.download', $apto->id) }}" target="_blank" class="btn-action-view">
                                                Ver Certificado
                                            </a>
                                            @if(auth()->check() && auth()->user()->rol === 'admin')
                                                <a href="{{ route('aptos-fisicos.edit', $apto->id) }}" class="btn-action-edit" wire:navigate>
                                                    Editar
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">
                                            No se han registrado certificados para este socio.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Historial de Asistencias (Accesos) -->
                <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-8">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4 mb-4">
                        <h3 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-wider">Historial de Accesos</h3>
                        <span class="text-2xs font-extrabold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 px-2 py-1 rounded-md">Todos los registros</span>
                    </div>

                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr class="custom-table-head">
                                    <th class="custom-table-th">Fecha</th>
                                    <th class="custom-table-th">Ingreso</th>
                                    <th class="custom-table-th">Salida</th>
                                    <th class="custom-table-th">Permanencia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($asistenciasPaginadas as $asistencia)
                                    <tr class="custom-table-row">
                                        <td class="custom-table-td font-bold">{{ $asistencia->fecha->format('d/m/Y') }}</td>
                                        <td class="custom-table-td font-mono text-slate-500 dark:text-slate-300 font-semibold">
                                            {{ \Carbon\Carbon::parse($asistencia->hora_ingreso)->format('H:i:s') }}
                                        </td>
                                        <td class="custom-table-td font-mono text-slate-500 dark:text-slate-300 font-semibold">
                                            @if($asistencia->hora_salida)
                                                {{ \Carbon\Carbon::parse($asistencia->hora_salida)->format('H:i:s') }}
                                            @else
                                                <span class="badge-success">En sala</span>
                                            @endif
                                        </td>
                                        <td class="custom-table-td">
                                            @if($asistencia->hora_salida)
                                                <span class="badge-info">
                                                    {{ $asistencia->permanencia_formateada }}
                                                </span>
                                            @else
                                                <span class="badge-success animate-pulse">
                                                    {{ $asistencia->permanencia_formateada }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">
                                            No se han registrado visitas para este socio.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación de Asistencias del Socio -->
                    <div class="mt-4">
                        {{ $asistenciasPaginadas->links() }}
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
