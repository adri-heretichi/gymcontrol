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

        <!-- Card de KPI de Sumatoria Superior -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-indigo-900 text-white rounded-3xl p-6 shadow-xl relative overflow-hidden border border-indigo-950">
                <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl"></div>
                <span class="block text-2xs font-extrabold uppercase tracking-widest text-indigo-300">Recaudación Filtrada</span>
                <span class="text-3xl font-black block mt-2">
                    ${{ number_format($sumaTotal, 2, ',', '.') }}
                </span>
                <span class="text-xs text-indigo-200 mt-1 block">Suma del total de registros filtrados</span>
            </div>
        </div>

        <div class="main-card">
            <!-- Encabezado y Botón de Cobro -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-slate-200 dark:border-slate-800 gap-4 mb-6">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Registro de Pagos</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">Historial general de cobranza de cuotas del gimnasio</p>
                </div>
                <div class="flex-shrink-0 flex items-center gap-3">
                    @if(auth()->check() && auth()->user()->rol === 'admin')
                        <!-- Botón de Exportación PDF (Solo para Administradores) -->
                        <a href="{{ route('reportes.pagos', ['buscar' => $buscar, 'metodo_pago' => $filtroMetodo]) }}" target="_blank" class="btn-secondary">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Exportar PDF
                        </a>
                    @endif

                    <a href="{{ route('pagos.create-general') }}" class="btn-primary">
                        <svg class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Registrar Cobro
                    </a>
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
                    <label for="filtroMetodo" class="form-label">Filtrar por Método</label>
                    <select 
                        wire:model.live="filtroMetodo" 
                        id="filtroMetodo"
                        class="form-input"
                    >
                        <option value="">Todos los métodos</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta de Crédito / Débito</option>
                        <option value="transferencia">Transferencia Bancaria</option>
                    </select>
                </div>
            </div>

            <!-- Tabla de Pagos -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Socio</th>
                            <th class="custom-table-th">DNI</th>
                            <th class="custom-table-th">Fecha de Pago</th>
                            <th class="custom-table-th">Método de Pago</th>
                            <th class="custom-table-th">Membresía</th>
                            <th class="custom-table-th">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($pagos as $pago)
                            <tr class="custom-table-row">
                                <!-- Socio -->
                                <td class="custom-table-td">
                                    <a href="{{ route('socios.show', $pago->socio?->id) }}" class="text-sm font-extrabold text-slate-950 dark:text-white hover:text-indigo-600 hover:underline">
                                        {{ $pago->socio?->apellido }}, {{ $pago->socio?->nombre }}
                                    </a>
                                </td>
                                <!-- DNI -->
                                <td class="custom-table-td text-slate-700 dark:text-slate-300">
                                    {{ $pago->socio?->dni }}
                                </td>
                                <!-- Fecha de Pago -->
                                <td class="custom-table-td text-slate-700 dark:text-slate-200">
                                    {{ $pago->fecha_pago->format('d/m/Y') }}
                                </td>
                                <!-- Método de Pago -->
                                <td class="custom-table-td capitalize">
                                    {{ $pago->metodo_pago }}
                                </td>
                                <!-- Membresía -->
                                <td class="custom-table-td">
                                    <span class="badge-info">
                                        {{ $pago->socio?->membresia?->nombre ?? 'Sin membresía' }}
                                    </span>
                                </td>
                                <!-- Importe -->
                                <td class="custom-table-td text-emerald-600 dark:text-emerald-400 font-extrabold text-base">
                                    ${{ number_format($pago->importe, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 italic">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-slate-300 dark:text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <p class="text-xs font-semibold">No se encontraron registros de pagos.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                {{ $pagos->links() }}
            </div>
        </div>
    </div>
</div>
