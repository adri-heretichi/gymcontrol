<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Terminal de Acceso - GymControl 24hs</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen">

        <!-- Banner del clima fijo en el layout — fuera de Livewire para que nunca desaparezca -->
        <div id="clima-banner"
             class="fixed top-0 left-0 right-0 z-50 hidden items-center justify-center gap-6 px-6 py-3"
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

        {{ $slot }}

        <script>
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

        // Cargar al iniciar la página
        cargarClima();

        // Recargar cada 10 minutos
        setInterval(cargarClima, 600000);
        </script>

    </body>
</html>
