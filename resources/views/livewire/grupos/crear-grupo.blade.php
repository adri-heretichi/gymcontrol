<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="main-card">
            <!-- Encabezado -->
            <div class="pb-6 border-b border-slate-200 dark:border-slate-800 mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Nuevo Grupo de Clase</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-semibold">Configuración de disciplina, cupos (hasta 15 alumnos) y cronograma de horarios</p>
                </div>
                <a href="{{ route('grupos.index') }}" class="btn-secondary" wire:navigate>
                    Volver
                </a>
            </div>

            <!-- Formulario -->
            <form wire:submit.prevent="guardar" class="space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Disciplina -->
                    <div>
                        <label for="disciplina_id" class="form-label">Disciplina *</label>
                        <select
                            id="disciplina_id"
                            wire:model="disciplina_id"
                            class="form-input @error('disciplina_id') border-rose-500 @enderror"
                        >
                            <option value="">-- Seleccionar disciplina --</option>
                            @foreach ($disciplinas as $d)
                                <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                            @endforeach
                        </select>
                        @error('disciplina_id')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Nombre del Grupo -->
                    <div>
                        <label for="nombre" class="form-label">Nombre del Grupo *</label>
                        <input
                            type="text"
                            id="nombre"
                            wire:model="nombre"
                            class="form-input @error('nombre') border-rose-500 @enderror"
                            placeholder="Ej: Turno Mañana, Infantiles A, Adultos Avanzados..."
                        />
                        @error('nombre')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Profesor a cargo -->
                    <div>
                        <label for="profesor" class="form-label">Profesor(a) a Cargo *</label>
                        <input
                            type="text"
                            id="profesor"
                            wire:model="profesor"
                            class="form-input @error('profesor') border-rose-500 @enderror"
                        />
                        @error('profesor')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Cupo Máximo -->
                    <div>
                        <label for="cupo_maximo" class="form-label">Cupo Máximo (Hasta 15) *</label>
                        <input
                            type="number"
                            id="cupo_maximo"
                            wire:model="cupo_maximo"
                            min="1"
                            max="15"
                            class="form-input @error('cupo_maximo') border-rose-500 @enderror"
                        />
                        <span class="text-2xs text-slate-500 dark:text-slate-400 mt-1 block">Límite institucional estricto: máx. 15 alumnos</span>
                        @error('cupo_maximo')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Estado -->
                    <div>
                        <label for="estado" class="form-label">Estado *</label>
                        <select
                            id="estado"
                            wire:model="estado"
                            class="form-input @error('estado') border-rose-500 @enderror"
                        >
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                        @error('estado')
                            <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- Sección de Días y Horarios -->
                <div class="pt-6 border-t border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">Días y Horarios de Clase</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Turnos de apertura: Mañana (08:00 a 12:00) y Tarde/Noche (15:00 a 22:00).
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="agregarHorario"
                            class="btn-secondary !py-1.5 !px-3 !text-2xs flex items-center gap-1"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Agregar Día
                        </button>
                    </div>

                    <!-- Alertas de validación de horarios -->
                    @error('horarios')
                        <div class="mb-4 p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 rounded-xl text-rose-700 dark:text-rose-300 text-xs font-bold">
                            {{ $message }}
                        </div>
                    @enderror

                    <!-- Filas dinámicas de horarios -->
                    <div class="space-y-3">
                        @foreach ($horarios as $index => $h)
                            <div class="flex flex-col sm:flex-row items-center gap-3 p-3 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <!-- Día de la semana -->
                                <div class="w-full sm:w-1/3">
                                    <label class="form-label !mb-1 text-2xs">Día</label>
                                    <select wire:model="horarios.{{ $index }}.dia_semana" class="form-input !py-1.5 text-xs">
                                        @foreach ($diasSemana as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Hora inicio -->
                                <div class="w-full sm:w-1/4">
                                    <label class="form-label !mb-1 text-2xs">Hora Inicio (HH:MM)</label>
                                    <input
                                        type="time"
                                        wire:model="horarios.{{ $index }}.hora_inicio"
                                        class="form-input !py-1.5 text-xs"
                                    />
                                </div>

                                <!-- Hora fin -->
                                <div class="w-full sm:w-1/4">
                                    <label class="form-label !mb-1 text-2xs">Hora Fin (HH:MM)</label>
                                    <input
                                        type="time"
                                        wire:model="horarios.{{ $index }}.hora_fin"
                                        class="form-input !py-1.5 text-xs"
                                    />
                                </div>

                                <!-- Botón quitar fila -->
                                <div class="w-full sm:w-auto pt-3 sm:pt-4 flex justify-end">
                                    @if(count($horarios) > 1)
                                        <button
                                            type="button"
                                            wire:click="removerHorario({{ $index }})"
                                            class="btn-action-danger !py-2 !px-3"
                                            title="Eliminar este horario"
                                        >
                                            Quitar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="pt-6 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3">
                    <a href="{{ route('grupos.index') }}" class="btn-secondary" wire:navigate>
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary">
                        Guardar Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
