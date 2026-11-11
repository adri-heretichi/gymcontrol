<div class="min-h-screen flex flex-col justify-between p-6 md:p-12 relative overflow-hidden bg-slate-950 text-slate-100"
     x-data="{ refocus() { if ($refs.tokenInput) $refs.tokenInput.focus() } }"
     x-init="
         refocus();
         document.body.addEventListener('click', () => refocus());
     "
>

    {{-- Banner del clima cargado desde el navegador vía JavaScript --}}
    {{-- Se mantiene visible entre polls de Livewire usando @script --}}
    <div id="clima-banner" class="fixed top-0 left-0 right-0 z-50 hidden items-center justify-center gap-6 px-6 py-3"
         style="background: linear-gradient(135deg, rgba(249,115,22,0.24) 0%, rgba(212,167,44,0.16) 100%);
                border-bottom: 1px solid rgba(255,255,255,0.1);
                backdrop-filter: blur(10px);">
        <span id="clima-icono" class="text-2xl"></span>
        <div class="flex items-center gap-4 text-sm font-bold text-white">
            <span>🌡️ <span id="clima-temp" class="text-orange-300"></span>°C</span>
            <span class="text-slate-600">|</span>
            <span>💧 <span id="clima-humedad" class="text-orange-300"></span>%</span>
            <span class="text-slate-600">|</span>
            <span>💨 <span id="clima-viento" class="text-orange-300"></span> km/h</span>
            <span class="text-slate-600">|</span>
            <span id="clima-desc" class="text-slate-300 font-semibold"></span>
            <span class="text-slate-300 font-semibold">· Formosa</span>
        </div>
    </div>

    <!-- Efectos luminosos de fondo -->
    <div class="absolute -right-24 -top-24 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -left-24 -bottom-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Encabezado de la Terminal -->
    <div class="z-10 mt-14 flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3 sm:gap-5">
            <img src="{{ asset('images/fenix/logo-oficial.jpg') }}" alt="Gimnasio Fénix" class="h-12 w-12 rounded-lg object-cover ring-1 ring-amber-400/50 sm:h-16 sm:w-16">
            <div>
                <span class="block text-lg font-black uppercase tracking-widest text-orange-300 sm:text-2xl">Fénix</span>
                <span class="block text-[9px] font-extrabold uppercase tracking-widest text-slate-400 sm:text-xs">Terminal de acceso · GymControl</span>
            </div>
        </div>

        <div class="text-slate-400 font-mono text-sm font-semibold bg-slate-900/80 px-4 py-2 rounded-2xl border border-slate-800">
            {{ now()->format('H:i') }}
        </div>
    </div>

    <!-- Área de Contenido Principal -->
    <div class="flex-1 flex flex-col justify-center items-center max-w-4xl w-full mx-auto my-8 z-10">

        @if($estadoAcceso === 'exito')
            <!-- PANEL VERDE: Ingreso Autorizado -->
            <div class="w-full bg-gradient-to-br from-emerald-500 to-teal-700 text-white rounded-3xl p-10 md:p-16 shadow-2xl relative overflow-hidden border border-emerald-400/30 text-center space-y-6 animate-[pulse_1.5s_infinite]">
                <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                    <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-xs font-black uppercase tracking-widest text-emerald-100/80">Ingreso Autorizado</span>
                    <h2 class="text-4xl md:text-5xl font-black mt-2 tracking-tight">{{ $socioInfo?->nombre }}</h2>
                    <p class="text-lg text-emerald-100/90 mt-2 font-semibold">{{ $mensajeAcceso }}</p>
                    @if($actividadAcceso && !empty($actividadAcceso['disciplina']))
                        <p class="mt-2 text-base font-bold">{{ $actividadAcceso['disciplina'] }} — {{ $actividadAcceso['grupo'] }}</p>
                    @endif
                    @if($horarioAcceso)
                        <p class="text-sm text-emerald-100">{{ ucfirst($horarioAcceso['dia']) }}, {{ $horarioAcceso['hora_inicio'] }}–{{ $horarioAcceso['hora_fin'] }}</p>
                    @endif
                    <p class="text-lg text-emerald-100/90 mt-2 font-semibold">¡Que tengas un excelente entrenamiento!</p>
                </div>
                <button type="button" wire:click="resetState" class="rounded-xl border border-white/40 px-5 py-3 font-bold text-white">Siguiente socio</button>
            </div>

        @elseif($estadoAcceso === 'salida')
            <!-- PANEL NARANJA: Salida Registrada -->
            <div class="w-full bg-gradient-to-br from-orange-600 to-orange-800 text-white rounded-3xl p-10 md:p-16 shadow-2xl relative overflow-hidden border border-orange-400/40 text-center space-y-6">
                <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                    <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-xs font-black uppercase tracking-widest text-orange-100/90">Salida Registrada</span>
                    <h2 class="text-4xl md:text-5xl font-black mt-2 tracking-tight">¡Hasta luego, {{ $socioInfo?->nombre }}!</h2>
                    <p class="text-lg text-orange-100/90 mt-2 font-semibold">{{ $mensajeAcceso }}</p>
                </div>
            </div>

        @elseif($estadoAcceso === 'error')
            <!-- PANEL ROJO: Acceso Denegado -->
            <div class="w-full bg-gradient-to-br from-rose-600 to-red-800 text-white rounded-3xl p-10 md:p-16 shadow-2xl relative overflow-hidden border border-rose-500/30 text-center space-y-6">
                <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                    <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-xs font-black uppercase tracking-widest text-rose-100/80">Acceso Denegado</span>
                    <h2 class="text-4xl md:text-5xl font-black mt-2 tracking-tight">
                        {{ $socioInfo ? ($socioInfo->nombre . ' ' . $socioInfo->apellido) : 'Socio No Registrado' }}
                    </h2>
                    <div class="mt-4 p-4 bg-black/10 rounded-2xl border border-white/10 max-w-lg mx-auto">
                        <p class="text-base font-bold text-rose-100 leading-relaxed">{{ $motivoRechazo }}</p>
                    </div>
                    @if($actividadAcceso && !empty($actividadAcceso['disciplina']))
                        <p class="mt-4 text-sm font-bold text-white">
                            Clase habilitada: {{ $actividadAcceso['disciplina'] }}{{ !empty($actividadAcceso['grupo']) ? ' — ' . $actividadAcceso['grupo'] : '' }}
                        </p>
                    @endif
                    @if($horarioAcceso)
                        <p class="text-sm text-rose-100">
                            {{ ucfirst($horarioAcceso['dia']) }}, {{ $horarioAcceso['hora_inicio'] }}–{{ $horarioAcceso['hora_fin'] }}
                        </p>
                    @endif
                    @if($opcionesActividad)
                        <form wire:submit.prevent="procesar" class="mx-auto mt-5 max-w-md space-y-3 text-left">
                            <label for="grupo-seleccionado" class="block text-sm font-bold text-white">Seleccioná el grupo y volvé a ingresar el PIN</label>
                            <select id="grupo-seleccionado" wire:model="grupoSeleccionado" class="w-full rounded-xl border-slate-600 bg-slate-900 text-white">
                                <option value="">Seleccionar grupo</option>
                                @foreach($opcionesActividad as $opcion)
                                    <option value="{{ $opcion['grupo_id'] }}">{{ $opcion['disciplina'] }} — {{ $opcion['grupo'] }}</option>
                                @endforeach
                            </select>
                            <input type="password" wire:model="pin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" aria-label="PIN de seis números" class="w-full rounded-xl border-slate-600 bg-slate-900 text-center text-2xl font-black tracking-[0.3em] text-white">
                            <div class="mx-auto grid max-w-sm grid-cols-3 gap-2" role="group" aria-label="Teclado numérico del PIN">
                                @foreach(['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                                    <button type="button" wire:click="appendDigit('{{ $digit }}')" class="rounded-xl border border-slate-700 bg-slate-900 py-2 text-lg font-black text-white">{{ $digit }}</button>
                                @endforeach
                                <button type="button" wire:click="removeLastDigit" class="rounded-xl border border-slate-700 bg-slate-900 py-2 text-xs font-bold text-slate-200">Borrar</button>
                                <button type="button" wire:click="appendDigit('0')" class="rounded-xl border border-slate-700 bg-slate-900 py-2 text-lg font-black text-white">0</button>
                                <button type="button" wire:click="clearPin" class="rounded-xl border border-slate-700 bg-slate-900 py-2 text-xs font-bold text-slate-200">Limpiar</button>
                            </div>
                            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-indigo-600 px-5 py-3 font-black text-white disabled:opacity-50">Registrar ingreso</button>
                        </form>
                    @endif
                </div>
                <button type="button" wire:click="resetState" class="rounded-xl border border-white/40 px-5 py-3 font-bold text-white">Nuevo intento</button>
            </div>

        @else
            <!-- PANTALLA NEUTRAL: Esperando interacción -->
            <div class="w-full max-w-xl text-center space-y-8">
                <div class="space-y-3">
                    <h1 class="text-4xl md:text-5xl font-black tracking-tight text-white leading-tight">
                        Bienvenido a <span class="bg-gradient-to-r from-orange-300 to-amber-400 bg-clip-text text-transparent">Gimnasio Fénix</span>
                    </h1>
                    <p class="text-slate-400 text-sm md:text-base font-semibold leading-relaxed">
                        Ingresá tu PIN de acceso. El ingreso y la salida son acciones separadas.
                    </p>
                </div>

                <!-- PIN para ingreso -->
                <div class="bg-slate-900/60 p-8 rounded-3xl border border-slate-800 shadow-2xl relative">
                    <div class="absolute inset-x-0 top-0 h-1 bg-orange-500/50 shadow-[0_0_15px_#f97316] animate-[pulse_2s_infinite]"></div>

                    <form wire:submit.prevent="procesar" class="space-y-4">
                        <input
                            type="password"
                            id="pin"
                            x-ref="tokenInput"
                            wire:model="pin"
                            wire:loading.attr="disabled"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            placeholder="Ingresá el PIN de seis dígitos..."
                            autocomplete="off"
                            aria-label="PIN de seis números"
                            class="w-full text-center py-5 border-2 border-slate-800 bg-slate-950/80 rounded-2xl text-3xl font-black tracking-widest text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-inner"
                        />
                        @error('pin') <p class="text-sm font-bold text-rose-300">{{ $message }}</p> @enderror
                        <div class="mx-auto grid max-w-sm grid-cols-3 gap-2" role="group" aria-label="Teclado numérico del PIN">
                            @foreach(['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                                <button type="button" wire:click="appendDigit('{{ $digit }}')" wire:loading.attr="disabled"
                                        class="rounded-xl border border-slate-700 bg-slate-900 py-3 text-xl font-black text-white hover:bg-slate-800 disabled:opacity-50"
                                        aria-label="Ingresar {{ $digit }}">{{ $digit }}</button>
                            @endforeach
                            <button type="button" wire:click="removeLastDigit" wire:loading.attr="disabled"
                                    class="rounded-xl border border-slate-700 bg-slate-900 py-3 text-sm font-bold text-slate-200 hover:bg-slate-800"
                                    aria-label="Borrar último dígito">Borrar</button>
                            <button type="button" wire:click="appendDigit('0')" wire:loading.attr="disabled"
                                    class="rounded-xl border border-slate-700 bg-slate-900 py-3 text-xl font-black text-white hover:bg-slate-800"
                                    aria-label="Ingresar 0">0</button>
                            <button type="button" wire:click="clearPin" wire:loading.attr="disabled"
                                    class="rounded-xl border border-slate-700 bg-slate-900 py-3 text-sm font-bold text-slate-200 hover:bg-slate-800"
                                    aria-label="Limpiar PIN">Limpiar</button>
                        </div>
                        <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-indigo-600 px-6 py-3 font-black text-white disabled:opacity-50">
                            <span wire:loading.remove wire:target="procesar">Registrar ingreso</span>
                            <span wire:loading wire:target="procesar">Validando...</span>
                        </button>
                    </form>
                </div>

                @if($estadoAcceso === 'exito' || $estadoAcceso === 'error')
                    <div class="rounded-2xl border p-5 text-left {{ $estadoAcceso === 'exito' ? 'border-emerald-500/40 bg-emerald-950/70' : 'border-rose-500/40 bg-rose-950/70' }}" role="status" aria-live="polite">
                        <h2 class="text-xl font-black text-white">
                            {{ $estadoAcceso === 'exito' ? 'Ingreso autorizado' : 'Acceso denegado' }}
                        </h2>
                        @if($socioInfo)
                            <p class="mt-1 text-lg font-bold text-white">{{ $socioInfo->nombre }} {{ $socioInfo->apellido }}</p>
                        @endif
                        <p class="mt-2 text-sm text-slate-100">{{ $mensajeAcceso }}</p>
                        @if($actividadAcceso && !empty($actividadAcceso['disciplina']))
                            <p class="mt-2 text-sm font-semibold text-slate-200">
                                {{ $actividadAcceso['disciplina'] }}{{ !empty($actividadAcceso['grupo']) ? ' — ' . $actividadAcceso['grupo'] : '' }}
                            </p>
                        @endif
                        @if($horarioAcceso)
                            <p class="text-sm text-slate-200">
                                {{ ucfirst($horarioAcceso['dia']) }}, {{ $horarioAcceso['hora_inicio'] }}–{{ $horarioAcceso['hora_fin'] }}
                            </p>
                        @endif
                        @if($opcionesActividad)
                            <label for="grupo-seleccionado" class="mt-4 block text-sm font-bold text-white">Hay más de una actividad en curso. Seleccioná el grupo y volvé a ingresar el PIN.</label>
                            <select id="grupo-seleccionado" wire:model="grupoSeleccionado" class="mt-2 w-full rounded-xl border-slate-600 bg-slate-900 text-white">
                                <option value="">Seleccionar grupo</option>
                                @foreach($opcionesActividad as $opcion)
                                    <option value="{{ $opcion['grupo_id'] }}">{{ $opcion['disciplina'] }} — {{ $opcion['grupo'] }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endif

                <div class="flex flex-col items-center gap-2">
                    <div class="flex items-center gap-2 px-4 py-2 bg-slate-900/40 rounded-full border border-slate-800/80">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-ping"></span>
                        <span class="text-xs font-bold text-slate-400">Terminal esperando marcado</span>
                    </div>
                </div>
            </div>
        @endif

    </div>
    <!-- Pie de página de la terminal -->
    <div class="text-center z-10 text-[9px] font-extrabold tracking-widest text-slate-600 uppercase border-t border-slate-900 pt-6">
        &copy; {{ date('Y') }} Gimnasio Fénix · Sistema GymControl
    </div>

</div>

@script
<script>
// Función para mapear el código del clima a ícono y descripción
function mapearClima(code) {
    if (code === 0) return { icono: '☀️', desc: 'Despejado' };
    if ([1,2,3].includes(code)) return { icono: '🌤️', desc: 'Parcialmente nublado' };
    if ([45,48].includes(code)) return { icono: '🌫️', desc: 'Neblina' };
    if ([51,53,55,61,63,65].includes(code)) return { icono: '🌧️', desc: 'Lluvia' };
    if ([71,73,75].includes(code)) return { icono: '🌨️', desc: 'Nieve' };
    if ([80,81,82].includes(code)) return { icono: '🌦️', desc: 'Lluvias dispersas' };
    if ([95,96,99].includes(code)) return { icono: '⛈️', desc: 'Tormenta' };
    return { icono: '🌡️', desc: 'Variable' };
}

// Función principal que carga el clima desde el navegador
function cargarClima() {
    fetch('https://api.open-meteo.com/v1/forecast?latitude=-26.18&longitude=-58.18&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=America%2FArgentina%2FSalta')
        .then(r => r.json())
        .then(data => {
            const c = data.current;
            const clima = mapearClima(c.weather_code);
            document.getElementById('clima-icono').textContent = clima.icono;
            document.getElementById('clima-temp').textContent = c.temperature_2m;
            document.getElementById('clima-humedad').textContent = c.relative_humidity_2m;
            document.getElementById('clima-viento').textContent = c.wind_speed_10m;
            document.getElementById('clima-desc').textContent = clima.desc;
            const banner = document.getElementById('clima-banner');
            banner.classList.remove('hidden');
            banner.classList.add('flex');
        })
        .catch(() => {});
}

// Función para mantener el banner visible después de cada poll de Livewire
function mantenerBanner() {
    const banner = document.getElementById('clima-banner');
    const temp = document.getElementById('clima-temp');
    if (banner && temp && temp.textContent) {
        banner.classList.remove('hidden');
        banner.classList.add('flex');
    }
}

// Cargar clima al iniciar
cargarClima();

// Recargar clima cada 10 minutos
setInterval(cargarClima, 600000);

// Mantener el banner visible después de cada actualización de Livewire
Livewire.hook('commit', ({ succeed }) => {
    succeed(() => {
        mantenerBanner();
    });
});
</script>
@endscript
