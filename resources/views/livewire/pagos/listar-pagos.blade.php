<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Pagos y Coberturas</h2>
            @can('create', \App\Models\Pago::class)
                <a href="{{ route('pagos.create-general') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                    + Nuevo pago
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6 mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-5">

        {{-- Flash --}}
        @if(session('message'))
            <div class="rounded bg-green-100 border border-green-400 text-green-700 px-4 py-3">
                {{ session('message') }}
            </div>
        @endif

        {{-- ── Recaudación (solo dueña) ────────────────────────────────────────── --}}
        @if($esDuena && $sumaTotal !== null)
            <div class="bg-white shadow rounded-lg px-6 py-4 flex items-center justify-between">
                <span class="text-gray-600 font-medium">Recaudación (filtro actual, pagos activos):</span>
                <span class="text-2xl font-bold text-green-700">
                    ${{ number_format($sumaTotal, 2, ',', '.') }}
                </span>
            </div>
        @endif

        {{-- ── Filtros ─────────────────────────────────────────────────────────── --}}
        <div class="bg-white shadow rounded-lg px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <input type="text" wire:model.live="buscar" placeholder="Buscar socio (nombre, DNI)…"
                       class="border-gray-300 rounded shadow-sm text-sm">

                <select wire:model.live="filtroMetodo"
                        class="border-gray-300 rounded shadow-sm text-sm">
                    <option value="">Todos los medios</option>
                    @foreach($mediosPago as $v => $label)
                        <option value="{{ $v }}">{{ $label }}</option>
                    @endforeach
                    <option value="tarjeta">Tarjeta (histórico)</option>
                    <option value="transferencia">Transferencia (histórico)</option>
                </select>

                @if($esDuena)
                    <select wire:model.live="filtroEstado"
                            class="border-gray-300 rounded shadow-sm text-sm">
                        <option value="">Todos los estados</option>
                        <option value="activo">Activos</option>
                        <option value="anulado">Anulados</option>
                    </select>

                    <div class="flex gap-2">
                        <input type="date" wire:model.live="filtroDesde"
                               class="flex-1 border-gray-300 rounded shadow-sm text-xs">
                        <input type="date" wire:model.live="filtroHasta"
                               class="flex-1 border-gray-300 rounded shadow-sm text-xs">
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Tabla de pagos ──────────────────────────────────────────────────── --}}
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">#</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Socio</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Fecha</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Disciplinas / Cobertura</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">Total</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Medio</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Operador</th>
                        @if($esDuena)
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Estado</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pagos as $pago)
                        <tr class="{{ $pago->estaAnulado() ? 'bg-red-50 opacity-70' : 'hover:bg-gray-50' }}">
                            <td class="px-4 py-3 text-gray-500 font-mono">{{ $pago->id }}</td>

                            {{-- Socio --}}
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-800">
                                    {{ $pago->socio?->apellido }}, {{ $pago->socio?->nombre }}
                                </span>
                                <span class="block text-xs text-gray-500">DNI: {{ $pago->socio?->dni }}</span>
                            </td>

                            {{-- Fecha --}}
                            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                {{ $pago->fecha_pago->format('d/m/Y') }}
                            </td>

                            {{-- Disciplinas y cobertura --}}
                            <td class="px-4 py-3">
                                @if($pago->detalles->isNotEmpty())
                                    @foreach($pago->detalles as $det)
                                        <div class="text-xs mb-1">
                                            <span class="font-medium">
                                                {{ $det->inscripcion?->grupo?->disciplina?->nombre ?? $det->concepto }}
                                            </span>
                                            <span class="text-gray-500 ml-1">
                                                {{ \Carbon\Carbon::parse($det->fecha_inicio_cobertura)->format('d/m/Y') }}
                                                –
                                                {{ \Carbon\Carbon::parse($det->fecha_fin_cobertura)->format('d/m/Y') }}
                                            </span>
                                            @php
                                                $hoy = now()->toDateString();
                                                $vigente = $pago->estaActivo()
                                                    && $det->fecha_inicio_cobertura->toDateString() <= $hoy
                                                    && $det->fecha_fin_cobertura->toDateString() >= $hoy;
                                            @endphp
                                            @if($vigente)
                                                <span class="text-green-600 ml-1">✓</span>
                                            @elseif($pago->estaAnulado())
                                                <span class="text-red-500 ml-1">✕</span>
                                            @else
                                                <span class="text-gray-400 ml-1">vencida</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-xs text-gray-400 italic">Sin detalles (pago histórico)</span>
                                @endif
                                @if($pago->referencia)
                                    <span class="text-xs text-gray-400 block mt-1">Ref: {{ $pago->referencia }}</span>
                                @endif
                            </td>

                            {{-- Total --}}
                            <td class="px-4 py-3 text-right font-semibold text-gray-800 whitespace-nowrap">
                                ${{ number_format($pago->importe, 2, ',', '.') }}
                            </td>

                            {{-- Medio --}}
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $pago->etiquetaMedioPago() }}
                            </td>

                            {{-- Operador --}}
                            <td class="px-4 py-3 text-xs text-gray-500">
                                {{ $pago->operador?->name ?? '(sistema)' }}
                            </td>

                            @if($esDuena)
                                {{-- Estado --}}
                                <td class="px-4 py-3">
                                    @if($pago->estaAnulado())
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-medium">
                                            Anulado
                                        </span>
                                        <span class="block text-xs text-gray-500 mt-1">
                                            por {{ $pago->anuladoPor?->name }} —
                                            {{ $pago->anulado_at?->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">
                                            Activo
                                        </span>
                                    @endif
                                </td>

                                {{-- Acciones --}}
                                <td class="px-4 py-3">
                                    @if($pago->estaActivo())
                                        <button wire:click="abrirModalAnular({{ $pago->id }})"
                                                class="text-xs text-red-600 hover:underline">
                                            Anular
                                        </button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $esDuena ? 9 : 7 }}" class="px-4 py-8 text-center text-gray-400 italic">
                                No se encontraron pagos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div class="mt-4">
            {{ $pagos->links() }}
        </div>

        {{-- ── Modal Anulación (solo dueña) ────────────────────────────────────── --}}
        @if($esDuena && $modalAnular)
            <div class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Anular pago #{{ $pagoAnularId }}</h3>

                    @if($errorAnulacion)
                        <div class="rounded bg-red-100 border border-red-400 text-red-700 px-3 py-2 mb-3 text-sm">
                            {{ $errorAnulacion }}
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Motivo de la anulación (obligatorio)
                        </label>
                        <textarea wire:model="motivoAnulacion" rows="3" maxlength="500"
                                  placeholder="Descripción del error o corrección administrativa…"
                                  class="w-full border-gray-300 rounded shadow-sm text-sm"></textarea>
                        @error('motivoAnulacion') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-gray-500 mb-4">
                        El pago no se eliminará. Quedará marcado como anulado y auditado.
                        Las coberturas asociadas quedarán suspendidas.
                    </p>

                    <div class="flex gap-3 justify-end">
                        <button wire:click="cerrarModalAnular"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm">
                            Cancelar
                        </button>
                        <button wire:click="confirmarAnulacion"
                                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                            Confirmar anulación
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
