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
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Planes de Membresía</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Configuración de planes de acceso, costos y límites de horas</p>
                </div>
                @if(auth()->check() && auth()->user()->rol === 'admin')
                <div class="flex-shrink-0">
                    <a href="{{ route('membresias.create') }}" class="btn-primary">
                        <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h.01M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2zm3-3h.01M9 17h.01M9 13h.01M12 13h.01M15 13h.01M9 9h.01M12 9h.01M15 9h.01"></path>
                        </svg>
                        Crear Plan
                    </a>
                </div>
                @endif
            </div>

            <!-- Barra de Búsqueda y Resultados -->
            <div class="filters-container !shadow-none !p-0 !border-0 mb-6">
                <div class="max-w-md flex flex-col gap-1">
                    <label for="buscar" class="form-label">Buscar planes</label>
                    <input 
                        wire:model.live="buscar" 
                        type="text" 
                        id="buscar"
                        placeholder="Buscar por nombre de membresía..." 
                        class="form-input"
                    />
                    <span class="text-xs text-slate-400 dark:text-slate-300 pl-1 mt-1 font-semibold">
                        Se encontraron {{ $membresias->total() }} planes.
                    </span>
                </div>
            </div>

            <!-- Tabla de Membresías -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Nombre del Plan</th>
                            <th class="custom-table-th">Precio</th>
                            <th class="custom-table-th">Límite de Horas</th>
                            <th class="custom-table-th">Estado</th>
                            @if(auth()->check() && auth()->user()->rol === 'admin')
                                <th class="custom-table-th text-center">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($membresias as $membresia)
                            <tr class="custom-table-row">
                                <!-- Nombre -->
                                <td class="custom-table-td font-bold text-slate-900 dark:text-white">
                                    {{ $membresia->nombre }}
                                </td>
                                <!-- Precio -->
                                <td class="custom-table-td font-semibold text-slate-800 dark:text-slate-200">
                                    ${{ number_format($membresia->precio, 2, ',', '.') }}
                                </td>
                                <!-- Horas Mensuales -->
                                <td class="custom-table-td text-slate-700 dark:text-slate-300">
                                    @if($membresia->horas_mensuales)
                                        {{ $membresia->horas_mensuales }} hs / mes
                                    @else
                                        <span class="badge-info !text-[9px] !px-1.5 !py-0.5">Ilimitado</span>
                                    @endif
                                </td>
                                <!-- Estado -->
                                <td class="custom-table-td">
                                    @if($membresia->estado === 'activo')
                                        <span class="badge-success">
                                            Activo
                                        </span>
                                    @else
                                        <span class="badge-danger">
                                            Inactivo
                                        </span>
                                    @endif
                                </td>
                                <!-- Acciones (Admin only) -->
                                @if(auth()->check() && auth()->user()->rol === 'admin')
                                    <td class="custom-table-td text-center flex justify-center gap-2">
                                        <!-- Editar -->
                                        <a href="{{ route('membresias.edit', $membresia->id) }}" class="btn-action-edit" wire:navigate>
                                            Editar
                                        </a>
                                        <!-- Activar / Desactivar -->
                                        @if($membresia->estado === 'activo')
                                            <button 
                                                wire:click="alternarEstado({{ $membresia->id }})" 
                                                wire:confirm="¿Estás seguro de que deseas DESACTIVAR el plan de membresía '{{ $membresia->nombre }}'? Esto no alterará a los socios ya asociados, pero no se podrá asignar a nuevos socios."
                                                class="btn-action-danger"
                                            >
                                                Desactivar
                                            </button>
                                        @else
                                            <button 
                                                wire:click="alternarEstado({{ $membresia->id }})" 
                                                wire:confirm="¿Estás seguro de que deseas ACTIVAR el plan de membresía '{{ $membresia->nombre }}'?"
                                                class="btn-action-success"
                                            >
                                                Activar
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">
                                    No se encontraron planes de membresía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                {{ $membresias->links() }}
            </div>
        </div>
    </div>
</div>
