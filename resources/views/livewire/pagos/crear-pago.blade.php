<div>
    {{-- ── Título ──────────────────────────────────────────────────────────────── --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Registrar Pago
            </h2>
            <a href="{{ route('pagos.index') }}" class="text-sm text-blue-600 hover:underline">
                ← Volver a pagos
            </a>
        </div>
    </x-slot>

    <div class="py-6 mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">

        {{-- Mensajes de error general --}}
        @if($errorGeneral)
            <div class="rounded bg-red-100 border border-red-400 text-red-700 px-4 py-3">
                {{ $errorGeneral }}
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-6 space-y-6">

            {{-- ── 1. Socio ──────────────────────────────────────────────────────── --}}
            <div>
                <h3 class="text-base font-semibold text-gray-700 mb-3">1. Socio</h3>

                @if($socio_id && $socioSeleccionado)
                    <p class="text-gray-800 font-medium">
                        {{ $socioSeleccionado->apellido }}, {{ $socioSeleccionado->nombre }}
                        <span class="text-gray-500 text-sm ml-2">DNI: {{ $socioSeleccionado->dni }}</span>
                        @unless($deshabilitarSocio)
                            <button type="button" wire:click="limpiarSocio" class="ml-3 text-sm text-blue-600 hover:underline">
                                Cambiar socio
                            </button>
                        @endunless
                    </p>
                @elseif($deshabilitarSocio && $socioPreseleccionado)
                    <p class="text-gray-800 font-medium">
                        {{ $socioPreseleccionado->apellido }}, {{ $socioPreseleccionado->nombre }}
                        <span class="text-gray-500 text-sm ml-2">DNI: {{ $socioPreseleccionado->dni }}</span>
                    </p>
                @else
                    <div class="relative">
                        <input type="search" wire:model.live.debounce.300ms="buscarSocio"
                               placeholder="Buscar por nombre, apellido o DNI"
                               class="w-full border-gray-300 rounded shadow-sm focus:ring focus:ring-blue-200">
                        @if(count($socios))
                            <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
                                @foreach($socios as $s)
                                    <button type="button" wire:click="seleccionarSocio({{ $s->id }})"
                                            class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-100">
                                        {{ $s->apellido }}, {{ $s->nombre }} <span class="text-gray-500">(DNI {{ $s->dni }})</span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif(mb_strlen(trim($buscarSocio)) >= 2)
                            <p class="mt-1 text-xs text-gray-500">No se encontraron socios activos con ese dato.</p>
                        @endif
                    </div>
                    @error('socio_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                @endif
            </div>

            {{-- ── 2. Datos del comprobante ────────────────────────────────────── --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de pago</label>
                    <input type="date" wire:model="fecha_pago"
                           class="w-full border-gray-300 rounded shadow-sm focus:ring focus:ring-blue-200">
                    @error('fecha_pago') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Medio de pago</label>
                    <select wire:model="metodo_pago"
                            class="w-full border-gray-300 rounded shadow-sm focus:ring focus:ring-blue-200">
                        @foreach($mediosPago as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @error('metodo_pago') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Referencia (opcional)</label>
                    <input type="text" wire:model="referencia" maxlength="100"
                           placeholder="Nro. recibo, nota, etc."
                           class="w-full border-gray-300 rounded shadow-sm focus:ring focus:ring-blue-200">
                </div>
            </div>

            {{-- ── 3. Conceptos / Detalles ─────────────────────────────────────── --}}
            <div>
                <h3 class="text-base font-semibold text-gray-700 mb-3">2. Membresías / disciplinas a cobrar</h3>

                @if($socio_id && empty($inscripcionesDisponibles))
                    <div class="rounded bg-yellow-50 border border-yellow-300 text-yellow-700 px-4 py-3 text-sm">
                        El socio no tiene inscripciones activas o pendientes. Registrá una inscripción primero.
                    </div>
                @endif

                @foreach($detalles as $i => $detalle)
                    <div class="border border-gray-200 rounded p-4 mb-3 bg-gray-50 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-600">Ítem #{{ $i + 1 }}</span>
                            @if(count($detalles) > 1)
                                <button type="button" wire:click="quitarDetalle({{ $i }})"
                                        class="text-red-500 text-xs hover:text-red-700">✕ Quitar</button>
                            @endif
                        </div>

                        {{-- Inscripción --}}
                        @if($socio_id && count($inscripcionesDisponibles))
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Inscripción</label>
                                <select wire:model.live="detalles.{{ $i }}.inscripcion_id"
                                        wire:change="actualizarFechasDetalle({{ $i }})"
                                        class="w-full border-gray-300 rounded shadow-sm text-sm">
                                    <option value="">— Seleccioná una membresía / inscripción —</option>
                                    @foreach($inscripcionesDisponibles as $insc)
                                        <option value="{{ $insc['id'] }}">
                                            {{ $insc['label'] }}
                                            ({{ $insc['estado_inscripcion'] }})
                                            @if($insc['precio'] !== null)
                                                · ${{ number_format((float) $insc['precio'], 2, ',', '.') }}
                                            @else
                                                · Precio sin definir
                                            @endif
                                            @if($insc['cobertura_vigente'])
                                                ✓ Vigente hasta {{ \Carbon\Carbon::parse($insc['fecha_fin'])->format('d/m/Y') }}
                                            @else
                                                ⚠ Sin cobertura
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error("detalles.{$i}.inscripcion_id") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @php
                            $inscripcionSeleccionada = collect($inscripcionesDisponibles)
                                ->firstWhere('id', (int) ($detalle['inscripcion_id'] ?? 0));
                        @endphp
                        @if($inscripcionSeleccionada)
                            @if($inscripcionSeleccionada['precio'] !== null)
                                <p class="rounded bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                    Precio configurado de la membresía: <strong>${{ number_format((float) $inscripcionSeleccionada['precio'], 2, ',', '.') }}</strong>. Se propone como importe del detalle.
                                </p>
                            @else
                                <p role="alert" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                                    Esta membresía no tiene precio configurado. No se puede registrar el pago hasta definirlo desde Membresías.
                                </p>
                            @endif
                        @endif

                        {{-- Concepto --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Concepto</label>
                            <input type="text" wire:model="detalles.{{ $i }}.concepto" maxlength="150"
                                   placeholder="Ej: Cuota noviembre – Kickboxing Muay Thai"
                                   class="w-full border-gray-300 rounded shadow-sm text-sm">
                            @error("detalles.{$i}.concepto") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Período --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Inicio de cobertura</label>
                                <input type="date" wire:model="detalles.{{ $i }}.fecha_inicio"
                                       class="w-full border-gray-300 rounded shadow-sm text-sm">
                                @error("detalles.{$i}.fecha_inicio") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Fin de cobertura</label>
                                <input type="date" wire:model="detalles.{{ $i }}.fecha_fin"
                                       class="w-full border-gray-300 rounded shadow-sm text-sm">
                                @error("detalles.{$i}.fecha_fin") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Importe ($)</label>
                                <input type="number" wire:model.live="detalles.{{ $i }}.subtotal"
                                       min="0" step="0.01" placeholder="0.00"
                                       class="w-full border-gray-300 rounded shadow-sm text-sm">
                                @error("detalles.{$i}.subtotal") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="button" wire:click="agregarDetalle"
                        class="text-sm text-blue-600 hover:underline mt-1">
                    + Agregar otro concepto
                </button>
            </div>

            {{-- ── Total ──────────────────────────────────────────────────────────── --}}
            <div class="flex justify-end items-center border-t pt-4">
                <span class="text-gray-700 font-semibold mr-4">Total del comprobante:</span>
                <span class="text-xl font-bold text-green-700">
                    ${{ number_format($importeTotal, 2, ',', '.') }}
                </span>
            </div>

            {{-- Aviso si total es 0 --}}
            @if($importeTotal <= 0 && count($detalles) > 0)
                <p class="text-yellow-600 text-sm">⚠ Ingresá al menos un importe mayor a cero.</p>
            @endif

            {{-- ── Acciones ─────────────────────────────────────────────────────── --}}
            <div class="flex gap-3 pt-2">
                <button type="button" wire:click="guardar"
                        wire:loading.attr="disabled"
                        class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="guardar">Registrar pago</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
                <a href="{{ route('pagos.index') }}"
                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                    Cancelar
                </a>
            </div>

        </div>

        {{-- Aviso de política --}}
        <p class="text-xs text-gray-400 text-center">
            El pago no aprueba inscripciones, no emite credenciales y no sustituye los requisitos médicos y de identificación para el ingreso.
        </p>
    </div>
</div>
