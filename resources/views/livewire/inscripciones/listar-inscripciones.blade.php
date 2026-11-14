<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Mensajes Flash de Retroalimentación -->
        @if (session()->has('message'))
            <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-950/30 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-300 rounded-r-xl font-bold text-sm shadow-md animate-pulse">
                {{ session('message') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="mb-6 p-4 bg-rose-50 dark:bg-rose-950/30 border-l-4 border-rose-500 text-rose-700 dark:text-rose-300 rounded-r-xl font-bold text-sm shadow-md">
                {{ session('error') }}
            </div>
        @endif

        <div class="main-card">
            <!-- Encabezado y Botón de Nueva Solicitud -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-slate-200 dark:border-slate-800 gap-4 mb-6">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Gestión de Inscripciones y Cupos</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Las nuevas inscripciones se confirman si hay vacantes. Las solicitudes pendientes y listas de espera corresponden a registros heredados; máximo 15 socios por grupo.</p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('inscripciones.create') }}" class="btn-primary" wire:navigate>
                        <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Inscribir socio
                    </a>
                </div>
            </div>

            <!-- Navegación por Pestañas -->
            <div class="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800 pb-4 mb-6">
                <button
                    type="button"
                    wire:click="cambiarTab('pendientes')"
                    class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition {{ $tab === 'pendientes' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }}"
                >
                    Pendientes heredadas
                    <span class="ml-1.5 px-2 py-0.5 rounded-full text-2xs {{ $tab === 'pendientes' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' }}">
                        {{ $conteoPendientes }}
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="cambiarTab('aprobadas')"
                    class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition {{ $tab === 'aprobadas' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }}"
                >
                    Inscripciones confirmadas
                    <span class="ml-1.5 px-2 py-0.5 rounded-full text-2xs {{ $tab === 'aprobadas' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' }}">
                        {{ $conteoAprobadas }}
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="cambiarTab('espera')"
                    class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition {{ $tab === 'espera' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }}"
                >
                    Lista de Espera
                    <span class="ml-1.5 px-2 py-0.5 rounded-full text-2xs {{ $tab === 'espera' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300' }}">
                        {{ $conteoEspera }}
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="cambiarTab('historial')"
                    class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition {{ $tab === 'historial' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }}"
                >
                    Bajas y Rechazos
                    <span class="ml-1.5 px-2 py-0.5 rounded-full text-2xs {{ $tab === 'historial' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">
                        {{ $conteoHistorial }}
                    </span>
                </button>
            </div>

            <!-- Filtros: Buscador, Disciplina y Grupo -->
            <div class="filters-container !shadow-none !p-0 !border-0 mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="buscar" class="form-label">Buscar socio o grupo</label>
                    <input
                        wire:model.live="buscar"
                        type="text"
                        id="buscar"
                        placeholder="Nombre, apellido, DNI o grupo..."
                        class="form-input"
                    />
                </div>
                <div>
                    <label for="disciplinaId" class="form-label">Filtrar por disciplina</label>
                    <select wire:model.live="disciplinaId" id="disciplinaId" class="form-input">
                        <option value="">Todas las disciplinas</option>
                        @foreach ($disciplinas as $d)
                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="grupoId" class="form-label">Filtrar por grupo</label>
                    <select wire:model.live="grupoId" id="grupoId" class="form-input" @disabled(!$disciplinaId)>
                        <option value="">{{ $disciplinaId ? 'Todos los grupos' : 'Primero elija disciplina' }}</option>
                        @foreach ($gruposFiltro as $g)
                            <option value="{{ $g->id }}">{{ $g->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Tabla de Datos -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr class="custom-table-head">
                            @if($tab === 'espera')
                                <th class="custom-table-th text-center">Posición</th>
                            @endif
                            <th class="custom-table-th">Socio</th>
                            <th class="custom-table-th">Grupo y Disciplina</th>
                            <th class="custom-table-th">Horarios</th>
                            <th class="custom-table-th">Cupo Grupo</th>
                            @if($tab === 'pendientes')
                                <th class="custom-table-th">Fecha Solicitud</th>
                            @elseif($tab === 'aprobadas')
                                <th class="custom-table-th">Aprobación</th>
                            @elseif($tab === 'espera')
                                <th class="custom-table-th">Fecha Espera</th>
                            @else
                                <th class="custom-table-th">Resolución</th>
                            @endif
                            <th class="custom-table-th text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($datos as $item)
                            <tr class="custom-table-row">

                                <!-- Posición en Lista de Espera -->
                                @if($tab === 'espera')
                                    <td class="custom-table-td text-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-black bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400">
                                            #{{ $item->posicion }}
                                        </span>
                                    </td>
                                @endif

                                <!-- Socio -->
                                <td class="custom-table-td">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ $item->socio->nombre_completo }}
                                    </div>
                                    <div class="text-2xs text-slate-500 dark:text-slate-400 font-semibold">
                                        DNI: {{ $item->socio->dni }}
                                    </div>
                                </td>

                                <!-- Grupo y Disciplina -->
                                <td class="custom-table-td">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ $item->grupo->nombre }}
                                    </div>
                                    <div class="text-2xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                        {{ $item->grupo->disciplina->nombre }}
                                    </div>
                                </td>

                                <!-- Horarios -->
                                <td class="custom-table-td text-xs text-slate-600 dark:text-slate-300">
                                    <div class="space-y-0.5">
                                        @foreach($item->grupo->horarios as $h)
                                            <div class="text-2xs font-semibold">
                                                {{ $h->dia_semana_label }} {{ substr($h->hora_inicio, 0, 5) }}-{{ substr($h->hora_fin, 0, 5) }}
                                            </div>
                                        @endforeach
                                    </div>
                                </td>

                                <!-- Cupo del Grupo -->
                                <td class="custom-table-td">
                                    @if($item->grupo->vacantesDisponibles() === 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-black bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200">
                                            {{ $item->grupo->texto_cupo }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-black bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200">
                                            {{ $item->grupo->texto_cupo }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Fecha / Resolución según Tab -->
                                <td class="custom-table-td text-xs text-slate-600 dark:text-slate-300">
                                    @if($tab === 'pendientes')
                                        <span>{{ $item->fecha_inscripcion ? $item->fecha_inscripcion->format('d/m/Y') : '—' }}</span>
                                    @elseif($tab === 'aprobadas')
                                        <div>{{ $item->aprobado_por ? ($item->aprobado_at ? $item->aprobado_at->format('d/m/Y H:i') : '—') : 'Inscripción directa' }}</div>
                                        @if($item->aprobado_por)
                                            <div class="text-2xs text-slate-400">Revisada por: {{ $item->aprobadoPor->name ?? 'Dueña' }}</div>
                                        @endif
                                    @elseif($tab === 'espera')
                                        <span>{{ $item->fecha_solicitud ? $item->fecha_solicitud->format('d/m/Y H:i') : '—' }}</span>
                                    @else
                                        <!-- Historial: Baja o Rechazo -->
                                        @if($item->estado === 'baja')
                                            <span class="badge-danger">Baja: {{ $item->fecha_baja ? $item->fecha_baja->format('d/m/Y') : '—' }}</span>
                                            <div class="text-2xs text-slate-500 mt-0.5">{{ $item->motivo_baja }}</div>
                                        @else
                                            <span class="badge-warning">Rechazada</span>
                                            <div class="text-2xs text-slate-500 mt-0.5">{{ $item->motivo_rechazo }}</div>
                                        @endif
                                    @endif
                                </td>

                                <!-- Acciones -->
                                <td class="custom-table-td text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        @if($tab === 'pendientes')
                                            @if(auth()->user()->rol === 'admin')
                                                <!-- Dueña: Aprobar -->
                                                <button
                                                    wire:click="aprobar({{ $item->id }})"
                                                    wire:confirm="¿Aprobar la inscripción de '{{ $item->socio->nombre_completo }}' en '{{ $item->grupo->nombre }}'?"
                                                    class="btn-action-success"
                                                    title="Aprobar inscripción (ocupa 1 plaza)"
                                                >
                                                    Aprobar
                                                </button>
                                                <!-- Dueña: Rechazar -->
                                                <button
                                                    wire:click="abrirModalRechazar({{ $item->id }})"
                                                    class="btn-action-danger"
                                                    title="Rechazar solicitud con motivo"
                                                >
                                                    Rechazar
                                                </button>
                                            @else
                                                <span class="badge-info text-2xs">Pendiente de Dueña</span>
                                            @endif

                                        @elseif($tab === 'aprobadas')
                                            <!-- Cambiar de Grupo -->
                                            <button
                                                wire:click="abrirModalCambio({{ $item->id }})"
                                                class="btn-action-edit"
                                                title="{{ auth()->user()->rol === 'admin' ? 'Cambiar a otro grupo' : 'Solicitar pase a otro grupo' }}"
                                            >
                                                {{ auth()->user()->rol === 'admin' ? 'Cambiar Grupo' : 'Solicitar Cambio' }}
                                            </button>
                                            <!-- Dar de Baja -->
                                            <button
                                                wire:click="abrirModalBaja({{ $item->id }})"
                                                class="btn-action-danger"
                                                title="{{ auth()->user()->rol === 'admin' ? 'Registrar baja autorizada' : 'Solicitar baja de grupo' }}"
                                            >
                                                {{ auth()->user()->rol === 'admin' ? 'Dar Baja' : 'Solicitar Baja' }}
                                            </button>

                                        @elseif($tab === 'espera')
                                            @if(auth()->user()->rol === 'admin')
                                                <!-- Dueña: Incorporar si hay vacantes -->
                                                @if($item->grupo->vacantesDisponibles() > 0)
                                                    @if((int) $primerasEsperas->get($item->grupo_id) === (int) $item->id)
                                                        <button
                                                            wire:click="incorporarDeEspera({{ $item->id }})"
                                                            wire:confirm="¿Incorporar a '{{ $item->socio->nombre_completo }}' a la vacante liberada en '{{ $item->grupo->nombre }}'?"
                                                            class="btn-action-success"
                                                            title="Incorporar a la plaza disponible"
                                                        >
                                                            Incorporar
                                                        </button>
                                                    @else
                                                        <button
                                                            disabled
                                                            class="opacity-40 cursor-not-allowed btn-action-view"
                                                            title="Debe respetarse el orden de la lista de espera"
                                                        >
                                                            Espera #{{ $item->posicion }}
                                                        </button>
                                                    @endif
                                                @else
                                                    <button
                                                        disabled
                                                        class="opacity-40 cursor-not-allowed btn-action-view"
                                                        title="No hay vacantes disponibles en el grupo (15/15)"
                                                    >
                                                        Sin cupo
                                                    </button>
                                                @endif

                                                <!-- Dueña: Cancelar espera -->
                                                <button
                                                    wire:click="cancelarEspera({{ $item->id }})"
                                                    wire:confirm="¿Cancelar este turno en la lista de espera?"
                                                    class="btn-action-danger"
                                                    title="Cancelar espera"
                                                >
                                                    Cancelar
                                                </button>
                                            @else
                                                <span class="badge-warning text-2xs">En Espera #{{ $item->posicion }}</span>
                                            @endif

                                        @else
                                            <!-- Historial -->
                                            <span class="text-2xs text-slate-400 italic">Registro histórico</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $tab === 'espera' ? 7 : 6 }}" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic py-8">
                                    No se encontraron registros en esta sección.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                {{ $datos->links() }}
            </div>
        </div>
    </div>

    <!-- MODAL 1: RECHAZAR INSCRIPCIÓN (Exclusivo Dueña) -->
    @if($mostrarModalRechazar)
        <div class="modal-backdrop"></div>
        <div class="modal-wrapper">
            <div class="modal-panel sm:max-w-md">
                <div class="modal-header">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">Rechazar Solicitud de Inscripción</h3>
                    <button wire:click="cerrarModalRechazar" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Indique el motivo por el cual no se aprueba la inscripción. Este mensaje quedará registrado en el historial del socio y la solicitud.
                    </p>
                    <div>
                        <label for="motivoRechazo" class="form-label">Motivo de Rechazo *</label>
                        <textarea
                            wire:model="motivoRechazo"
                            id="motivoRechazo"
                            rows="3"
                            class="form-input @error('motivoRechazo') border-rose-500 @enderror"
                            placeholder="Ej: Incompatibilidad de nivel, falta de documentación o cupo de seguridad..."
                        ></textarea>
                        @error('motivoRechazo')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" wire:click="cerrarModalRechazar" class="btn-secondary">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmarRechazo" class="btn-action-danger !py-2.5 !px-4 text-xs font-bold">
                        Confirmar Rechazo
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 2: REGISTRAR / SOLICITAR BAJA -->
    @if($mostrarModalBaja)
        <div class="modal-backdrop"></div>
        <div class="modal-wrapper">
            <div class="modal-panel sm:max-w-md">
                <div class="modal-header">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">
                        {{ auth()->user()->rol === 'admin' ? 'Registrar Baja de Grupo' : 'Solicitar Baja de Grupo' }}
                    </h3>
                    <button wire:click="cerrarModalBaja" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ auth()->user()->rol === 'admin'
                            ? 'Al efectivizar la baja, la plaza quedará liberada inmediatamente y se notificará si hay alumnos en lista de espera.'
                            : 'La solicitud de baja será enviada a la dueña para su autorización.' }}
                    </p>
                    <div>
                        <label for="motivoBaja" class="form-label">Motivo de la Baja *</label>
                        <textarea
                            wire:model="motivoBaja"
                            id="motivoBaja"
                            rows="3"
                            class="form-input @error('motivoBaja') border-rose-500 @enderror"
                            placeholder="Ej: Solicitud personal del alumno, cambio de horarios laborales..."
                        ></textarea>
                        @error('motivoBaja')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" wire:click="cerrarModalBaja" class="btn-secondary">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmarBaja" class="btn-action-danger !py-2.5 !px-4 text-xs font-bold">
                        {{ auth()->user()->rol === 'admin' ? 'Efectivizar Baja' : 'Enviar Solicitud de Baja' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3: CAMBIAR / SOLICITAR CAMBIO DE GRUPO -->
    @if($mostrarModalCambio)
        <div class="modal-backdrop"></div>
        <div class="modal-wrapper">
            <div class="modal-panel sm:max-w-lg">
                <div class="modal-header">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">
                        {{ auth()->user()->rol === 'admin' ? 'Cambio de Grupo Transaccional' : 'Solicitar Cambio de Grupo' }}
                    </h3>
                    <button wire:click="cerrarModalCambio" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <div class="modal-body space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Seleccione el nuevo grupo de destino. Se validará que tenga vacantes disponibles y que no genere colisiones horarias.
                    </p>

                    <div>
                        <label for="grupoDestinoId" class="form-label">Grupo de Destino *</label>
                        <select wire:model="grupoDestinoId" id="grupoDestinoId" class="form-input @error('grupoDestinoId') border-rose-500 @enderror">
                            <option value="">-- Seleccionar grupo de destino --</option>
                            @foreach($gruposDestinoDisponibles as $gd)
                                <option value="{{ $gd->id }}" @disabled($gd->vacantesDisponibles() === 0)>
                                    {{ $gd->disciplina->nombre }} - {{ $gd->nombre }} ({{ $gd->texto_cupo }})
                                </option>
                            @endforeach
                        </select>
                        @error('grupoDestinoId')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="motivoCambio" class="form-label">Motivo del Cambio (Opcional)</label>
                        <textarea
                            wire:model="motivoCambio"
                            id="motivoCambio"
                            rows="2"
                            class="form-input"
                            placeholder="Motivo del cambio de grupo..."
                        ></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" wire:click="cerrarModalCambio" class="btn-secondary">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmarCambio" class="btn-primary">
                        {{ auth()->user()->rol === 'admin' ? 'Confirmar Cambio de Grupo' : 'Enviar Solicitud de Cambio' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
