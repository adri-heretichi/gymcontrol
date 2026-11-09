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
            <!-- Encabezado y Botón de Alta Superior -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-slate-200 dark:border-slate-800 gap-4 mb-6">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Grupos, Horarios y Cupos</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Administración de comisiones de clase, turnos y control de vacantes (máximo 15 alumnos)</p>
                </div>
                @if(auth()->check() && auth()->user()->rol === 'admin')
                <div class="flex-shrink-0">
                    <a href="{{ route('grupos.create') }}" class="btn-primary" wire:navigate>
                        <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Nuevo Grupo
                    </a>
                </div>
                @endif
            </div>

            <!-- Filtros: Búsqueda y Disciplina -->
            <div class="filters-container !shadow-none !p-0 !border-0 mb-6 flex flex-col md:flex-row gap-4">
                <div class="flex-1 flex flex-col gap-1">
                    <label for="buscar" class="form-label">Buscar grupo o profesor</label>
                    <input
                        wire:model.live="buscar"
                        type="text"
                        id="buscar"
                        placeholder="Buscar por grupo, profesor o disciplina..."
                        class="form-input"
                    />
                </div>
                <div class="w-full md:w-64 flex flex-col gap-1">
                    <label for="disciplinaId" class="form-label">Filtrar por disciplina</label>
                    <select wire:model.live="disciplinaId" id="disciplinaId" class="form-input">
                        <option value="">Todas las disciplinas</option>
                        @foreach ($disciplinas as $d)
                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Tabla de Grupos -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Grupo</th>
                            <th class="custom-table-th">Disciplina</th>
                            <th class="custom-table-th">Días y Horarios</th>
                            <th class="custom-table-th">Profesor</th>
                            <th class="custom-table-th">Cupo y Vacantes</th>
                            <th class="custom-table-th">Estado</th>
                            @if(auth()->check() && auth()->user()->rol === 'admin')
                                <th class="custom-table-th text-center">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($grupos as $grupo)
                            <tr class="custom-table-row">
                                <!-- Nombre del Grupo -->
                                <td class="custom-table-td font-bold text-slate-900 dark:text-white">
                                    {{ $grupo->nombre }}
                                </td>
                                <!-- Disciplina -->
                                <td class="custom-table-td text-slate-800 dark:text-slate-200 font-semibold">
                                    {{ $grupo->disciplina->nombre }}
                                </td>
                                <!-- Días y Horarios -->
                                <td class="custom-table-td text-xs text-slate-600 dark:text-slate-300">
                                    @if($grupo->horarios->isNotEmpty())
                                        <div class="space-y-1">
                                            @foreach($grupo->horarios as $h)
                                                <div class="inline-block bg-slate-100 dark:bg-slate-700/50 px-2 py-0.5 rounded text-2xs font-bold mr-1">
                                                    {{ ucfirst($h->dia_semana) }} {{ substr($h->hora_inicio, 0, 5) }} - {{ substr($h->hora_fin, 0, 5) }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="italic text-slate-400">Sin horarios asignados</span>
                                    @endif
                                </td>
                                <!-- Profesor -->
                                <td class="custom-table-td text-slate-700 dark:text-slate-300 text-xs">
                                    {{ $grupo->profesor }}
                                </td>
                                <!-- Cupo y Vacantes -->
                                <td class="custom-table-td">
                                    @if($grupo->vacantesDisponibles() === 0)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-2xs font-black uppercase tracking-wider bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200">
                                            {{ $grupo->texto_cupo }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-2xs font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200">
                                            {{ $grupo->texto_cupo }}
                                        </span>
                                    @endif
                                </td>
                                <!-- Estado -->
                                <td class="custom-table-td">
                                    @if($grupo->estado === 'activo')
                                        <span class="badge-success">Activo</span>
                                    @else
                                        <span class="badge-danger">Inactivo</span>
                                    @endif
                                </td>
                                <!-- Acciones (Admin only) -->
                                @if(auth()->check() && auth()->user()->rol === 'admin')
                                    <td class="custom-table-td text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <!-- Editar -->
                                            <a href="{{ route('grupos.edit', $grupo->id) }}" class="btn-action-edit" wire:navigate>
                                                Editar
                                            </a>
                                            <!-- Alternar Estado -->
                                            @if($grupo->estado === 'activo')
                                                <button
                                                    wire:click="alternarEstado({{ $grupo->id }})"
                                                    wire:confirm="¿Deseas DESACTIVAR el grupo '{{ $grupo->nombre }}'? Las inscripciones existentes se conservarán intactas."
                                                    class="btn-action-danger"
                                                >
                                                    Desactivar
                                                </button>
                                            @else
                                                <button
                                                    wire:click="alternarEstado({{ $grupo->id }})"
                                                    wire:confirm="¿Deseas ACTIVAR el grupo '{{ $grupo->nombre }}'?"
                                                    class="btn-action-success"
                                                >
                                                    Activar
                                                </button>
                                            @endif
                                            <!-- Eliminar (solo si no tiene inscripciones ni asistencias) -->
                                            @if($grupo->puedeEliminarse())
                                                <button
                                                    wire:click="eliminar({{ $grupo->id }})"
                                                    wire:confirm="¿Estás segura de eliminar permanentemente el grupo '{{ $grupo->nombre }}'? Esta acción no se puede deshacer."
                                                    class="btn-action-danger"
                                                    title="Eliminar grupo sin registros"
                                                >
                                                    Eliminar
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->check() && auth()->user()->rol === 'admin' ? 7 : 6 }}" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic py-8">
                                    No se encontraron grupos registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                {{ $grupos->links() }}
            </div>
        </div>
    </div>
</div>
