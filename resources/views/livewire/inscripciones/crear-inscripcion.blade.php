<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="main-card">
            <!-- Encabezado -->
            <div class="pb-6 border-b border-slate-200 dark:border-slate-800 mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Inscribir socio en un grupo</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-semibold">La inscripción se confirma directamente si hay vacantes; la habilitación de ingreso se evalúa por separado.</p>
                </div>
                <a href="{{ route('inscripciones.index') }}" class="btn-secondary" wire:navigate>
                    Volver
                </a>
            </div>

            <form wire:submit.prevent="guardar" class="space-y-6">

                <!-- 1. Búsqueda y Selección de Socio -->
                <div class="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <label class="form-label !mb-2">1. Seleccionar Socio *</label>

                    @if($socio_id)
                        <!-- Socio ya seleccionado -->
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 rounded-xl border border-indigo-200 dark:border-indigo-900">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/60 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-bold">
                                    {{ substr($buscarSocio, 0, 1) }}
                                </div>
                                <div>
                                    <span class="text-sm font-bold text-slate-900 dark:text-white block">{{ $buscarSocio }}</span>
                                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">Socio activo seleccionado</span>
                                </div>
                            </div>
                            <button type="button" wire:click="limpiarSocio" class="btn-action-danger !py-1 !px-2.5 text-2xs">
                                Cambiar
                            </button>
                        </div>
                    @else
                        <!-- Buscador de socios -->
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="buscarSocio"
                                class="form-input @error('socio_id') border-rose-500 @enderror"
                                placeholder="Escriba nombre, apellido o DNI del socio..."
                            />
                            @error('socio_id')
                                <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                            @enderror

                            <!-- Lista desplegable de sugerencias -->
                            @if(count($sociosSugeridos) > 0)
                                <div class="absolute z-20 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xl overflow-hidden divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($sociosSugeridos as $s)
                                        <button
                                            type="button"
                                            wire:click="seleccionarSocio({{ $s->id }})"
                                            class="w-full text-left px-4 py-2.5 hover:bg-indigo-50 dark:hover:bg-slate-700/50 flex justify-between items-center transition"
                                        >
                                            <span class="text-sm font-bold text-slate-900 dark:text-white">
                                                {{ $s->nombre_completo }}
                                            </span>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                                DNI: {{ $s->dni }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- 2. Selección de Disciplina y Grupo -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Disciplina -->
                    <div>
                        <label for="disciplina_id" class="form-label">2. Disciplina *</label>
                        <select
                            id="disciplina_id"
                            wire:model.live="disciplina_id"
                            class="form-input"
                        >
                            <option value="">-- Seleccionar disciplina --</option>
                            @foreach ($disciplinas as $d)
                                <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Grupo -->
                    <div>
                        <label for="grupo_id" class="form-label">3. Grupo de Clase *</label>
                        <select
                            id="grupo_id"
                            wire:model.live="grupo_id"
                            class="form-input @error('grupo_id') border-rose-500 @enderror"
                            @disabled(!$disciplina_id)
                        >
                            <option value="">-- {{ $disciplina_id ? 'Seleccionar grupo' : 'Primero elija disciplina' }} --</option>
                            @foreach ($grupos as $g)
                                <option value="{{ $g->id }}">
                                    {{ $g->nombre }} ({{ $g->texto_cupo }}){{ $g->vacantesDisponibles() === 0 ? ' — COMPLETO' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('grupo_id')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                @if($disciplina_id && count($grupos))
                    <section class="space-y-3" aria-label="Grupos y vacantes disponibles">
                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-700 dark:text-slate-200">Grupos de la disciplina</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($grupos as $grupo)
                                <article class="rounded-xl border p-4 {{ $grupo->vacantesDisponibles() === 0 ? 'border-rose-200 bg-rose-50/70 dark:border-rose-900 dark:bg-rose-950/20' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800' }}">
                                    <div class="flex justify-between gap-3">
                                        <h4 class="font-black text-slate-900 dark:text-white">{{ $grupo->nombre }}</h4>
                                        <span class="text-xs font-bold {{ $grupo->vacantesDisponibles() === 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                            {{ $grupo->texto_cupo }}
                                        </span>
                                    </div>
                                    <ul class="mt-2 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                                        @foreach($grupo->horarios as $horario)
                                            <li>{{ $horario->dia_semana_label }} {{ substr($horario->hora_inicio, 0, 5) }} a {{ substr($horario->hora_fin, 0, 5) }} hs</li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-2 text-xs font-bold {{ $grupo->vacantesDisponibles() === 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                        {{ $grupo->vacantesDisponibles() === 0 ? 'Inscripción cerrada: grupo completo' : 'Inscripción abierta: ' . $grupo->vacantesDisponibles() . ' vacantes' }}
                                    </p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                <!-- Detalle del Grupo Seleccionado: Horarios y Cupos -->
                @if($grupoSeleccionado)
                    <div class="p-4 rounded-2xl border {{ $grupoSeleccionado->vacantesDisponibles() === 0 ? 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/50' : 'bg-indigo-50 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-900/50' }}">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                            <div>
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    {{ $grupoSeleccionado->nombre }} ({{ $grupoSeleccionado->disciplina->nombre }})
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Profesor(a): <span class="font-bold text-slate-700 dark:text-slate-300">{{ $grupoSeleccionado->profesor }}</span>
                                </p>
                            </div>
                            <div>
                                @if($grupoSeleccionado->vacantesDisponibles() === 0)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300">
                                        {{ $grupoSeleccionado->texto_cupo }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300">
                                        {{ $grupoSeleccionado->texto_cupo }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Horarios -->
                        <div class="mb-3">
                            <span class="text-2xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                                Días y Horarios de Cursada:
                            </span>
                            <div class="flex flex-wrap gap-1.5">
                                @forelse($grupoSeleccionado->horarios as $h)
                                    <span class="inline-block bg-white dark:bg-slate-800 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                        {{ $h->dia_semana_label }} {{ substr($h->hora_inicio, 0, 5) }} a {{ substr($h->hora_fin, 0, 5) }} hs
                                    </span>
                                @empty
                                    <span class="text-xs italic text-slate-400">Sin horarios definidos</span>
                                @endforelse
                            </div>
                        </div>

                        <!-- Advertencia de Cupo -->
                        @if($grupoSeleccionado->vacantesDisponibles() === 0)
                            <div class="p-3 bg-amber-100/70 dark:bg-amber-900/30 rounded-xl border border-amber-200 text-amber-900 dark:text-amber-200 text-xs font-bold flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                <span>El grupo alcanzó el máximo de 15. No se puede inscribir aquí; seleccione otro grupo de esta disciplina con vacantes.</span>
                            </div>
                        @else
                            <div class="text-xs text-slate-600 dark:text-slate-300">
                                Al confirmar, la inscripción queda <strong>confirmada</strong> y ocupa una vacante. Esto no habilita por sí solo el ingreso del socio.
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Observaciones -->
                <div>
                    <label for="observaciones" class="form-label">Observaciones (Opcional)</label>
                    <textarea
                        id="observaciones"
                        wire:model="observaciones"
                        rows="2"
                        class="form-input"
                        placeholder="Motivo de la solicitud, nivel deportivo o aclaraciones adicionales..."
                    ></textarea>
                </div>

                <!-- Botones de Acción -->
                <div class="pt-6 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3">
                    <a href="{{ route('inscripciones.index') }}" class="btn-secondary" wire:navigate>
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary" @disabled(!$socio_id || !$grupo_id || !$grupoSeleccionado || $grupoSeleccionado->vacantesDisponibles() === 0)>
                        Confirmar inscripción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
