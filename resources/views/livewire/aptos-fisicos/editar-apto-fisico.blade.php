<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <a href="{{ route('socios.show', $aptoFisico->socio_id) }}" class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 font-medium transition duration-150 ease-in-out mb-2 group">
                <svg class="w-4 h-4 mr-1.5 transform group-hover:-translate-x-1 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Cancelar y volver a la ficha del socio
            </a>
            <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                Editar Certificado Médico: <span class="text-indigo-600 dark:text-indigo-400">{{ $aptoFisico->socio?->nombre }} {{ $aptoFisico->socio?->apellido }}</span>
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-300 mt-1 font-semibold">
                Modifique las fechas, el estado de vigencia o reemplace el certificado médico privado.
            </p>
        </div>

        <!-- Formulario principal -->
        <div class="main-card">
            <form wire:submit.prevent="actualizar" class="p-8 space-y-6">
                
                <div>
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4">
                        Detalles del Certificado
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Fecha de Emisión -->
                        <div>
                            <label for="fecha_emision" class="form-label">Fecha de Emisión</label>
                            <input 
                                type="date" 
                                id="fecha_emision" 
                                wire:model="fecha_emision" 
                                class="form-input !font-semibold"
                            >
                            @error('fecha_emision') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Fecha de Vencimiento -->
                        <div>
                            <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                            <input 
                                type="date" 
                                id="fecha_vencimiento" 
                                wire:model="fecha_vencimiento" 
                                class="form-input !font-semibold"
                            >
                            @error('fecha_vencimiento') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Estado -->
                        <div>
                            <label for="estado" class="form-label">Estado de Vigencia</label>
                            <select id="estado" wire:model="estado" class="form-input">
                                <option value="vigente">Vigente (Aprobado para ingreso)</option>
                                <option value="vencido">Vencido (Ingreso denegado)</option>
                            </select>
                            @error('estado') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Reemplazo opcional del archivo -->
                        <div class="col-span-1 md:col-span-2">
                            <label for="archivo_cargado" class="form-label">Reemplazar Archivo del Certificado (Opcional)</label>
                            
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-300 dark:border-slate-700 border-dashed rounded-xl hover:border-indigo-500 transition duration-150 bg-slate-50 dark:bg-slate-900/50">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-300" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-slate-600 dark:text-slate-400 justify-center font-bold">
                                        <label for="archivo_cargado" class="relative cursor-pointer rounded-md text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 focus-within:outline-none">
                                            <span>Subir un nuevo archivo</span>
                                            <input id="archivo_cargado" wire:model="archivo_cargado" type="file" class="sr-only">
                                        </label>
                                        <p class="pl-1 text-slate-500 dark:text-slate-500 font-semibold">o arrastrar y soltar</p>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-300 font-semibold">Deje vacío si desea conservar el archivo actual. Formato: PDF, PNG, JPG, JPEG hasta 4MB</p>
                                </div>
                            </div>

                            <!-- Barra de carga Livewire -->
                            <div wire:loading wire:target="archivo_cargado" class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Subiendo nuevo archivo...
                            </div>

                            @if ($archivo_cargado)
                                <div class="mt-2 text-sm text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Nuevo archivo cargado temporalmente: {{ $archivo_cargado->getClientOriginalName() }}
                                </div>
                            @endif

                            @error('archivo_cargado') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Botones del Formulario -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('socios.show', $aptoFisico->socio_id) }}" class="btn-secondary">
                        Cancelar
                    </a>
                    
                    <button type="submit" class="btn-primary">
                        Guardar Cambios
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
