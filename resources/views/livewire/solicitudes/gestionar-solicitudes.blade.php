<div class="py-8 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if (session()->has('message'))
            <div class="rounded-xl border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">
                {{ session('message') }}
            </div>
        @endif

        <section class="main-card">
            <div class="mb-6 flex flex-col gap-3 border-b border-slate-200 pb-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white">Solicitudes administrativas</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                        @if(auth()->user()->esDuena())
                            Revisá y resolvé solicitudes. Los cambios se aplican mediante los servicios autorizados.
                        @else
                            Consultá el estado de tus solicitudes y pedí una revisión médica a la dueña.
                        @endif
                    </p>
                </div>
                @if(auth()->user()->esRecepcionista())
                    <a href="{{ route('inscripciones.create') }}" class="btn-primary" wire:navigate>Nueva solicitud de inscripción</a>
                @endif
            </div>

            @if(auth()->user()->esRecepcionista())
                <form wire:submit="crearRevisionMedica" class="mb-8 grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-700 md:grid-cols-3">
                    <div>
                        <label for="socioId" class="form-label">Socio</label>
                        <select id="socioId" wire:model="socioId" class="form-input">
                            <option value="">Seleccionar socio</option>
                            @foreach($socios as $socio)
                                <option value="{{ $socio->id }}">{{ $socio->apellido }}, {{ $socio->nombre }}</option>
                            @endforeach
                        </select>
                        @error('socioId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="motivo" class="form-label">Motivo administrativo de revisión</label>
                        <textarea id="motivo" wire:model="motivo" rows="2" maxlength="1000" class="form-input" placeholder="Describí brevemente qué necesita revisar la dueña. No incluyas diagnósticos ni datos médicos."></textarea>
                        @error('motivo') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="btn-primary">Enviar revisión médica</button>
                        <p class="mt-2 text-xs text-slate-500">La solicitud no cambia la decisión médica ni habilita el acceso.</p>
                    </div>
                </form>
            @endif

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label for="estadoFiltro" class="form-label">Estado</label>
                    <select id="estadoFiltro" wire:model.live="estadoFiltro" class="form-input">
                        <option value="pendiente">Pendientes</option>
                        <option value="aprobada">Aprobadas</option>
                        <option value="rechazada">Rechazadas</option>
                        <option value="cancelada">Canceladas</option>
                        <option value="todas">Todas</option>
                    </select>
                </div>
                <div>
                    <label for="buscar" class="form-label">Buscar socio o tipo</label>
                    <input id="buscar" wire:model.live.debounce.300ms="buscar" class="form-input" placeholder="Nombre o tipo de solicitud">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="custom-table min-w-full">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Solicitud</th>
                            <th class="custom-table-th">Socio</th>
                            <th class="custom-table-th">Solicitante</th>
                            <th class="custom-table-th">Creación</th>
                            <th class="custom-table-th">Estado y resolución</th>
                            @if(auth()->user()->esDuena())
                                <th class="custom-table-th text-right">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($solicitudes as $solicitud)
                            <tr>
                                <td class="custom-table-td">
                                    <span class="font-bold text-slate-900 dark:text-white">{{ str_replace('_', ' ', ucfirst($solicitud->tipo)) }}</span>
                                    @if($solicitud->tipo !== \App\Models\Solicitud::TIPO_EXCEPCION_MEDICA)
                                        <span class="mt-1 block max-w-md text-xs text-slate-500 dark:text-slate-300">{{ $solicitud->descripcion }}</span>
                                    @else
                                        <span class="mt-1 block text-xs text-slate-500">Revisión de requisito médico (contenido restringido)</span>
                                    @endif
                                    @if($solicitud->grupo || $solicitud->grupoDestino)
                                        <span class="mt-1 block text-xs text-slate-500">
                                            {{ $solicitud->grupo?->nombre }}
                                            @if($solicitud->grupoDestino) → {{ $solicitud->grupoDestino->nombre }} @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="custom-table-td">{{ $solicitud->socio?->nombre }} {{ $solicitud->socio?->apellido }}</td>
                                <td class="custom-table-td">{{ $solicitud->solicitante?->name ?? 'Usuario no disponible' }}</td>
                                <td class="custom-table-td">{{ $solicitud->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="custom-table-td">
                                    <span class="font-bold">{{ ucfirst($solicitud->estado) }}</span>
                                    @if($solicitud->fecha_decision)
                                        <span class="mt-1 block text-xs text-slate-500">{{ $solicitud->fecha_decision->format('d/m/Y H:i') }} · {{ $solicitud->revisadoPor?->name }}</span>
                                        @if($solicitud->respuesta_duena)
                                            <span class="mt-1 block max-w-sm text-xs text-slate-500">
                                                {{ $solicitud->tipo === \App\Models\Solicitud::TIPO_EXCEPCION_MEDICA && auth()->user()->esRecepcionista()
                                                    ? 'La decisión fue registrada por la dueña.'
                                                    : $solicitud->respuesta_duena }}
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                @if(auth()->user()->esDuena())
                                    <td class="custom-table-td text-right">
                                        @if($solicitud->estaPendiente())
                                            <button type="button" wire:click="abrirResolucion({{ $solicitud->id }})" class="btn-action-edit">Resolver</button>
                                        @else
                                            <span class="text-xs text-slate-400">Historial</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()->esDuena() ? 6 : 5 }}" class="px-6 py-10 text-center text-sm text-slate-500">No hay solicitudes para este filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $solicitudes->links() }}</div>
        </section>

        @if(auth()->user()->esRecepcionista())
            <section class="main-card">
                <h2 class="text-lg font-black text-slate-900 dark:text-white">Mis últimas operaciones</h2>
                <p class="mb-4 text-xs text-slate-500">Hasta 50 acciones registradas por tu cuenta. Este panel no expone la auditoría administrativa completa.</p>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($movimientosRecepcion as $movimiento)
                        <div class="flex flex-wrap justify-between gap-2 py-3 text-sm">
                            <span class="font-semibold">{{ str_replace('_', ' ', ucfirst($movimiento->accion)) }}</span>
                            <span class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $movimiento->tipo_evento)) }} · {{ $movimiento->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">Todavía no hay operaciones para mostrar.</p>
                    @endforelse
                </div>
            </section>
        @endif

        @if($solicitudId && auth()->user()->esDuena())
            @php($seleccionada = \App\Models\Solicitud::query()->find($solicitudId))
            @php($contextoSeleccionada = $seleccionada?->contextoCorreccion() ?? [])
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="resolve-title">
                <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <h2 id="resolve-title" class="text-xl font-black text-slate-900 dark:text-white">Resolver solicitud</h2>
                            <p class="mt-1 text-sm text-slate-500">La aprobación ejecuta la operación funcional dentro de una transacción.</p>
                        </div>
                        <button type="button" wire:click="$set('solicitudId', null)" class="text-slate-500" aria-label="Cerrar">✕</button>
                    </div>

                    @if($seleccionada?->tipo === \App\Models\Solicitud::TIPO_EXCEPCION_MEDICA)
                        <label for="decisionMedica" class="form-label">Decisión médica a aplicar</label>
                        <select id="decisionMedica" wire:model="decisionMedica" class="form-input mb-4">
                            <option value="">Seleccionar (solo al aprobar)</option>
                            <option value="requerido">Requiere certificado</option>
                            <option value="no_requerido">No requiere certificado</option>
                        </select>
                        @error('decisionMedica') <span class="mb-3 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        <p class="mb-4 text-xs text-slate-500">La solicitud no decide por sí misma. La resolución requiere esta selección explícita y no altera PIN ni QR.</p>
                    @endif

                    @if(($contextoSeleccionada['recurso'] ?? null) === 'permanencia')
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div><label class="form-label">Fecha de ingreso</label><input type="date" wire:model="correccionFechaIngreso" class="form-input"></div>
                            <div><label class="form-label">Hora de ingreso</label><input type="time" step="1" wire:model="correccionHoraIngreso" class="form-input"></div>
                            <div><label class="form-label">Fecha de salida (vacía si sigue abierta)</label><input type="date" wire:model="correccionFechaSalida" class="form-input"></div>
                            <div><label class="form-label">Hora de salida</label><input type="time" step="1" wire:model="correccionHoraSalida" class="form-input"></div>
                        </div>
                    @elseif(($contextoSeleccionada['recurso'] ?? null) === 'clase')
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="form-label">Estado de asistencia</label>
                                <select wire:model="correccionEstadoClase" class="form-input">
                                    <option value="">Seleccionar</option>
                                    @foreach(['puntual', 'tarde', 'pendiente', 'ausencia_confirmada', 'ausente_justificado', 'cancelado'] as $estado)
                                        <option value="{{ $estado }}">{{ str_replace('_', ' ', ucfirst($estado)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="form-label">Hora de presentación</label><input type="time" step="1" wire:model="correccionHoraClase" class="form-input"></div>
                        </div>
                    @endif

                    <label for="respuesta" class="form-label mt-4">Observación de resolución</label>
                    <textarea id="respuesta" wire:model="respuesta" rows="3" maxlength="1000" class="form-input" placeholder="Motivo de aprobación o rechazo"></textarea>
                    @error('respuesta') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror

                    <div class="mt-6 flex flex-wrap justify-end gap-3">
                        <button type="button" wire:click="resolver('rechazada')" class="rounded-xl border border-rose-300 px-4 py-2 font-bold text-rose-700">Rechazar</button>
                        <button type="button" wire:click="resolver('aprobada')" class="btn-primary">Aprobar y aplicar</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
