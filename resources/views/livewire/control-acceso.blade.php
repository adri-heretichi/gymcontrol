<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen" 
     x-data="{ refocus() { $refs.identificador.focus() } }" 
     x-init="refocus(); document.body.addEventListener('click', () => refocus())"
     wire:poll.3s>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Mensajes Flash de Retroalimentación de Salidas Manuales -->
        @if (session()->has('message'))
            <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-950/30 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-300 rounded-r-xl font-bold text-sm shadow-md animate-pulse">
                {{ session('message') }}
            </div>
        @endif

        <!-- Encabezado de Control de Acceso -->
        <div class="pb-6 border-b border-slate-200 dark:border-slate-800 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Control de Ingreso y Salida</h2>
                <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Validación automatizada de cuotas, membresías y certificados médicos en tiempo real.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900">
                    <span class="w-2 h-2 mr-2 bg-indigo-500 rounded-full animate-ping"></span>
                    Terminal Activa
                </span>
            </div>
        </div>

        <!-- Layout de dos paneles -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- PANEL DE CONTROL (Izquierda - 2 columnas de ancho en desktop) -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Tarjeta de Entrada de Datos -->
                <div class="bg-white dark:bg-slate-800 p-8 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-indigo-500/5 rounded-full blur-2xl"></div>
                    
                    <form wire:submit.prevent="procesar" class="space-y-4">
                        <label for="identificador" class="form-label">
                            Escanear QR / Token / Ingresar DNI
                        </label>
                        <div class="relative rounded-2xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h.01M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2zm3-3h.01M9 17h.01M9 13h.01M12 13h.01M15 13h.01M9 9h.01M12 9h.01M15 9h.01"></path>
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                id="identificador"
                                x-ref="identificador"
                                wire:model="identificador" 
                                placeholder="Ingrese DNI o Token y presione Enter..." 
                                autocomplete="off"
                                class="w-full pl-12 pr-4 py-4 border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-2xl text-lg font-black tracking-wider text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-inner"
                            />
                        </div>
                        <p class="text-xs text-slate-400 dark:text-slate-300 italic font-semibold">Haga clic en cualquier lugar de la pantalla para bloquear el enfoque automático del escáner.</p>
                    </form>
                </div>

                <!-- Panel de Estado de Acceso (Respuesta Visual) -->
                <div>
                    @if($estadoAcceso === 'exito')
                        <!-- ACCESO PERMITIDO (Verde Premium) -->
                        <div class="bg-gradient-to-br from-emerald-500 to-teal-700 text-white rounded-3xl p-8 shadow-2xl relative overflow-hidden border border-emerald-600 animate-fade-in">
                            <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-white/10 rounded-full blur-3xl"></div>
                            
                            <div class="flex flex-col sm:flex-row items-center gap-6">
                                <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 text-white">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <div class="text-center sm:text-left flex-1">
                                    <span class="text-xs font-extrabold uppercase tracking-widest text-emerald-100">INGRESO AUTORIZADO</span>
                                    <h3 class="text-3xl font-black mt-1 leading-tight">{{ $socioInfo?->nombre }} {{ $socioInfo?->apellido }}</h3>
                                    <p class="text-sm text-emerald-100/90 mt-1 font-semibold">Membresía: {{ $socioInfo?->membresia?->nombre }} | DNI: {{ $socioInfo?->dni }}</p>
                                </div>
                            </div>
                        </div>

                    @elseif($estadoAcceso === 'salida')
                        <!-- SALIDA REGISTRADA (Azul/Índigo Premium) -->
                        <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 text-white rounded-3xl p-8 shadow-2xl relative overflow-hidden border border-indigo-700 animate-fade-in">
                            <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-white/10 rounded-full blur-3xl"></div>
                            
                            <div class="flex flex-col sm:flex-row items-center gap-6">
                                <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 text-white">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                </div>
                                <div class="text-center sm:text-left flex-1">
                                    <span class="text-xs font-extrabold uppercase tracking-widest text-indigo-100">SALIDA REGISTRADA</span>
                                    <h3 class="text-3xl font-black mt-1 leading-tight">{{ $socioInfo?->nombre }} {{ $socioInfo?->apellido }}</h3>
                                    <p class="text-sm text-indigo-100/90 mt-1 font-semibold">{{ $mensajeAcceso }}</p>
                                </div>
                            </div>

                            {{-- Aviso de tiempo agotado en el panel de salida --}}
                            @if(count($motivosDenegacion) > 0)
                                <div class="mt-6 p-4 bg-orange-500/20 rounded-2xl border border-orange-400/30">
                                    <ul class="list-disc pl-5 space-y-1 text-sm font-bold text-orange-100">
                                        @foreach($motivosDenegacion as $motivo)
                                            <li>{{ $motivo }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>

                    @elseif($estadoAcceso === 'error')
                        <!-- ACCESO RECHAZADO (Rojo/Alerta de peligro) -->
                        <div class="bg-gradient-to-br from-rose-600 to-red-800 text-white rounded-3xl p-8 shadow-2xl relative overflow-hidden border border-rose-700 animate-fade-in">
                            <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-white/10 rounded-full blur-3xl"></div>
                            
                            <div class="flex flex-col sm:flex-row items-center gap-6">
                                <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 text-white">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                                <div class="text-center sm:text-left flex-1">
                                    <span class="text-xs font-extrabold uppercase tracking-widest text-rose-100">ACCESO DENEGADO</span>
                                    <h3 class="text-3xl font-black mt-1 leading-tight">
                                        {{ $socioInfo ? ($socioInfo->nombre . ' ' . $socioInfo->apellido) : 'Socio no registrado' }}
                                    </h3>
                                    @if($socioInfo)
                                        <p class="text-sm text-rose-100/90 mt-0.5 font-semibold">DNI: {{ $socioInfo->dni }}</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Listado de razones específicas -->
                            <div class="mt-6 p-4 bg-black/10 rounded-2xl border border-white/10">
                                <h4 class="text-sm font-bold uppercase tracking-wider text-rose-100 mb-2">Motivos de denegación:</h4>
                                <ul class="list-disc pl-5 space-y-1.5 text-sm font-semibold text-white/95">
                                    @foreach($motivosDenegacion as $motivo)
                                        <li>{{ $motivo }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                    @else
                        <!-- ESTADO NEUTRAL (Esperando interacción) -->
                        <div class="bg-indigo-950 text-indigo-300 rounded-3xl p-10 shadow-2xl relative overflow-hidden border border-indigo-900 text-center flex flex-col items-center justify-center min-h-[300px]">
                            <!-- Línea de escaneo animada -->
                            <div class="absolute inset-x-0 top-0 h-1 bg-indigo-500/80 shadow-[0_0_15px_#6366f1] animate-[pulse_2s_infinite]"></div>
                            
                            <svg class="w-16 h-16 text-indigo-400 mb-4 animate-[bounce_3s_infinite]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h.01M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2zm3-3h.01M9 17h.01M9 13h.01M12 13h.01M15 13h.01M9 9h.01M12 9h.01M15 9h.01"></path>
                            </svg>
                            <h3 class="text-xl font-extrabold text-white tracking-tight">Esperando Lectura de Acceso</h3>
                            <p class="text-xs text-indigo-400 max-w-sm mt-2 font-medium">Por favor, escanee la credencial del socio o ingrese su número de DNI / PIN manual en la caja de arriba.</p>
                        </div>
                    @endif
                </div>

            </div>

            <!-- PANEL DE SOCIOS EN SALA (Derecha) -->
            <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-6 flex flex-col h-[600px]">
                <div class="border-b border-slate-100 dark:border-slate-700 pb-4 mb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-wider">Socios en Sala</h3>
                        <p class="text-[10px] font-bold text-slate-500 dark:text-slate-300 mt-0.5">Listado de asistentes actuales</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-black bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400 rounded-full border border-indigo-100 dark:border-indigo-900">
                        {{ count($sociosEnSala) }}
                    </span>
                </div>

                <!-- Lista con Scroll -->
                <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                    @forelse($sociosEnSala as $asistencia)
                        <div class="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-slate-100 dark:border-slate-800/80 flex justify-between items-center hover:border-slate-200 dark:hover:border-slate-700 transition duration-150">
                            <div>
                                <span class="block text-sm font-extrabold text-slate-900 dark:text-white leading-tight">
                                    {{ $asistencia->socio?->nombre }} {{ $asistencia->socio?->apellido }}
                                </span>
                                <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-400 mt-0.5 uppercase tracking-wide">
                                    {{ $asistencia->socio?->membresia?->nombre ?? 'Sin Plan' }}
                                </span>
                                <span class="block text-[10px] font-mono font-bold text-indigo-500 mt-1">
                                    PIN: {{ $asistencia->socio?->token }} | DNI: {{ $asistencia->socio?->dni }}
                                </span>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="text-2xs text-slate-500 dark:text-slate-300 font-bold">
                                        Ingresó: {{ \Carbon\Carbon::parse($asistencia->hora_ingreso)->format('H:i') }}
                                    </span>
                                    <span class="badge-info !text-[9px] !px-1.5 !py-0.5">
                                        {{ \Carbon\Carbon::parse($asistencia->fecha->format('Y-m-d') . ' ' . $asistencia->hora_ingreso)->diffInMinutes(\Carbon\Carbon::now()) }} min
                                    </span>
                                </div>

                                {{-- Badge de aviso si tiene límite de horas diarias --}}
                                @if($asistencia->tiene_limite)
                                    @if($asistencia->minutos_hoy >= 120)
                                        <div class="mt-2 px-2 py-1 bg-red-100 dark:bg-red-950 border border-red-300 dark:border-red-800 rounded-lg">
                                            <span class="text-[10px] font-black text-red-600 dark:text-red-400">
                                                ⏰ TIEMPO AGOTADO — Debe abonar para continuar
                                            </span>
                                        </div>
                                    @elseif($asistencia->minutos_hoy >= 96)
                                        <div class="mt-2 px-2 py-1 bg-orange-100 dark:bg-orange-950 border border-orange-300 dark:border-orange-800 rounded-lg">
                                            <span class="text-[10px] font-black text-orange-600 dark:text-orange-400">
                                                ⚠️ {{ 120 - $asistencia->minutos_hoy }} min restantes
                                            </span>
                                        </div>
                                    @endif
                                @endif

                            </div>
                            
                            <button 
                                wire:click="registrarSalidaManual({{ $asistencia->id }})" 
                                class="btn-action-danger"
                                title="Registrar Salida Manual"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-center text-slate-400 dark:text-slate-500 italic py-12">
                            <svg class="w-12 h-12 text-slate-300 dark:text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <p class="text-xs font-semibold">No hay socios en sala actualmente.</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>
</div>
