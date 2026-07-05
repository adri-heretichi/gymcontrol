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
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Gestión de Operadores</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Administración de usuarios con acceso al sistema GymControl</p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('operadores.create') }}" class="btn-primary">
                        <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        Registrar Operador
                    </a>
                </div>
            </div>

            <!-- Barra de Búsqueda y Filtros -->
            <div class="filters-container !shadow-none !p-0 !border-0 mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div class="max-w-md w-full flex flex-col gap-1">
                    <label for="buscar" class="form-label">Buscador por Nombre/Correo</label>
                    <input 
                        wire:model.live="buscar" 
                        type="text" 
                        id="buscar"
                        placeholder="Buscar operador por nombre o email..." 
                        class="form-input"
                    />
                </div>

                <div class="max-w-xs w-full flex flex-col gap-1">
                    <label for="filtroRol" class="form-label">Filtrar por Rol</label>
                    <select 
                        wire:model.live="filtroRol" 
                        id="filtroRol"
                        class="form-input"
                    >
                        <option value="">Todos los roles</option>
                        <option value="admin">Administrador (admin)</option>
                        <option value="recepcionista">Recepcionista</option>
                    </select>
                </div>
            </div>

            <!-- Tabla de Operadores -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Nombre</th>
                            <th class="custom-table-th">Correo Electrónico</th>
                            <th class="custom-table-th">Rol de Permisos</th>
                            <th class="custom-table-th text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($operadores as $operador)
                            <tr class="custom-table-row">
                                <!-- Nombre -->
                                <td class="custom-table-td font-bold text-slate-900 dark:text-white">
                                    {{ $operador->name }}
                                </td>
                                <!-- Email -->
                                <td class="custom-table-td text-slate-700 dark:text-slate-200">
                                    {{ $operador->email }}
                                </td>
                                <!-- Rol -->
                                <td class="custom-table-td">
                                    @if($operador->rol === 'admin')
                                        <span class="badge-info">
                                            Administrador
                                        </span>
                                    @else
                                        <span class="badge-success">
                                            Recepcionista
                                        </span>
                                    @endif
                                </td>
                                <!-- Acciones -->
                                <td class="custom-table-td text-center">
                                    <!-- Editar y Cambiar Contraseña -->
                                    <a href="{{ route('operadores.edit', $operador->id) }}" class="btn-action-edit" wire:navigate>
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Editar / Contraseña
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">
                                    No se encontraron operadores registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                {{ $operadores->links() }}
            </div>
        </div>
    </div>
</div>
