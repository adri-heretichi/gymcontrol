<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <a href="{{ route('membresias.index') }}" class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 font-medium transition duration-150 ease-in-out mb-2 group">
                <svg class="w-4 h-4 mr-1.5 transform group-hover:-translate-x-1 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Cancelar y volver
            </a>
            <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                Crear Nuevo Plan de Membresía
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-semibold">
                Defina los parámetros para un nuevo plan de acceso y facturación en el gimnasio.
            </p>
        </div>

        <!-- Formulario principal -->
        <div class="main-card">
            <form wire:submit.prevent="guardar" class="space-y-6">
                
                <div>
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4">
                        Detalles de la Membresía
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nombre de la Membresía -->
                        <div class="col-span-1 md:col-span-2">
                            <label for="nombre" class="form-label">Nombre del Plan</label>
                            <input type="text" id="nombre" wire:model="nombre" placeholder="Ej: Pase Libre Mensual, Plan Estudiante, Pase Diario..." class="form-input">
                            @error('nombre') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Precio -->
                        <div>
                            <label for="precio" class="form-label">Precio ($)</label>
                            <input type="text" id="precio" wire:model="precio" placeholder="Ej: 15000.00" class="form-input">
                            @error('precio') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Horas Mensuales (Límite) -->
                        <div>
                            <label for="horas_mensuales" class="form-label">Horas Mensuales de Límite (Opcional)</label>
                            <input type="number" id="horas_mensuales" wire:model="horas_mensuales" placeholder="Ej: 30 (Dejar en blanco para ilimitado)" class="form-input">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-1.5 block font-semibold">Deje este campo vacío para otorgar acceso ilimitado.</span>
                            @error('horas_mensuales') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Botones del Formulario -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('membresias.index') }}" class="btn-secondary">
                        Cancelar
                    </a>
                    
                    <button type="submit" class="btn-primary">
                        Crear Plan
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
