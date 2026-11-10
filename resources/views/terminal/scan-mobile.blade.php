<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Acceso · GymControl</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
    <main class="mx-auto flex min-h-[80vh] w-full max-w-md flex-col justify-center">
        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl sm:p-8">
            <header class="mb-6 text-center">
                <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-300">Gimnasio Fénix</p>
                <h1 class="mt-2 text-3xl font-black text-white">Control de acceso</h1>
                <p class="mt-2 text-sm text-slate-400">Confirmá el ingreso desde tu celular.</p>
            </header>

            @if($resultado['autorizado'] && $resultado['codigo'] === 'salida_registrada')
                <div class="rounded-2xl border border-sky-700 bg-sky-950/60 p-5 text-center" role="status" aria-live="polite">
                    <h2 class="text-xl font-black text-white">Salida registrada</h2>
                    <p class="mt-2 text-sm text-sky-100">{{ $resultado['mensaje'] }}</p>
                </div>
            @elseif($resultado['autorizado'])
                <div class="rounded-2xl border border-emerald-700 bg-emerald-950/50 p-5" role="status" aria-live="polite">
                    @if($desdePost)
                        <h2 class="text-xl font-black text-white">¡Bienvenido, {{ $resultado['socio']->nombre }}!</h2>
                        <p class="mt-2 text-sm text-emerald-100">Ingreso autorizado.</p>
                    @else
                        <h2 class="text-xl font-black text-white">Ingreso disponible</h2>
                        <p class="mt-2 text-sm text-emerald-100">La credencial cumple los requisitos. Confirmá para registrar tu ingreso.</p>
                    @endif
                    @if($resultado['actividad'] && !empty($resultado['actividad']['disciplina']))
                        <p class="mt-3 font-bold text-white">
                            {{ $resultado['actividad']['disciplina'] }}
                            @if(!empty($resultado['actividad']['grupo']))
                                — {{ $resultado['actividad']['grupo'] }}
                            @endif
                        </p>
                    @endif
                    @if($resultado['horario'])
                        <p class="mt-1 text-sm text-emerald-100">
                            {{ ucfirst($resultado['horario']['dia']) }},
                            {{ $resultado['horario']['hora_inicio'] }}–{{ $resultado['horario']['hora_fin'] }}
                        </p>
                    @endif
                </div>
            @else
                <div class="rounded-2xl border border-rose-700 bg-rose-950/60 p-5" role="alert" aria-live="assertive">
                    <h2 class="text-xl font-black text-white">ACCESO DENEGADO</h2>
                    <p class="mt-2 text-sm font-semibold text-rose-100">{{ $resultado['mensaje'] }}</p>
                </div>
            @endif

            @if(!$desdePost && $resultado['codigo'] === 'actividad_ambigua')
                <form method="POST" action="{{ route('terminal.scan.ingreso') }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="identificador" value="{{ $token }}">
                    <label for="grupo_id" class="block text-sm font-bold text-white">Seleccioná la actividad</label>
                    <select id="grupo_id" name="grupo_id" required class="w-full rounded-xl border-slate-700 bg-slate-950 text-white">
                        <option value="">Elegir grupo</option>
                        @foreach($resultado['actividad']['opciones'] ?? [] as $opcion)
                            <option value="{{ $opcion['grupo_id'] }}">
                                {{ $opcion['disciplina'] }} — {{ $opcion['grupo'] }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full rounded-xl bg-indigo-600 px-5 py-4 font-black text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        Confirmar ingreso
                    </button>
                </form>
            @elseif(!$desdePost && $resultado['autorizado'])
                <form method="POST" action="{{ route('terminal.scan.ingreso') }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="identificador" value="{{ $token }}">
                    <input type="hidden" name="grupo_id" value="{{ $resultado['actividad']['grupo_id'] }}">
                    <button type="submit" class="w-full rounded-xl bg-indigo-600 px-5 py-4 font-black text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        Confirmar ingreso
                    </button>
                </form>
            @endif

            @if($puedeSalir)
                <form method="POST" action="{{ route('terminal.scan.salida') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="identificador" value="{{ $token }}">
                    <button type="submit" class="w-full rounded-xl border border-slate-600 px-5 py-3 font-bold text-slate-200 transition hover:bg-slate-800">
                        Registrar salida
                    </button>
                </form>
            @endif
        </section>
        <p class="mt-5 text-center text-xs text-slate-500">El acceso se registra únicamente al confirmar la acción.</p>
    </main>
</body>
</html>
