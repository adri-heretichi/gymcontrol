<div class="py-8 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <section class="main-card">
            <div class="mb-6 border-b border-slate-200 pb-5 dark:border-slate-800">
                <h1 class="text-2xl font-black text-slate-900 dark:text-white">Auditoría administrativa</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Consulta de solo lectura. Los secretos y los datos médicos privados no se muestran.</p>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><label class="form-label" for="fechaDesde">Desde</label><input id="fechaDesde" type="date" wire:model.live="fechaDesde" class="form-input"></div>
                <div><label class="form-label" for="fechaHasta">Hasta</label><input id="fechaHasta" type="date" wire:model.live="fechaHasta" class="form-input"></div>
                <div>
                    <label class="form-label" for="tipoEvento">Tipo de evento</label>
                    <select id="tipoEvento" wire:model.live="tipoEvento" class="form-input">
                        <option value="">Todos</option>
                        @foreach($tipos as $tipo)<option value="{{ $tipo }}">{{ ucfirst(str_replace('_', ' ', $tipo)) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="accion">Acción</label>
                    <select id="accion" wire:model.live="accion" class="form-input">
                        <option value="">Todas</option>
                        @foreach($acciones as $accionOpcion)<option value="{{ $accionOpcion }}">{{ str_replace('_', ' ', ucfirst($accionOpcion)) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="usuarioId">Usuario responsable</label>
                    <select id="usuarioId" wire:model.live="usuarioId" class="form-input">
                        <option value="">Todos</option>
                        @foreach($usuarios as $usuario)<option value="{{ $usuario->id }}">{{ $usuario->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="dniSocio">Socio (DNI exacto)</label>
                    <input id="dniSocio" type="text" inputmode="numeric" wire:model.live.debounce.400ms="dniSocio" class="form-input" placeholder="DNI del socio">
                </div>
                <div>
                    <label class="form-label" for="resultado">Resultado de acceso</label>
                    <select id="resultado" wire:model.live="resultado" class="form-input">
                        <option value="">Todos</option>
                        <option value="autorizado">Autorizado</option>
                        <option value="denegado">Denegado</option>
                    </select>
                </div>
                <div class="flex items-end"><button type="button" wire:click="limpiarFiltros" class="btn-action-edit">Limpiar filtros</button></div>
            </div>

            <div class="overflow-x-auto">
                <table class="custom-table min-w-full">
                    <thead>
                        <tr class="custom-table-head">
                            <th class="custom-table-th">Fecha y hora</th>
                            <th class="custom-table-th">Evento / acción</th>
                            <th class="custom-table-th">Usuario</th>
                            <th class="custom-table-th">Socio relacionado</th>
                            <th class="custom-table-th">Resultado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($eventos as $evento)
                            @php
                                $resultadoEvento = $evento->datos_despues['resultado'] ?? null;
                                $metodoEvento = $evento->datos_despues['metodo'] ?? null;
                                $codigoSeguro = is_string($resultadoEvento) && preg_match('/^[a-z0-9_]+$/i', $resultadoEvento)
                                    ? str_replace('_', ' ', $resultadoEvento)
                                    : null;
                            @endphp
                            <tr>
                                <td class="custom-table-td whitespace-nowrap">{{ $evento->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="custom-table-td">
                                    <span class="font-bold">{{ ucfirst(str_replace('_', ' ', $evento->tipo_evento)) }}</span>
                                    <span class="block text-xs text-slate-500">{{ str_replace('_', ' ', $evento->accion) }}</span>
                                </td>
                                <td class="custom-table-td">{{ $evento->usuario?->name ?? 'Sistema / terminal' }}</td>
                                <td class="custom-table-td">{{ $evento->socio_relacionado ?? '—' }}</td>
                                <td class="custom-table-td">
                                    @if($codigoSeguro){{ ucfirst($codigoSeguro) }}@endif
                                    @if($metodoEvento)<span class="block text-xs text-slate-500">{{ $metodoEvento }}</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">No hay eventos para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $eventos->links() }}</div>
        </section>
    </div>
</div>
