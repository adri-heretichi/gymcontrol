<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>GymControl - Acceso</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* Garantizar contraste y diseño premium sin depender de Tailwind compilado */
            body {
                background-color: #0f172a; /* Slate 900 */
                color: #f8fafc; /* Slate 50 */
                font-family: 'Figtree', sans-serif;
                margin: 0;
                padding: 0;
            }
            .split-container {
                display: flex;
                min-height: 100vh;
                height: 100vh;
                overflow: hidden;
            }
            .left-pane {
                display: none;
                flex: 1.2;
                position: relative;
                background-image: linear-gradient(135deg, rgba(15, 23, 42, 0.85), rgba(30, 27, 75, 0.95)), url('{{ asset('images/gym_bg.png') }}');
                background-size: cover;
                background-position: center;
                flex-direction: column;
                justify-content: space-between;
                padding: 3.5rem;
                border-right: 1px solid rgba(255, 255, 255, 0.05);
            }
            @media (min-width: 1024px) {
                .left-pane {
                    display: flex;
                }
            }
            .right-pane {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 2.5rem;
                background-color: #0b0f19; /* Darker slate */
                overflow-y: auto;
            }
            .brand-logo {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            .brand-name {
                font-size: 1.8rem;
                font-weight: 900;
                letter-spacing: -0.05em;
                background: linear-gradient(to right, #818cf8, #c084fc);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                text-transform: uppercase;
            }
            .feature-list {
                margin-top: 4rem;
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
            }
            .feature-item {
                display: flex;
                align-items: flex-start;
                gap: 1rem;
            }
            .feature-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 2.5rem;
                height: 2.5rem;
                border-radius: 0.75rem;
                background-color: rgba(99, 102, 241, 0.15);
                color: #818cf8;
                border: 1px solid rgba(99, 102, 241, 0.2);
                flex-shrink: 0;
            }
            .feature-text h4 {
                font-size: 0.95rem;
                font-weight: 800;
                color: #ffffff;
                margin: 0;
            }
            .feature-text p {
                font-size: 0.8rem;
                color: #94a3b8;
                margin: 0.25rem 0 0 0;
                line-height: 1.4;
            }
            .card-wrapper {
                width: 100%;
                max-width: 24rem;
            }
            /* Garantizar contraste en el formulario de login */
            label {
                color: #cbd5e1 !important; /* Slate 300 */
                font-weight: 700 !important;
                font-size: 0.8rem !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                margin-bottom: 0.35rem !important;
                display: block;
            }
            input[type="email"], input[type="password"] {
                background-color: #1e293b !important; /* Slate 800 */
                color: #ffffff !important;
                border: 1px solid #475569 !important; /* Slate 600 */
                border-radius: 0.75rem !important;
                padding: 0.75rem 1rem !important;
                font-size: 0.9rem !important;
                width: 100% !important;
                transition: all 0.15s ease-in-out !important;
                box-sizing: border-box !important;
            }
            input[type="email"]:focus, input[type="password"]:focus {
                border-color: #6366f1 !important;
                outline: none !important;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25) !important;
            }
            button[type="submit"] {
                background-color: #4f46e5 !important; /* Indigo 600 */
                color: #ffffff !important;
                font-weight: 800 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                border-radius: 0.75rem !important;
                padding: 0.75rem 1.5rem !important;
                font-size: 0.8rem !important;
                border: none !important;
                cursor: pointer !important;
                transition: all 0.15s ease-in-out !important;
                width: 100% !important;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
                box-sizing: border-box !important;
            }
            button[type="submit"]:hover {
                background-color: #4338ca !important; /* Indigo 700 */
                transform: translateY(-1px) !important;
            }
            a {
                color: #818cf8 !important; /* Indigo 400 */
                font-weight: 600 !important;
                transition: color 0.15s ease-in-out !important;
                text-decoration: none !important;
            }
            a:hover {
                color: #a5b4fc !important; /* Indigo 300 */
                text-decoration: underline !important;
            }
            .checkbox-label {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                cursor: pointer;
                user-select: none;
                text-transform: none !important;
                letter-spacing: normal !important;
                font-weight: 500 !important;
                font-size: 0.85rem !important;
                color: #94a3b8 !important;
                margin-bottom: 0 !important;
            }
            input[type="checkbox"] {
                background-color: #1e293b !important;
                border: 1px solid #475569 !important;
                border-radius: 0.25rem !important;
                width: 1.1rem !important;
                height: 1.1rem !important;
                cursor: pointer !important;
                margin: 0 !important;
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="split-container">
            <!-- Left Pane: Visual Brand Info -->
            <div class="left-pane">
                <div class="brand-logo">
                    <!-- Kettlebell Icon SVG -->
                    <svg class="w-8 h-8 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v4M6 8h12M4 11v8a3 3 0 003 3h10a3 3 0 003-3v-8H4z" />
                        <path d="M7 8V6a5 5 0 0110 0v2" />
                    </svg>
                    <span class="brand-name">GymControl</span>
                </div>

                <div>
                    <h1 class="text-4xl font-extrabold text-white tracking-tight leading-tight" style="font-size: 2.25rem; font-weight: 900; line-height: 1.2;">
                        Gestión 24 horas <br>
                        <span style="color: #818cf8;">fácil e inteligente.</span>
                    </h1>
                    <p class="text-slate-400 mt-4 text-sm max-w-md" style="color: #94a3b8; font-size: 0.875rem; line-height: 1.5; margin-top: 1rem;">
                        Plataforma integral para administración de socios, asistencias en tiempo real y facturación automatizada.
                    </p>

                    <div class="feature-list">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h.01M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2zm3-3h.01M9 17h.01M9 13h.01M12 13h.01M15 13h.01M9 9h.01M12 9h.01M15 9h.01"></path>
                                </svg>
                            </div>
                            <div class="feature-text">
                                <h4>Acceso por Token y QR</h4>
                                <p>Control automatizado de ingreso con cálculo exacto de permanencia.</p>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="feature-text">
                                <h4>Membresías y Aptos Médicos</h4>
                                <p>Validación de vigencias y alertas visuales instantáneas al personal.</p>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v5.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 013 18.375v-5.25zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125v-9.75zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v14.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"></path>
                                </svg>
                            </div>
                            <div class="feature-text">
                                <h4>Estadísticas en Tiempo Real</h4>
                                <p>Dashboard dinámico con KPIs diferenciados según el rol del usuario.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-slate-500 font-semibold" style="color: #64748b; font-size: 0.75rem;">
                    &copy; {{ date('Y') }} GymControl 24hs. Todos los derechos reservados.
                </div>
            </div>

            <!-- Right Pane: Login Form Slot -->
            <div class="right-pane">
                <div class="card-wrapper">
                    <!-- Mobile Logo Branding -->
                    <div class="lg:hidden flex flex-col items-center mb-8" style="display: flex; flex-direction: column; align-items: center; margin-bottom: 2rem;">
                        <div class="flex items-center gap-2 mb-2" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <svg class="w-8 h-8 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 2rem; height: 2rem; color: #818cf8;">
                                <path d="M12 2v4M6 8h12M4 11v8a3 3 0 003 3h10a3 3 0 003-3v-8H4z" />
                                <path d="M7 8V6a5 5 0 0110 0v2" />
                            </svg>
                            <span class="brand-name" style="font-size: 1.5rem; font-weight: 900; background: linear-gradient(to right, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-transform: uppercase;">GymControl</span>
                        </div>
                        <h2 class="text-lg font-bold text-slate-300" style="font-size: 1.125rem; font-weight: 700; color: #cbd5e1; margin: 0;">Iniciar Sesión</h2>
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>

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
    </body>
</html>
