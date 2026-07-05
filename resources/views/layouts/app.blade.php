<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 flex flex-col">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200 py-6 mt-auto" x-data="{ openEquipo: false }">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-500 flex justify-center items-center gap-1">
                    <span>GymControl &copy; 2026 &middot;</span>
                    <button type="button" @click="openEquipo = true" class="text-indigo-600 hover:text-indigo-900 font-semibold focus:outline-none">
                        [Ver equipo &rarr;]
                    </button>
                </div>

                <!-- Modal de Equipo -->
                <div x-show="openEquipo" 
                     class="fixed inset-0 z-50 overflow-y-auto" 
                     style="display: none;" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     x-cloak>
                    
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="openEquipo = false"></div>

                    <!-- Content -->
                    <div class="flex items-center justify-center min-h-screen p-4 relative z-10">
                        <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-4xl w-full p-8 shadow-2xl border border-slate-100 dark:border-slate-700 space-y-6 text-left" @click.stop>
                            <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700 pb-4">
                                <h3 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">Equipo de Desarrollo</h3>
                                <button type="button" @click="openEquipo = false" class="text-slate-400 hover:text-slate-600 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- DESARROLLADOR 1 -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 flex flex-col items-center text-center space-y-4">
                                    <img src="{{ asset('images/equipo/diego.jpg') }}"
                                         class="w-24 h-24 rounded-full object-cover mx-auto mb-3 border-4 border-blue-400">
                                    <div>
                                        <h4 class="text-base font-bold text-slate-900 dark:text-white">Diego Cardozo</h4>
                                        <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-2xs font-extrabold uppercase bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                            Desarrollo Backend
                                        </span>
                                    </div>
                                    <div class="text-2xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                                        Lógica del sistema, base de datos y gestión de información
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                        Responsable de organizar el funcionamiento interno de GymControl, incluyendo la gestión de socios, membresías, pagos, aptos físicos y asistencias. Su trabajo permite que la información se registre, se procese y se consulte correctamente.
                                    </p>
                                </div>

                                <!-- DESARROLLADOR 2 -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 flex flex-col items-center text-center space-y-4">
                                    <img src="{{ asset('images/equipo/gabriela.jpg') }}"
                                         class="w-24 h-24 rounded-full object-cover mx-auto mb-3 border-4 border-pink-400">
                                    <div>
                                        <h4 class="text-base font-bold text-slate-900 dark:text-white">Gabriela Lopez</h4>
                                        <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-2xs font-extrabold uppercase bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300">
                                            Desarrollo Frontend
                                        </span>
                                    </div>
                                    <div class="text-2xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                                        Diseño de interfaz y experiencia de usuario
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                        Responsable de crear pantallas modernas, claras y fáciles de utilizar dentro del sistema GymControl. Su trabajo permite que los usuarios puedan navegar por el sistema de manera simple, visual y ordenada.
                                    </p>
                                </div>

                                <!-- DESARROLLADOR 3 -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 flex flex-col items-center text-center space-y-4">
                                    <img src="{{ asset('images/equipo/adriana.jpg') }}"
                                         class="w-24 h-24 rounded-full object-cover mx-auto mb-3 border-4 border-green-400">
                                    <div>
                                        <h4 class="text-base font-bold text-slate-900 dark:text-white">Adriana Heretichi</h4>
                                        <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-2xs font-extrabold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            Desarrollo Full Stack
                                        </span>
                                    </div>
                                    <div class="text-2xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                                        Integración entre interfaz, funcionalidades y control de asistencia
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                        Responsable de conectar la parte visual con la lógica interna del sistema. Su trabajo se enfoca en integrar funciones como ingreso y egreso de socios, validación de membresías, uso de token o QR y generación de reportes dentro de GymControl.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
