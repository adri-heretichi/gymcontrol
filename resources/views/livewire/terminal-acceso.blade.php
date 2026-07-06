<div class="min-h-screen flex flex-col justify-between p-6 md:p-12 relative overflow-hidden bg-slate-950 text-slate-100"
     x-data="{ refocus() { $refs.tokenInput.focus() } }"
     x-init="
         refocus();
         document.body.addEventListener('click', () => refocus());
         $watch('$wire.estadoAcceso', value => {
             if (value) {
                 setTimeout(() => {
                     $wire.resetState();
                     refocus();
                 }, 3000);
             }
         });
     "
     wire:poll.1s="checkExternalScan"
>

    {{-- Banner del clima cargado desde el navegador vía JavaScript --}}
    {{-- Se mantiene visible entre polls de Livewire usando @script --}}
    <div id="clima-banner" class="fixed top-0 left-0 right-0 z-50 hidden items-center justify-center gap-6 px-6 py-3"
         style="background: linear-gradient(135deg, rgba(99,102,241,0.25) 0%, rgba(139,92,246,0.25) 100%);
                border-bottom: 1px solid rgba(255,255,255,0.1);
                backdrop-filter: blur(10px);">
        <span id="clima-icono" class="text-2xl"></span>
        <div class="flex items-center gap-4 text-sm font-bold text-white">
            <span>🌡️ <span id="clima-temp" class="text-indigo-300"></span>°C</span>
            <span class="text-slate-600">|</span>
            <span>💧 <span id="clima-humedad" class="text-indigo-300"></span>%</span>
            <span class="text-slate-600">|</span>
            <span>💨 <span id="clima-viento" class="text-indigo-300"></span> km/h</span>
            <span class="text-slate-600">|</span>
            <span id="clima-desc" class="text-slate-300 font-semibold"></span>
            <span class="text-slate-300 font-semibold">· Formosa</span>
        </div>
    </div>

    <!-- Efectos luminosos de fondo -->
    <div class="absolute -right-24 -top-24 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -left-24 -bottom-24 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Encabezado de la Terminal -->
    <div class="flex justify-between items-center z-10 mt-14">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4M6 8h12M4 11v8a3 3 0 003 3h10a3 3 0 003-3v-8H4z" />
                    <path d="M7 8V6a5 5 0 0110 0v2" />
                </svg>
            </div>
            <div>
                <span class="block font-black text-xl tracking-tight bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">GYMCONTROL 24 HS</span>
                <span class="block text-[9px] font-extrabold tracking-widest text-slate-500 uppercase">Terminal de Autogestión</span>
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
                    <h2 class="text-4xl md:text-5xl font-black mt-2 tracking-tight">{{ $mensajeAcceso }}</h2>
                    <p class="text-lg text-emerald-100/90 mt-2 font-semibold">¡Que tengas un excelente entrenamiento!</p>
                </div>
            </div>

        @elseif($estadoAcceso === 'salida')
            <!-- PANEL AZUL: Salida Registrada -->
            <div class="w-full bg-gradient-to-br from-indigo-600 to-indigo-800 text-white rounded-3xl p-10 md:p-16 shadow-2xl relative overflow-hidden border border-indigo-500/30 text-center space-y-6">
                <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                    <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-xs font-black uppercase tracking-widest text-indigo-100/80">Salida Registrada</span>
                    <h2 class="text-4xl md:text-5xl font-black mt-2 tracking-tight">¡Hasta luego, {{ $socioInfo?->nombre }}!</h2>
                    <p class="text-lg text-indigo-100/90 mt-2 font-semibold">{{ $mensajeAcceso }}</p>
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
                </div>
            </div>

        @else
            <!-- PANTALLA NEUTRAL: Esperando interacción -->
            <div class="w-full max-w-xl text-center space-y-8">
                <div class="space-y-3">
                    <h1 class="text-4xl md:text-5xl font-black tracking-tight text-white leading-tight">
                        Bienvenido a <span class="bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">GymControl</span>
                    </h1>
                    <p class="text-slate-400 text-sm md:text-base font-semibold leading-relaxed">
                        Ingresá tu Token PIN de acceso o escaneá el código QR generado desde tu celular para habilitar el ingreso o salida.
                    </p>
                </div>

                <!-- Input para Token/USB Reader -->
                <div class="bg-slate-900/60 p-8 rounded-3xl border border-slate-800 shadow-2xl relative">
                    <div class="absolute inset-x-0 top-0 h-1 bg-indigo-500/50 shadow-[0_0_15px_#6366f1] animate-[pulse_2s_infinite]"></div>

                    <form wire:submit.prevent="procesar" class="space-y-4">
                        <input 
                            type="text" 
                            id="token"
                            x-ref="tokenInput"
                            wire:model="token" 
                            wire:keydown.enter.prevent="procesar"
                            placeholder="Digitá tu token aquí..." 
                            autocomplete="off"
                            class="w-full text-center py-5 border-2 border-slate-800 bg-slate-950/80 rounded-2xl text-3xl font-black tracking-widest text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-inner"
                        />
                    </form>
                </div>

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
        &copy; {{ date('Y') }} GymControl 24hs. Todos los derechos reservados.
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
