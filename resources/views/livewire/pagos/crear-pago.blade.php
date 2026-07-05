<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        
        <div class="bg-white dark:bg-slate-800 shadow-xl rounded-3xl border border-slate-100 dark:border-slate-800 overflow-hidden">
            
            <!-- Encabezado del Formulario -->
            <div class="bg-gradient-to-r from-indigo-900 to-indigo-950 px-8 py-6 text-white rounded-t-3xl border-b border-indigo-950">
                <h2 class="text-2xl font-black tracking-tight">Registrar Pago</h2>
                <p class="text-xs text-indigo-200 mt-1 uppercase tracking-wider font-extrabold">Ingreso de cobranza y renovación de membresía</p>
            </div>

            <!-- Formulario principal -->
            <form wire:submit.prevent="guardar" class="p-8 space-y-6">
                @csrf

                <!-- Campo Socio -->
                <div>
                    <label class="form-label">Socio</label>
                    @if ($deshabilitarSocio)
                        <div class="flex items-center justify-between p-4 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 rounded-xl">
                            <div>
                                <span class="block text-base font-extrabold text-slate-900 dark:text-white">
                                    {{ $socioPreseleccionado->apellido }}, {{ $socioPreseleccionado->nombre }}
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-300 font-semibold">DNI: {{ $socioPreseleccionado->dni }}</span>
                            </div>
                            <span class="badge-info">
                                {{ $socioPreseleccionado->membresia?->nombre ?? 'Sin membresía' }}
                            </span>
                        </div>
                        <input type="hidden" wire:model="socio_id" />
                    @else
                        <div class="relative">
                            <select 
                                id="socio_id"
                                wire:model.live="socio_id" 
                                class="form-input"
                            >
                                <option value="">Seleccione un socio activo...</option>
                                @foreach($socios as $socio)
                                    <option value="{{ $socio->id }}">
                                        {{ $socio->apellido }}, {{ $socio->nombre }} (DNI: {{ $socio->dni }}) - {{ $socio->membresia?->nombre ?? 'Sin Membresía' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('socio_id')
                            <p class="text-rose-500 text-xs font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <!-- Fila: Fecha e Importe -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Fecha de Pago -->
                    <div>
                        <label for="fecha_pago" class="form-label">Fecha de Pago</label>
                        <input 
                            type="date" 
                            id="fecha_pago"
                            wire:model="fecha_pago" 
                            class="form-input"
                        />
                        @error('fecha_pago')
                            <p class="text-rose-500 text-xs font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Importe -->
                    <div>
                        <label for="importe" class="form-label">Importe ($)</label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="text-slate-400 dark:text-slate-500 font-extrabold text-sm">$</span>
                            </div>
                            <input 
                                type="number" 
                                step="0.01" 
                                id="importe"
                                placeholder="0,00"
                                wire:model="importe" 
                                class="form-input !pl-8 !font-bold"
                            />
                        </div>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1.5 font-semibold">Sugerido automáticamente del plan del socio. Puede modificarse.</p>
                        @error('importe')
                            <p class="text-rose-500 text-xs font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Método de Pago -->
                <div>
                    <label for="metodo_pago" class="form-label">Método de Pago</label>
                    <div class="grid grid-cols-3 gap-4">
                        <label class="flex flex-col items-center justify-center p-4 border-2 rounded-xl cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-950/20 hover:border-indigo-300 transition duration-150 @if($metodo_pago === 'efectivo') border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20 @else border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 @endif">
                            <input type="radio" wire:model.live="metodo_pago" value="efectivo" class="sr-only" />
                            <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">Efectivo</span>
                        </label>
                        <label class="flex flex-col items-center justify-center p-4 border-2 rounded-xl cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-950/20 hover:border-indigo-300 transition duration-150 @if($metodo_pago === 'tarjeta') border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20 @else border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 @endif">
                            <input type="radio" wire:model.live="metodo_pago" value="tarjeta" class="sr-only" />
                            <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">Tarjeta</span>
                        </label>
                        <label class="flex flex-col items-center justify-center p-4 border-2 rounded-xl cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-950/20 hover:border-indigo-300 transition duration-150 @if($metodo_pago === 'transferencia') border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20 @else border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 @endif">
                            <input type="radio" wire:model.live="metodo_pago" value="transferencia" class="sr-only" />
                            <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">Transf.</span>
                        </label>
                    </div>
                    @error('metodo_pago')
                        <p class="text-rose-500 text-xs font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Acciones -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('pagos.index') }}" class="btn-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary">
                        Registrar Cobro
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
