<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GymControl - Acceso</title>
    <!-- Redirección automática después de 5 segundos -->
    <meta http-equiv="refresh" content="5;url=/terminal/scan/{{ $token }}?ready=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col justify-center items-center text-center p-6 {{ $estado === 'exito' ? 'bg-[#22c55e]' : ($estado === 'salida' ? 'bg-[#3b82f6]' : 'bg-[#ef4444]') }} text-white font-sans">
    <div class="space-y-6 max-w-md mx-auto">
        @if($estado === 'exito')
            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight leading-tight">
                ¡Bienvenido/a, <span class="font-black underline">{{ $nombre }}</span>!
            </h1>
        @elseif($estado === 'salida')
            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight leading-tight">
                ¡Hasta luego, <span class="font-black underline">{{ $nombre }}</span>!
            </h1>
        @else
            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 mx-auto">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <div class="space-y-4">
                @if($mensaje === 'Token no válido')
                    <h1 class="text-4xl font-extrabold tracking-tight leading-tight">Token no válido</h1>
                @else
                    <h1 class="text-4xl font-extrabold tracking-tight leading-tight">Acceso Denegado</h1>
                    @if($nombre)
                        <p class="text-xl font-bold text-red-100">Socio: <span class="font-black">{{ $nombre }}</span></p>
                    @endif
                @endif
                <div class="bg-black/20 rounded-2xl p-4 border border-white/10 mx-auto max-w-sm">
                    <p class="text-md font-bold leading-relaxed">{{ $motivo }}</p>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
