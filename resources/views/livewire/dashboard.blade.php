<div class="py-10 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <header class="border-b border-slate-200 dark:border-slate-700 pb-5">
            <h1 class="text-3xl font-black text-slate-900 dark:text-white">
                {{ $esDuena ? 'Panel administrativo' : 'Panel de recepción' }}
            </h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                Indicadores operativos actualizados desde los registros del gimnasio.
            </p>
        </header>

        @unless($esDuena)
            <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5 space-y-4">
                <h2 class="font-bold text-slate-900 dark:text-white">Buscar socio</h2>
                <form action="{{ route('socios.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input type="search" name="buscar" placeholder="Nombre, apellido o DNI"
                        class="form-input flex-1" aria-label="Buscar socio">
                    <button type="submit" class="btn-primary">Buscar</button>
                </form>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('control-acceso') }}" class="btn-primary">Registrar ingreso o salida</a>
                    <a href="{{ route('inscripciones.index') }}" class="btn-secondary">Inscripciones</a>
                    <a href="{{ route('solicitudes.index') }}" class="btn-secondary">Mis solicitudes</a>
                    <a href="{{ route('pagos.index') }}" class="btn-secondary">Pagos y seguimiento</a>
                </div>
            </section>
        @endunless

        @php
            $indicadores = $esDuena ? [
                ['Socios registrados', $totalSocios],
                ['Socios activos', $sociosActivos],
                ['Inscripciones pendientes', $inscripcionesPendientes],
                ['Inscripciones aprobadas', $inscripcionesAprobadas],
                ['Pagos cobrados hoy', $ingresosHoy],
                ['Recaudación de hoy', '$'.number_format($recaudacionHoy, 2, ',', '.')],
                ['Recaudación del mes', '$'.number_format($ingresosMes, 2, ',', '.')],
                ['Permanencias abiertas', $sociosEnSala],
                ['Asistencias a clase hoy', $asistenciasClasesHoy],
                ['Cuotas vigentes', $cuotasVigentes],
                ['Inscripciones con cobertura vencida', $cuotasVencidas],
                ['Solicitudes pendientes', $solicitudesPendientes],
                ['Decisiones médicas pendientes', $decisionesMedicasPendientes],
                ['Certificados faltantes o vencidos', $certificadosFaltantesOVencidos],
                ['Accesos denegados (7 días)', $accesosDenegadosRecientes],
            ] : [
                ['Socios activos', $sociosActivos],
                ['Inscripciones pendientes', $inscripcionesPendientes],
                ['Inscripciones aprobadas', $inscripcionesAprobadas],
                ['Personas en sala', $sociosEnSala],
                ['Asistencias a clase hoy', $asistenciasClasesHoy],
                ['Cuotas vigentes', $cuotasVigentes],
                ['Inscripciones con cobertura vencida', $cuotasVencidas],
                ['Mis solicitudes pendientes', $solicitudesPendientes],
            ];
        @endphp
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" aria-label="Indicadores">
            @foreach($indicadores as [$etiqueta, $valor])
                <article class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-300">{{ $etiqueta }}</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ $valor }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Cupos por grupo</h2>
                <a href="{{ route('grupos.index') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">Ver grupos</a>
            </div>
            <div class="overflow-x-auto">
                <table class="custom-table w-full">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Disciplina</th>
                            <th class="custom-table-th">Grupo</th>
                            <th class="custom-table-th">Ocupados</th>
                            <th class="custom-table-th">Disponibles</th>
                            <th class="custom-table-th">Lista de espera</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grupos as $grupo)
                            <tr class="custom-table-row">
                                <td class="custom-table-td">{{ $grupo['disciplina'] ?? '—' }}</td>
                                <td class="custom-table-td font-semibold">{{ $grupo['nombre'] }}</td>
                                <td class="custom-table-td">{{ $grupo['ocupados'] }} / {{ $grupo['cupo'] }}</td>
                                <td class="custom-table-td">{{ $grupo['disponibles'] }}</td>
                                <td class="custom-table-td">{{ $grupo['en_espera'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="custom-table-td text-center text-slate-500">Sin grupos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Permanencias abiertas</h2>
                    <a href="{{ route('control-acceso') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">Control de acceso</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="custom-table w-full">
                        <thead>
                            <tr class="custom-table-head">
                                <th class="custom-table-th">Socio</th>
                                <th class="custom-table-th">Fecha</th>
                                <th class="custom-table-th">Ingreso</th>
                                <th class="custom-table-th">Método</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($permanenciasAbiertas as $movimiento)
                                <tr class="custom-table-row">
                                    <td class="custom-table-td font-semibold">{{ $movimiento->socio?->apellido }}, {{ $movimiento->socio?->nombre }}</td>
                                    <td class="custom-table-td">{{ $movimiento->fecha?->format('d/m/Y') }}</td>
                                    <td class="custom-table-td">{{ $movimiento->hora_ingreso }}</td>
                                    <td class="custom-table-td">{{ $movimiento->metodo_acceso ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="custom-table-td text-center text-slate-500">No hay permanencias abiertas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Asistencias a clase de hoy</h2>
                    <a href="{{ route('asistencias.index') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">Ver asistencias</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="custom-table w-full">
                        <thead>
                            <tr class="custom-table-head">
                                <th class="custom-table-th">Socio</th>
                                <th class="custom-table-th">Grupo</th>
                                <th class="custom-table-th">Hora</th>
                                <th class="custom-table-th">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($asistenciasClaseRecientes as $asistencia)
                                <tr class="custom-table-row">
                                    <td class="custom-table-td font-semibold">{{ $asistencia->socio?->apellido }}, {{ $asistencia->socio?->nombre }}</td>
                                    <td class="custom-table-td">{{ $asistencia->grupo?->nombre ?? '—' }}</td>
                                    <td class="custom-table-td">{{ $asistencia->hora_marcado ?? '—' }}</td>
                                    <td class="custom-table-td">{{ ucfirst(str_replace('_', ' ', $asistencia->estado)) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="custom-table-td text-center text-slate-500">Sin asistencias registradas hoy.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Solicitudes recientes</h2>
                    <a href="{{ route('solicitudes.index') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">Ver solicitudes</a>
                </div>
                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($solicitudesRecientes as $solicitud)
                        <li class="py-3 flex items-center justify-between gap-4">
                            <span class="text-sm text-slate-700 dark:text-slate-200">
                                {{ $solicitud->socio?->apellido }}, {{ $solicitud->socio?->nombre }}
                                <span class="block text-xs text-slate-500">{{ str_replace('_', ' ', $solicitud->tipo) }}</span>
                            </span>
                            <span class="text-xs font-bold">{{ ucfirst($solicitud->estado) }}</span>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-slate-500">Sin solicitudes recientes.</li>
                    @endforelse
                </ul>
            </section>

            @if($esDuena)
                <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Accesos denegados recientes</h2>
                        <a href="{{ route('auditoria.index') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">Auditoría</a>
                    </div>
                    <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($accesosDenegados as $evento)
                            <li class="py-3 flex items-center justify-between gap-4 text-sm">
                                <span class="text-slate-700 dark:text-slate-200">{{ $evento->usuario?->name ?? 'Terminal o QR' }}</span>
                                <time class="text-slate-500">{{ $evento->created_at?->format('d/m/Y H:i') }}</time>
                            </li>
                        @empty
                            <li class="py-3 text-sm text-slate-500">Sin accesos denegados recientes.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Socios registrados recientemente</h2>
                    <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($sociosRecientes as $socio)
                            <li class="py-3 flex items-center justify-between gap-4 text-sm">
                                <a class="font-semibold text-indigo-600 dark:text-indigo-300" href="{{ route('socios.show', $socio) }}">{{ $socio->apellido }}, {{ $socio->nombre }}</a>
                                <span class="text-slate-500">{{ ucfirst($socio->estado) }}</span>
                            </li>
                        @empty
                            <li class="py-3 text-sm text-slate-500">Sin socios registrados.</li>
                        @endforelse
                    </ul>
                </section>
            @endif
        </div>

        @unless($esDuena)
            <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Últimas operaciones</h2>
                    <a href="{{ route('control-acceso') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">Ver movimientos</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="custom-table w-full">
                        <thead>
                            <tr class="custom-table-head">
                                <th class="custom-table-th">Socio</th>
                                <th class="custom-table-th">Fecha</th>
                                <th class="custom-table-th">Ingreso</th>
                                <th class="custom-table-th">Salida</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movimientosRecientes as $movimiento)
                                <tr class="custom-table-row">
                                    <td class="custom-table-td font-semibold">{{ $movimiento->socio?->apellido }}, {{ $movimiento->socio?->nombre }}</td>
                                    <td class="custom-table-td">{{ $movimiento->fecha?->format('d/m/Y') }}</td>
                                    <td class="custom-table-td">{{ $movimiento->hora_ingreso }}</td>
                                    <td class="custom-table-td">{{ $movimiento->hora_salida ?? 'Permanencia abierta' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="custom-table-td text-center text-slate-500">Sin operaciones recientes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endunless
    </div>
</div>
