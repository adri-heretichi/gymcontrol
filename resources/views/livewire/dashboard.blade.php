<div wire:poll.5s class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        
        @if(auth()->check() && auth()->user()->rol === 'admin')
            <!-- ================= DASHBOARD ADMINISTRADOR ================= -->
            <div class="pb-6 border-b border-slate-200 dark:border-slate-800 mb-8">
                <h3 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight uppercase">Panel Administrativo</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-semibold">GymControl - Indicadores globales de gestión y accesos del gimnasio.</p>
            </div>

            <!-- Tarjetas de Indicadores KPI -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <!-- Total Socios -->
                <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between hover:shadow-2xl transition duration-150">
                    <div>
                        <span class="block text-2xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-300">Total Socios</span>
                        <span class="text-3xl font-black text-slate-950 dark:text-white mt-2 block">{{ $totalSocios }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.386 11.386 0 0110.089 20M3 11.627a9.009 9.009 0 013-3.727m0 0a9.009 9.009 0 019-1.282m-9 5a9.009 9.009 0 002.518-5.57m3.482 12.352A11.387 11.387 0 013 16.291m0 0v-2.289a4.125 4.125 0 013-4.044m0 0a8.997 8.997 0 017.843 4.582M12 3a3 3 0 100 6 3 3 0 000-6zM21 12a3 3 0 11-6 0 3 3 0 016 0zM6 18a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Socios Activos -->
                <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between hover:shadow-2xl transition duration-150">
                    <div>
                        <span class="block text-2xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-300">Socios Activos</span>
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-2 block">{{ $sociosActivos }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Membresías Vencidas -->
                <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between hover:shadow-2xl transition duration-150">
                    <div>
                        <span class="block text-2xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-300">Vencidos</span>
                        <span class="text-3xl font-black text-rose-600 dark:text-rose-400 mt-2 block">{{ $sociosVencidos }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286zm0 13.036h.008v.008H12v-.008z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Recaudación Mes -->
                <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between hover:shadow-2xl transition duration-150">
                    <div>
                        <span class="block text-2xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-300">Ingresos Mes</span>
                        <span class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-2 block">${{ number_format($ingresosMes, 0, ',', '.') }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.214.116C10.158 16.1 11.087 16 12 16c2.5 0 4.5-1.125 4.5-2.5S14.5 11 12 11c-2.5 0-4.5-1.125-4.5-2.5S9.5 6 12 6c.913 0 1.842.1 2.786.302l.214.116M6 12h12"></path>
                        </svg>
                    </div>
                </div>

                <!-- Socios en Sala -->
                <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between hover:shadow-2xl transition duration-150">
                    <div>
                        <span class="block text-2xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-300">En Sala</span>
                        <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-2 block">{{ $sociosEnSala }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Tablas de Datos Cruzados -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Últimas Asistencias -->
                <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-6">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4 mb-4">
                        <h4 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-wider">Accesos Recientes en Sala</h4>
                        <span class="text-2xs font-extrabold text-indigo-500 bg-indigo-50 dark:bg-indigo-950 px-2 py-1 rounded-md">Últimos 5</span>
                    </div>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr class="custom-table-head">
                                    <th class="custom-table-th">Socio</th>
                                    <th class="custom-table-th">Fecha</th>
                                    <th class="custom-table-th">Ingreso</th>
                                    <th class="custom-table-th">Salida</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($ultimasAsistencias as $asistencia)
                                    <tr class="custom-table-row">
                                        <td class="custom-table-td font-bold text-slate-800 dark:text-white">
                                            {{ $asistencia->socio?->apellido }}, {{ $asistencia->socio?->nombre }}
                                        </td>
                                        <td class="custom-table-td">{{ $asistencia->fecha->format('d/m/Y') }}</td>
                                        <td class="custom-table-td font-mono text-xs">{{ $asistencia->hora_ingreso }}</td>
                                        <td class="custom-table-td font-mono text-xs">
                                            @if($asistencia->hora_salida)
                                                {{ $asistencia->hora_salida }}
                                            @else
                                                <span class="badge-success">EN SALA</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">No hay accesos registrados hoy.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Últimos Socios Registrados -->
                <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-6">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4 mb-4">
                        <h4 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-wider">Últimos Socios Registrados</h4>
                        <span class="text-2xs font-extrabold text-indigo-500 bg-indigo-50 dark:bg-indigo-950 px-2 py-1 rounded-md">Últimos 5</span>
                    </div>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr class="custom-table-head">
                                    <th class="custom-table-th">Socio</th>
                                    <th class="custom-table-th">Membresía</th>
                                    <th class="custom-table-th">Alta</th>
                                    <th class="custom-table-th">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($ultimosSocios as $soc)
                                    <tr class="custom-table-row">
                                        <td class="custom-table-td font-bold text-slate-800 dark:text-white">
                                            {{ $soc->apellido }}, {{ $soc->nombre }}
                                        </td>
                                        <td class="custom-table-td">
                                            <span class="badge-info">
                                                {{ $soc->membresia?->nombre ?? 'Sin membresía' }}
                                            </span>
                                        </td>
                                        <td class="custom-table-td text-xs">{{ $soc->fecha_alta ? $soc->fecha_alta->format('d/m/Y') : 'N/A' }}</td>
                                        <td class="custom-table-td">
                                            @if($soc->estado === 'activo')
                                                <span class="badge-success">Activo</span>
                                            @else
                                                <span class="badge-danger">Inactivo</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">No hay socios registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Accesos Rápidos -->
            <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-6 mt-8">
                <h4 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-wider mb-4">Accesos Rápidos Administrativos</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="{{ route('socios.index') }}" class="p-4 bg-slate-50 dark:bg-slate-900/40 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 rounded-2xl border border-slate-150 dark:border-slate-700/80 hover:border-indigo-200 transition text-center block">
                        <span class="block font-extrabold text-sm text-indigo-600 dark:text-indigo-400">Ver Socios</span>
                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-300 block mt-1">Listado y Fichas</span>
                    </a>
                    <a href="{{ route('socios.create') }}" class="p-4 bg-slate-50 dark:bg-slate-900/40 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 rounded-2xl border border-slate-150 dark:border-slate-700/80 hover:border-indigo-200 transition text-center block">
                        <span class="block font-extrabold text-sm text-indigo-600 dark:text-indigo-400">Registrar Socio</span>
                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-300 block mt-1">Nuevo Miembro</span>
                    </a>
                </div>
            </div>

        @else
            <!-- ================= DASHBOARD RECEPCIONISTA ================= -->
            <div class="pb-6 border-b border-slate-200 dark:border-slate-800 mb-8">
                <h3 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight uppercase">Panel de Recepción</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-semibold">GymControl - Operación diaria, búsqueda de socios y control de accesos en sala.</p>
            </div>

            <!-- Buscador Rápido de Socios -->
            <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-8 mb-8">
                <div class="mb-4">
                    <h4 class="text-xs font-black text-indigo-500 dark:text-indigo-400 uppercase tracking-widest">Buscador Rápido de Socio</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-300 mt-1 font-semibold">Ingresa el nombre, apellido, DNI, Celular o Token del socio para buscarlo al instante.</p>
                </div>
                <form action="{{ route('socios.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="buscar" placeholder="Buscar por Nombre, DNI, Token..." class="form-input flex-1" required />
                    <button type="submit" class="btn-primary">
                        Buscar Socio
                    </button>
                </form>
            </div>

            <!-- Indicadores Operativos -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Socios Activos -->
                <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between hover:shadow-2xl transition duration-150">
                    <div>
                        <span class="block text-2xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-300">Socios Activos Totales</span>
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-2 block">{{ $sociosActivos }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Socios en Sala -->
                <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between hover:shadow-2xl transition duration-150">
                    <div>
                        <span class="block text-2xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-300">Socios en Sala (Actualmente)</span>
                        <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-2 block">{{ $sociosEnSala }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Accesos Recientes en Sala -->
            <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 p-6">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4 mb-4">
                    <h4 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-wider">Últimos Accesos del Día</h4>
                    <span class="text-2xs font-extrabold text-indigo-500 bg-indigo-50 dark:bg-indigo-950 px-2 py-1 rounded-md">Últimos 5</span>
                </div>
                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr class="custom-table-head">
                                <th class="custom-table-th">Socio</th>
                                <th class="custom-table-th">DNI / Token</th>
                                <th class="custom-table-th">Membresía</th>
                                <th class="custom-table-th">Hora Entrada</th>
                                <th class="custom-table-th">Estado Entrada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($ultimasAsistencias as $asistencia)
                                <tr class="custom-table-row">
                                    <td class="custom-table-td font-bold text-slate-800 dark:text-white">
                                        {{ $asistencia->socio?->apellido }}, {{ $asistencia->socio?->nombre }}
                                    </td>
                                    <td class="custom-table-td">
                                        <span class="block text-xs text-slate-700 dark:text-slate-300">DNI: {{ $asistencia->socio?->dni }}</span>
                                        <span class="block text-[10px] font-mono text-slate-400 mt-0.5">PIN: {{ $asistencia->socio?->token }}</span>
                                    </td>
                                    <td class="custom-table-td">
                                        <span class="badge-info">
                                            {{ $asistencia->socio?->membresia?->nombre ?? 'Sin membresía' }}
                                        </span>
                                    </td>
                                    <td class="custom-table-td font-mono font-bold">{{ $asistencia->hora_ingreso }}</td>
                                    <td class="custom-table-td">
                                        @if($asistencia->hora_salida)
                                            <span class="text-slate-500 dark:text-slate-300 font-semibold">Salió ({{ $asistencia->hora_salida }})</span>
                                        @else
                                            <span class="badge-success animate-pulse">PRESENTE</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">
                                        No se registran ingresos en el día de hoy.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-8">
                <a href="{{ route('socios.index') }}" class="btn-primary">
                    Ver Todos los Socios
                </a>
            </div>
        @endif

    </div>
</div>
