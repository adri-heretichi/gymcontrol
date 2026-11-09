<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Mensajes Flash de Retroalimentación -->
        @if (session()->has('message'))
            <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-950/30 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-300 rounded-r-xl font-bold text-sm shadow-md animate-pulse">
                {{ session('message') }}
            </div>
        @endif

        <!-- Encabezado -->
        <div class="pb-6 border-b border-slate-200 dark:border-slate-800 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Historial de Asistencias</h2>
                <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Registro histórico de ingresos, egresos y tiempos de permanencia en el establecimiento.</p>
            </div>
            <div class="flex-shrink-0">
                <a href="{{ route('reportes.asistencias', ['buscar' => $search, 'fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta, 'estado' => $estado]) }}" target="_blank" class="btn-primary">
                    <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Exportar PDF
                </a>
            </div>
        </div>

        <!-- Banner del Clima (Open-Meteo) -->
        @if ($climaCargado)
            <div class="mb-8 p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-center text-3xl shadow-inner">
                        {{ $iconoClima }}
                    </div>
                    <div>
                        <h4 class="text-base font-extrabold text-slate-950 dark:text-white">Clima actual en Formosa</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-0.5">{{ $descripcionClima }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-6 sm:gap-8 border-t sm:border-t-0 border-slate-100 dark:border-slate-800 pt-3 sm:pt-0">
                    <div>
                        <span class="block text-2xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Temperatura</span>
                        <span class="text-lg font-black text-slate-900 dark:text-white mt-0.5 block">{{ $temperatura }} °C</span>
                    </div>
                    <div class="border-l border-slate-150 dark:border-slate-800 pl-6 sm:pl-8">
                        <span class="block text-2xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Humedad</span>
                        <span class="text-lg font-black text-slate-900 dark:text-white mt-0.5 block">{{ $humedad }} %</span>
                    </div>
                    <div class="border-l border-slate-150 dark:border-slate-800 pl-6 sm:pl-8">
                        <span class="block text-2xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Viento</span>
                        <span class="text-lg font-black text-slate-900 dark:text-white mt-0.5 block">{{ $viento }} km/h</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Filtros y Búsqueda -->
        <div class="filters-container">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                
                <!-- Buscador de Socio -->
                <div>
                    <label for="search" class="form-label">Buscar Socio</label>
                    <input 
                        type="text" 
                        id="search"
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Nombre, DNI..." 
                        class="form-input"
                    />
                </div>

                <!-- Fecha Desde -->
                <div>
                    <label for="fechaDesde" class="form-label">Fecha Desde</label>
                    <input 
                        type="date" 
                        id="fechaDesde"
                        wire:model.live="fechaDesde" 
                        class="form-input"
                    />
                </div>

                <!-- Fecha Hasta -->
                <div>
                    <label for="fechaHasta" class="form-label">Fecha Hasta</label>
                    <input 
                        type="date" 
                        id="fechaHasta"
                        wire:model.live="fechaHasta" 
                        class="form-input"
                    />
                </div>

                <!-- Estado en Sala -->
                <div>
                    <label for="estado" class="form-label">Estado de Permanencia</label>
                    <select 
                        id="estado"
                        wire:model.live="estado" 
                        class="form-input"
                    >
                        <option value="todos">Todos</option>
                        <option value="en_sala">En sala / Activas</option>
                        <option value="finalizados">Completadas</option>
                    </select>
                </div>

            </div>
        </div>

        <section class="mt-8 space-y-6">
            @if(auth()->user()->esDuena())
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">Configuración de tardanzas</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Sin tolerancia configurada, las presentaciones posteriores al inicio quedan pendientes de determinación.
                    </p>
                    <form wire:submit.prevent="guardarTolerancia" class="mt-4 flex flex-wrap items-end gap-3">
                        <div>
                            <label for="tolerancia-tardanza" class="form-label">Tolerancia en minutos (vacío = sin definir)</label>
                            <input id="tolerancia-tardanza" type="number" min="0" max="1440"
                                   wire:model="toleranciaTardanzaMinutos" class="form-input w-48">
                            @error('toleranciaTardanzaMinutos') <span class="text-sm text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="btn-primary">Guardar configuración</button>
                        <span class="text-sm text-slate-500">
                            Actual: {{ $toleranciaConfigurada === null ? 'sin definir' : $toleranciaConfigurada . ' min' }}
                        </span>
                    </form>

                    <div class="mt-6 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <h4 class="font-bold text-slate-800 dark:text-white">Confirmar ausencias de una clase finalizada hoy</h4>
                        <p class="mt-1 text-sm text-slate-500">La selección siempre corresponde a un grupo y horario concretos; no se procesan fechas históricas.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($gruposActivos as $grupo)
                                @foreach($grupo->horarios as $horario)
                                    <button type="button"
                                            wire:click="confirmarAusencias({{ $grupo->id }}, '{{ $horario->hora_inicio }}')"
                                            wire:loading.attr="disabled"
                                            class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-bold text-amber-800 dark:border-amber-800 dark:text-amber-200">
                                        {{ $grupo->nombre }} · {{ $horario->dia_semana }} {{ substr($horario->hora_inicio, 0, 5) }}
                                    </button>
                                @endforeach
                            @endforeach
                        </div>
                        @error('fecha') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                        @error('grupo') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900 dark:bg-indigo-950/30">
                <h3 class="text-lg font-black text-slate-900 dark:text-white">Permanencias abiertas</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Un ingreso físico puede vincularse con varias clases independientes.</p>
                @if($mensajeClase)
                    <p class="mt-3 font-bold text-emerald-700 dark:text-emerald-300" role="status">{{ $mensajeClase }}</p>
                @endif
                @error('grupoClaseSeleccionado') <p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p> @enderror
                <div class="mt-4 space-y-3">
                    @forelse($permanenciasAbiertas as $permanencia)
                        <div class="rounded-xl bg-white p-4 dark:bg-slate-900">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-bold text-slate-900 dark:text-white">
                                    {{ $permanencia->socio->nombre }} {{ $permanencia->socio->apellido }}
                                </span>
                                <span class="text-sm text-slate-500">
                                    Ingreso {{ $permanencia->fecha->format('d/m/Y') }} {{ $permanencia->hora_ingreso }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($permanencia->socio->inscripciones->where('estado', 'aprobada') as $inscripcion)
                                    @if($inscripcion->grupo && $inscripcion->grupo->estado === 'activo')
                                        <button type="button"
                                                wire:click="marcarAsistenciaClase({{ $permanencia->id }}, {{ $inscripcion->grupo->id }})"
                                                wire:loading.attr="disabled"
                                                class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-bold text-white disabled:opacity-50">
                                            Registrar {{ $inscripcion->grupo->disciplina?->nombre }} · {{ $inscripcion->grupo->nombre }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No hay permanencias abiertas.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-lg font-black text-slate-900 dark:text-white">Asistencias por clase</h3>
                <div class="mt-3 max-w-xs">
                    <label for="estado-clase" class="form-label">Estado de clase</label>
                    <select id="estado-clase" wire:model.live="estadoClase" class="form-input">
                        <option value="todos">Todos</option>
                        <option value="puntual">Puntuales</option>
                        <option value="tarde">Tardanzas</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="ausencia_confirmada">Ausencias confirmadas</option>
                    </select>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="custom-table">
                        <thead><tr class="custom-table-head">
                            <th class="custom-table-th">Socio</th>
                            <th class="custom-table-th">Clase / Grupo</th>
                            <th class="custom-table-th">Fecha y horario</th>
                            <th class="custom-table-th">Presentación</th>
                            <th class="custom-table-th">Estado</th>
                            <th class="custom-table-th">Registro</th>
                            <th class="custom-table-th">Acciones</th>
                        </tr></thead>
                        <tbody>
                            @forelse($asistenciasClases as $clase)
                                <tr class="custom-table-row">
                                    <td class="custom-table-td">{{ $clase->socio->nombre }} {{ $clase->socio->apellido }}</td>
                                    <td class="custom-table-td">{{ $clase->grupo->disciplina?->nombre }} · {{ $clase->grupo->nombre }}</td>
                                    <td class="custom-table-td">
                                        {{ $clase->fecha->format('d/m/Y') }}
                                        {{ $clase->hora_inicio_programada ? substr($clase->hora_inicio_programada, 0, 5) . '–' . substr($clase->hora_fin_programada, 0, 5) : 'Horario histórico no disponible' }}
                                    </td>
                                    <td class="custom-table-td">{{ $clase->hora_marcado ? substr($clase->hora_marcado, 0, 5) : '—' }}</td>
                                    <td class="custom-table-td">{{ str_replace('_', ' ', ucfirst($clase->estado)) }}</td>
                                    <td class="custom-table-td">{{ $clase->origen_registro ?? 'histórico' }}</td>
                                    <td class="custom-table-td">
                                        @if(auth()->user()->esDuena())
                                            <button type="button" wire:click="editarAsistenciaClase({{ $clase->id }})" class="btn-action-edit">Corregir</button>
                                        @else
                                            <button type="button" wire:click="prepararSolicitudCorreccion('clase', {{ $clase->id }})" class="btn-action-edit">Solicitar corrección</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="custom-table-td text-center">Todavía no hay asistencias por clase.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $asistenciasClases->links() }}</div>
            </div>

            @if($tipoCorreccion !== '')
                <form wire:submit.prevent="solicitarCorreccion('{{ $tipoCorreccion }}', {{ $registroCorreccionId }}, descripcionCorreccion)"
                      class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
                    <label for="descripcion-correccion" class="form-label">Motivo de la solicitud para la dueña</label>
                    <textarea id="descripcion-correccion" wire:model="descripcionCorreccion" minlength="10" maxlength="1000" required class="form-input"></textarea>
                    <button type="submit" class="btn-primary mt-3">Enviar solicitud</button>
                </form>
            @endif
            @if($mensajeCorreccion)
                <p class="font-bold text-emerald-700 dark:text-emerald-300" role="status">{{ $mensajeCorreccion }}</p>
            @endif
        </section>

        <!-- Tabla de Asistencias -->
        <div class="table-container">
            <table class="custom-table">
                <thead>
                    <tr class="custom-table-head">
                        <th class="custom-table-th">Socio</th>
                        <th class="custom-table-th">Fecha</th>
                        <th class="custom-table-th">Hora Ingreso</th>
                        <th class="custom-table-th">Salida</th>
                        <th class="custom-table-th">Permanencia</th>
                        @if(auth()->user()->esDuena() || auth()->user()->esRecepcionista())
                            <th class="custom-table-th text-right">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($asistencias as $asistencia)
                        <tr class="custom-table-row">
                            <!-- Socio -->
                            <td class="custom-table-td">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900 flex items-center justify-center text-xs font-black text-indigo-600 dark:text-indigo-400">
                                        {{ strtoupper(substr($asistencia->socio->nombre, 0, 1) . substr($asistencia->socio->apellido, 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('socios.show', $asistencia->socio->id) }}" class="text-sm font-extrabold text-slate-950 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline leading-tight">
                                            {{ $asistencia->socio->nombre }} {{ $asistencia->socio->apellido }}
                                        </a>
                                        <span class="block text-2xs font-semibold text-slate-500 dark:text-slate-300 mt-0.5">DNI: {{ $asistencia->socio->dni }}</span>
                                    </div>
                                </div>
                            </td>
                            <!-- Fecha -->
                            <td class="custom-table-td">
                                {{ $asistencia->fecha->format('d/m/Y') }}
                            </td>
                            <!-- Hora Ingreso -->
                            <td class="custom-table-td">
                                {{ \Carbon\Carbon::parse($asistencia->hora_ingreso)->format('H:i:s') }}
                            </td>
                            <!-- Hora Salida -->
                            <td class="custom-table-td">
                                @if($asistencia->hora_salida)
                                    {{ $asistencia->fecha_salida?->format('d/m/Y') ?? $asistencia->fecha->format('d/m/Y') }}
                                    {{ \Carbon\Carbon::parse($asistencia->hora_salida)->format('H:i:s') }}
                                @else
                                    <span class="badge-success">
                                        En sala
                                    </span>
                                @endif
                            </td>
                            <!-- Permanencia -->
                            <td class="custom-table-td">
                                @if($asistencia->hora_salida)
                                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                                        {{ $asistencia->permanencia_formateada }}
                                    </span>
                                @else
                                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $asistencia->permanencia_formateada }}
                                    </span>
                                @endif
                            </td>
                            <!-- Correcciones auditadas o solicitud de revisión -->
                            @if(auth()->user()->esDuena() || auth()->user()->esRecepcionista())
                                <td class="custom-table-td text-right">
                                    @if(auth()->user()->esDuena())
                                        <button wire:click="edit({{ $asistencia->id }})" class="btn-action-edit">Corregir</button>
                                    @else
                                        <button wire:click="prepararSolicitudCorreccion('permanencia', {{ $asistencia->id }})" class="btn-action-edit">Solicitar corrección</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 italic">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-slate-300 dark:text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-xs font-semibold">No se encontraron registros de asistencias para los filtros aplicados.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Paginación -->
            <div class="px-6 py-4 bg-slate-50/75 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-800">
                {{ $asistencias->links() }}
            </div>
        </div>

        <!-- Modal de Edición de Asistencia (Solo Administradores) -->
        @if ($showEditModal && auth()->user()->esDuena())
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="modal-wrapper">
                    
                    <!-- Fondo Oscuro -->
                    <div class="modal-backdrop" wire:click="closeEdit" aria-hidden="true"></div>

                    <!-- Truco para centrar modal -->
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <!-- Contenedor del Modal -->
                    <div class="modal-panel">
                        
                        <!-- Cabecera -->
                        <div class="modal-header">
                            <div>
                                <h3 class="text-xl font-black text-slate-900 dark:text-white">Editar Asistencia</h3>
                                <p class="text-xs text-slate-400 dark:text-slate-500 font-bold mt-0.5">Socio: {{ $socioNombre }}</p>
                            </div>
                            <button wire:click="closeEdit" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Formulario -->
                        <div class="modal-body">
                            <!-- Fecha -->
                            <div>
                                <label for="fecha" class="form-label">Fecha</label>
                                <input 
                                    type="date" 
                                    id="fecha" 
                                    wire:model="fecha" 
                                    class="form-input" 
                                />
                                @error('fecha') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Hora de Ingreso -->
                            <div>
                                <label for="hora_ingreso" class="form-label">Hora de Ingreso</label>
                                <input 
                                    type="time" 
                                    id="hora_ingreso" 
                                    step="1"
                                    wire:model="hora_ingreso" 
                                    class="form-input" 
                                />
                                @error('hora_ingreso') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="fecha_salida" class="form-label">Fecha de Salida</label>
                                <input type="date" id="fecha_salida" wire:model="fecha_salida" class="form-input" />
                                @error('fecha_salida') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Hora de Salida -->
                            <div>
                                <label for="hora_salida" class="form-label">Hora de Salida (Dejar vacío si sigue en sala)</label>
                                <input 
                                    type="time" 
                                    id="hora_salida" 
                                    step="1"
                                    wire:model="hora_salida" 
                                    class="form-input" 
                                />
                                @error('hora_salida') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Botonera / Footer -->
                        <div class="modal-footer">
                            <button 
                                wire:click="closeEdit" 
                                class="btn-secondary"
                            >
                                Cancelar
                            </button>
                            <button 
                                wire:click="save" 
                                class="btn-primary"
                            >
                                Guardar Cambios
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        @endif

        @if ($showClassEditModal && auth()->user()->esDuena())
            <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                <div class="modal-wrapper">
                    <div class="modal-backdrop" wire:click="$set('showClassEditModal', false)" aria-hidden="true"></div>
                    <div class="modal-panel">
                        <div class="modal-header">
                            <h3 class="text-xl font-black text-slate-900 dark:text-white">Corregir asistencia de clase</h3>
                            <button wire:click="$set('showClassEditModal', false)" class="text-slate-500">Cerrar</button>
                        </div>
                        <div class="modal-body">
                            <div>
                                <label for="classAttendanceStatus" class="form-label">Estado</label>
                                <select id="classAttendanceStatus" wire:model="classAttendanceStatus" class="form-input">
                                    <option value="puntual">Puntual</option>
                                    <option value="tarde">Tarde</option>
                                    <option value="pendiente">Pendiente de determinar</option>
                                    <option value="ausencia_confirmada">Ausencia confirmada</option>
                                    <option value="ausente_justificado">Ausencia justificada (histórico)</option>
                                    <option value="cancelado">Cancelada</option>
                                </select>
                            </div>
                            <div>
                                <label for="classAttendanceTime" class="form-label">Hora real de presentación</label>
                                <input id="classAttendanceTime" type="time" step="1" wire:model="classAttendanceTime" class="form-input">
                                @error('classAttendanceTime') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button wire:click="$set('showClassEditModal', false)" class="btn-secondary">Cancelar</button>
                            <button wire:click="guardarCorreccionClase" class="btn-primary">Guardar corrección</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
