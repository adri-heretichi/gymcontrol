<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GymControl - Acceso</title>
    <!-- Redirección después de 5 segundos -->
    <meta http-equiv="refresh" content="5;url={{ route('terminal.scan', ['token' => $token, 'ready' => 1]) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col justify-between p-6 {{ $estado === 'exito' ? 'bg-emerald-600' : ($estado === 'salida' ? 'bg-indigo-600' : 'bg-rose-600') }} text-white font-sans">
    <div class="flex-1 flex flex-col justify-center items-center text-center max-w-md mx-auto space-y-6">
        @if($estado === 'exito')
            <!-- Ingreso Exitoso (Fondo verde) -->
            <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div>
                <h1 class="text-4xl font-extrabold tracking-tight">
                    ¡Bienvenido/a, <span class="font-black underline">{{ $nombre }}</span>!
                </h1>
                <p class="text-lg text-emerald-100/90 mt-4 font-semibold">¡Que tengas un excelente entrenamiento!</p>
            </div>
        @elseif($estado === 'salida')
            <!-- Salida Registrada (Fondo azul) -->
            <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </div>
            <div>
                <h1 class="text-4xl font-extrabold tracking-tight">
                    ¡Hasta luego, <span class="font-black underline">{{ $nombre }}</span>!
                </h1>
            </div>
        @else
            <!-- Acceso Denegado (Fondo rojo) -->
            <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <div class="space-y-4 w-full">
                @if($mensaje === 'Token no válido')
                    <h1 class="text-4xl font-extrabold tracking-tight">Token no válido</h1>
                @else
                    <h1 class="text-4xl font-extrabold tracking-tight">Acceso Denegado</h1>
                    @if($nombre)
                        <p class="text-xl font-bold text-rose-100">Socio: <span class="font-black">{{ $nombre }}</span></p>
                    @endif
                @endif
                <div class="bg-black/20 rounded-2xl p-4 border border-white/10 mx-auto max-w-sm">
                    <p class="text-md font-bold text-rose-100 leading-relaxed">{{ $motivo }}</p>
                </div>
            </div>
        @endif
    </div>
    
    <div class="text-center text-xs text-white/60 pt-4">
        GymControl &copy; 2026
    </div>
</body>
</html>
