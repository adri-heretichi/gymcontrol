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
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Catálogo de Disciplinas</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Gestión de actividades y artes marciales del Gimnasio Fénix</p>
                </div>
                @if(auth()->check() && auth()->user()->rol === 'admin')
                <div class="flex-shrink-0">
                    <a href="{{ route('disciplinas.create') }}" class="btn-primary" wire:navigate>
                        <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Nueva Disciplina
                    </a>
                </div>
                @endif
            </div>

            <!-- Barra de Búsqueda y Resultados -->
            <div class="filters-container !shadow-none !p-0 !border-0 mb-6">
                <div class="max-w-md flex flex-col gap-1">
                    <label for="buscar" class="form-label">Buscar disciplinas</label>
                    <input
                        wire:model.live="buscar"
                        type="text"
                        id="buscar"
                        placeholder="Buscar por nombre o descripción..."
                        class="form-input"
                    />
                    <span class="text-xs text-slate-400 dark:text-slate-300 pl-1 mt-1 font-semibold">
                        Se encontraron {{ $disciplinas->total() }} disciplinas.
                    </span>
                </div>
            </div>

            <!-- Tabla de Disciplinas -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Nombre de la Disciplina</th>
                            <th class="custom-table-th">Descripción</th>
                            <th class="custom-table-th text-center">Grupos</th>
                            <th class="custom-table-th">Estado</th>
                            @if(auth()->check() && auth()->user()->rol === 'admin')
                                <th class="custom-table-th text-center">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($disciplinas as $disciplina)
                            <tr class="custom-table-row">
                                <!-- Nombre -->
                                <td class="custom-table-td font-bold text-slate-900 dark:text-white">
                                    {{ $disciplina->nombre }}
                                </td>
                                <!-- Descripción -->
                                <td class="custom-table-td text-slate-600 dark:text-slate-400 max-w-xs truncate">
                                    {{ $disciplina->descripcion ?: '—' }}
                                </td>
                                <!-- Grupos Vinculados -->
                                <td class="custom-table-td text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400">
                                        {{ $disciplina->grupos_count }} {{ Str::plural('grupo', $disciplina->grupos_count) }}
                                    </span>
                                </td>
                                <!-- Estado -->
                                <td class="custom-table-td">
                                    @if($disciplina->estado === 'activo')
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
                                            <a href="{{ route('disciplinas.edit', $disciplina->id) }}" class="btn-action-edit" wire:navigate>
                                                Editar
                                            </a>
                                            <!-- Alternar Estado -->
                                            @if($disciplina->estado === 'activo')
                                                <button
                                                    wire:click="alternarEstado({{ $disciplina->id }})"
                                                    wire:confirm="¿Deseas DESACTIVAR la disciplina '{{ $disciplina->nombre }}'? Los grupos vinculados no se eliminarán."
                                                    class="btn-action-danger"
                                                >
                                                    Desactivar
                                                </button>
                                            @else
                                                <button
                                                    wire:click="alternarEstado({{ $disciplina->id }})"
                                                    wire:confirm="¿Deseas ACTIVAR la disciplina '{{ $disciplina->nombre }}'?"
                                                    class="btn-action-success"
                                                >
                                                    Activar
                                                </button>
                                            @endif
                                            <!-- Eliminar Físicamente (Solo si puede) -->
                                            @if($disciplina->puedeEliminarse())
                                                <button
                                                    wire:click="eliminar({{ $disciplina->id }})"
                                                    wire:confirm="¿Estás segura de eliminar permanentemente la disciplina '{{ $disciplina->nombre }}'? Esta acción no se puede deshacer."
                                                    class="btn-action-danger"
                                                    title="Eliminar disciplina sin grupos"
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
                                <td colspan="{{ auth()->check() && auth()->user()->rol === 'admin' ? 5 : 4 }}" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic py-8">
                                    No se encontraron disciplinas registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                {{ $disciplinas->links() }}
            </div>
        </div>
    </div>
</div>
