<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GymControl - Listo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-900 flex flex-col justify-between p-6 text-slate-100 font-sans">
    <div class="flex-1 flex flex-col justify-center items-center text-center max-w-md mx-auto space-y-6">
        <div class="w-24 h-24 bg-slate-800 rounded-full flex items-center justify-center border-4 border-slate-700 mx-auto">
            <svg class="w-12 h-12 text-indigo-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-200">Dispositivo Listo</h1>
            <p class="text-slate-400 mt-3 font-medium">El escaneo se procesó correctamente.</p>
            <p class="text-xs text-slate-500 mt-2">Podés cerrar esta pestaña o volver a escanear el QR cuando ingreses o salgas.</p>
        </div>
    </div>
    
    <div class="text-center text-xs text-slate-500 pt-4">
        GymControl &copy; 2026
    </div>
</body>
</html>
