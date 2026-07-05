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
            <!-- Encabezado -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-slate-200 dark:border-slate-800 gap-4 mb-6">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Certificados de Aptos Físicos</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Control médico y vigencia de certificados médicos de los socios</p>
                </div>
            </div>

            <!-- Barra de Búsqueda y Filtros -->
            <div class="filters-container !shadow-none !p-0 !border-0 mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div class="max-w-md w-full flex flex-col gap-1">
                    <label for="buscar" class="form-label">Buscar por Socio (Nombre / DNI)</label>
                    <input 
                        wire:model.live="buscar" 
                        type="text" 
                        id="buscar"
                        placeholder="Buscar por DNI, Nombre o Apellido..." 
                        class="form-input"
                    />
                </div>

                <div class="max-w-xs w-full flex flex-col gap-1">
                    <label for="filtroEstado" class="form-label">Filtrar por Vigencia</label>
                    <select 
                        wire:model.live="filtroEstado" 
                        id="filtroEstado"
                        class="form-input"
                    >
                        <option value="">Todos los certificados</option>
                        <option value="vigente">Vigentes (Aprobados)</option>
                        <option value="vencido">Vencidos / Expirados</option>
                    </select>
                </div>
            </div>

            <!-- Tabla de Aptos Físicos -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Socio</th>
                            <th class="custom-table-th">DNI</th>
                            <th class="custom-table-th">Fecha Emisión</th>
                            <th class="custom-table-th">Fecha Vencimiento</th>
                            <th class="custom-table-th">Estado</th>
                            <th class="custom-table-th text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($aptos as $apto)
                            @php
                                $esVencido = $apto->fecha_vencimiento->isPast() || $apto->estado === 'vencido';
                            @endphp
                            <tr class="custom-table-row">
                                <!-- Socio -->
                                <td class="custom-table-td">
                                    {{ $apto->socio?->apellido }}, {{ $apto->socio?->nombre }}
                                </td>
                                <!-- DNI -->
                                <td class="custom-table-td text-slate-700 dark:text-slate-300">
                                    {{ $apto->socio?->dni }}
                                </td>
                                <!-- Fecha Emisión -->
                                <td class="custom-table-td text-slate-550 dark:text-slate-400">
                                    {{ $apto->fecha_emision->format('d/m/Y') }}
                                </td>
                                <!-- Fecha Vencimiento -->
                                <td class="custom-table-td">
                                    @if($esVencido)
                                        <span class="text-rose-600 dark:text-rose-400 font-bold">
                                            {{ $apto->fecha_vencimiento->format('d/m/Y') }} (Vencido)
                                        </span>
                                    @else
                                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">
                                            {{ $apto->fecha_vencimiento->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </td>
                                <!-- Estado -->
                                <td class="custom-table-td">
                                    @if(!$esVencido)
                                        <span class="badge-success">
                                            Vigente
                                        </span>
                                    @else
                                        <span class="badge-danger animate-pulse">
                                            Vencido
                                        </span>
                                    @endif
                                </td>
                                <!-- Acciones -->
                                <td class="custom-table-td text-center flex justify-center gap-2">
                                    <!-- Ver / Descargar -->
                                    <a href="{{ route('aptos-fisicos.download', $apto->id) }}" target="_blank" class="btn-action-view">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        Ver Certificado
                                    </a>

                                    @if(auth()->check() && auth()->user()->rol === 'admin')
                                        <!-- Editar (Admin only) -->
                                        <a href="{{ route('aptos-fisicos.edit', $apto->id) }}" class="btn-action-edit" wire:navigate>
                                            Editar
                                        </a>

                                        <!-- Eliminar (Admin only) -->
                                        <button 
                                            wire:click="eliminar({{ $apto->id }})" 
                                            wire:confirm="¿Estás seguro de que deseas ELIMINAR definitivamente este certificado médico? Esta acción no se puede deshacer."
                                            class="btn-action-danger"
                                        >
                                            Eliminar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="custom-table-td text-center text-slate-400 dark:text-slate-500 italic">
                                    No se encontraron certificados cargados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                {{ $aptos->links() }}
            </div>
        </div>
    </div>
</div>
