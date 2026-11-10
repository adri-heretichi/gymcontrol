<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="main-card">
            <!-- Encabezado -->
            <div class="pb-6 border-b border-slate-200 dark:border-slate-800 mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Editar Disciplina</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-semibold">Modificar datos de '{{ $disciplina->nombre }}'</p>
                </div>
                <a href="{{ route('disciplinas.index') }}" class="btn-secondary" wire:navigate>
                    Volver
                </a>
            </div>

            <!-- Formulario -->
            <form wire:submit.prevent="actualizar" class="space-y-6">
                <!-- Nombre -->
                <div>
                    <label for="nombre" class="form-label">Nombre de la Disciplina *</label>
                    <input
                        type="text"
                        id="nombre"
                        wire:model="nombre"
                        class="form-input @error('nombre') border-rose-500 @enderror"
                    />
                    @error('nombre')
                        <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Descripción -->
                <div>
                    <label for="descripcion" class="form-label">Descripción (Opcional)</label>
                    <textarea
                        id="descripcion"
                        wire:model="descripcion"
                        rows="3"
                        class="form-input @error('descripcion') border-rose-500 @enderror"
                    ></textarea>
                    @error('descripcion')
                        <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Estado -->
                <div>
                    <label for="estado" class="form-label">Estado *</label>
                    <select
                        id="estado"
                        wire:model="estado"
                        class="form-input @error('estado') border-rose-500 @enderror"
                    >
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                    @error('estado')
                        <span class="text-rose-500 text-xs font-bold mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Botones de Acción -->
                <div class="pt-6 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3">
                    <a href="{{ route('disciplinas.index') }}" class="btn-secondary" wire:navigate>
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary">
                        Actualizar Disciplina
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
