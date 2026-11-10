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
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Listado de Socios</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Administración, estado y control de accesos de socios</p>
                </div>
                @if(auth()->check() && auth()->user()->rol === 'admin')
                    <div class="flex-shrink-0">
                        <a href="{{ route('socios.create') }}" class="btn-primary">
                            <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h.01M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2zm3-3h.01M9 17h.01M9 13h.01M12 13h.01M15 13h.01M9 9h.01M12 9h.01M15 9h.01"></path>
                            </svg>
                            Registrar Socio
                        </a>
                    </div>
                @endif
            </div>

            <!-- Barra de Búsqueda y Resultados -->
            <div class="filters-container !shadow-none !p-0 !border-0 mb-6">
                <div class="max-w-md flex flex-col gap-1">
                    <label for="buscar" class="form-label">Buscador en tiempo real</label>
                    <input 
                        wire:model.live="buscar" 
                        type="text" 
                        id="buscar"
                        placeholder="Buscar por DNI, Nombre, Celular, Correo o Token..." 
                        class="form-input"
                    />
                    <span class="text-xs text-slate-400 dark:text-slate-300 pl-1 mt-1 font-semibold">
                        Se encontraron {{ $socios->total() }} socios.
                    </span>
                </div>
            </div>

            <!-- Tabla de Socios -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Socio</th>
                            <th class="custom-table-th">DNI</th>
                            <th class="custom-table-th">Inscripciones</th>
                            <th class="custom-table-th">Estado administrativo</th>
                            <th class="custom-table-th">Situación de acceso</th>
                            <th class="custom-table-th text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($socios as $socio)
                            <tr class="custom-table-row">
                                <!-- Socio (Foto / Avatar + Nombre) -->
                                <td class="custom-table-td">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full flex items-center justify-center overflow-hidden shadow-inner flex-shrink-0">
                                            @if($socio->foto)
                                                <!-- Si existe foto real, se renderiza -->
                                                <img src="{{ Storage::url($socio->foto) }}" alt="Foto de {{ $socio->nombre }}" class="h-full w-full object-cover">
                                            @else
                                                <!-- Generación defensiva de iniciales para el avatar temporal -->
                                                @php
                                                    $nombre = trim($socio->nombre ?? '');
                                                    $apellido = trim($socio->apellido ?? '');
                                                    $iniNombre = $nombre !== '' ? mb_substr($nombre, 0, 1) : '';
                                                    $iniApellido = $apellido !== '' ? mb_substr($apellido, 0, 1) : '';
                                                    $iniciales = mb_strtoupper($iniNombre . $iniApellido);
                                                    if ($iniciales === '') {
                                                        $iniciales = 'GYM';
                                                    }
                                                @endphp
                                                <!-- Avatar circular sólido garantizado -->
                                                <div class="h-full w-full flex items-center justify-center text-xs font-black bg-indigo-600 dark:bg-indigo-900 text-white uppercase tracking-wider">
                                                    {{ $iniciales }}
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-white leading-tight">
                                                {{ $socio->apellido }}, {{ $socio->nombre }}
                                            </div>
                                            <div class="text-[10px] font-bold text-slate-400 dark:text-slate-400 mt-0.5">
                                                Alta: {{ $socio->fecha_alta ? $socio->fecha_alta->format('d/m/Y') : 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <!-- DNI -->
                                <td class="custom-table-td text-slate-700 dark:text-slate-300">
                                    {{ $socio->dni }}
                                </td>
                                <!-- Actividades vinculadas a inscripciones -->
                                <td class="custom-table-td">
                                    <span class="badge-info">{{ $socio->inscripciones_count }}</span>
                                </td>
                                <!-- Estado -->
                                <td class="custom-table-td">
                                    @if($socio->estado === 'activo')
                                        <span class="badge-success">
                                            Activo
                                        </span>
                                    @else
                                        <span class="badge-danger">
                                            Inactivo
                                        </span>
                                    @endif
                                </td>
                                <td class="custom-table-td">
                                    @php($estadoAcceso = $estadosAcceso[$socio->id])
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200' => $estadoAcceso['tipo'] === 'bloqueado',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' => $estadoAcceso['tipo'] === 'pendiente',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' => $estadoAcceso['tipo'] === 'completo',
                                    ])>
                                        {{ $estadoAcceso['etiqueta'] }}
                                    </span>
                                    <p class="mt-1 max-w-xs text-xs text-slate-500 dark:text-slate-300">
                                        {{ $estadoAcceso['detalle'] }}
                                    </p>
                                </td>
                                <!-- Acciones -->
                                <td class="custom-table-td text-center flex justify-center gap-2">
                                    <!-- Ver Detalles (Ruta socios.show) -->
                                    <a href="{{ route('socios.show', $socio->id) }}" class="btn-action-view" wire:navigate>
                                        Ver
                                    </a>
                                    @if(auth()->check() && auth()->user()->rol === 'admin')
                                        <!-- Editar (Ruta socios.edit) -->
                                        <a href="{{ route('socios.edit', $socio->id) }}" class="btn-action-edit" wire:navigate>
                                            Editar
                                        </a>
                                    @endif
                                    <!-- Activar/Desactivar con Baja Lógica (Solo Administradores) -->
                                    @if(auth()->check() && auth()->user()->rol === 'admin')
                                        @if($socio->estado === 'activo')
                                            <button 
                                                wire:click="alternarEstado({{ $socio->id }})" 
                                                wire:confirm="¿Estás seguro de que deseas DESACTIVAR al socio {{ $socio->nombre }} {{ $socio->apellido }}?"
                                                class="btn-action-danger"
                                            >
                                                Desactivar
                                            </button>
                                        @else
                                            <button 
                                                wire:click="alternarEstado({{ $socio->id }})" 
                                                wire:confirm="¿Estás seguro de que deseas ACTIVAR al socio {{ $socio->nombre }} {{ $socio->apellido }}?"
                                                class="btn-action-success"
                                            >
                                                Activar
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 italic">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-slate-300 dark:text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <p class="text-xs font-semibold">No se encontraron socios que coincidan con la búsqueda.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                {{ $socios->links() }}
            </div>
        </div>
    </div>
</div>
