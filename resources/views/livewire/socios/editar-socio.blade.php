<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <a href="{{ route('socios.show', $socio->id) }}" class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 font-medium transition duration-150 ease-in-out mb-2 group">
                <svg class="w-4 h-4 mr-1.5 transform group-hover:-translate-x-1 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Cancelar y volver a la ficha
            </a>
            <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                Editar Socio: {{ $socio->nombre }} {{ $socio->apellido }}
            </h2>
            <p class="text-sm mt-1 text-slate-500 dark:text-slate-400 font-semibold">
                Modifique los campos correspondientes y presione Guardar Cambios.
            </p>
        </div>

        <!-- Formulario principal -->
        <div class="main-card">
            <form wire:submit.prevent="actualizar" class="space-y-6">
                
                <!-- Sección 1: Datos Personales -->
                <div>
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4">
                        1. Datos Personales
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nombre -->
                        <div>
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" id="nombre" wire:model="nombre" class="form-input">
                            @error('nombre') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Apellido -->
                        <div>
                            <label for="apellido" class="form-label">Apellido</label>
                            <input type="text" id="apellido" wire:model="apellido" class="form-input">
                            @error('apellido') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- DNI -->
                        <div>
                            <label for="dni" class="form-label">DNI (Sin puntos ni espacios)</label>
                            <input type="text" id="dni" wire:model="dni" class="form-input">
                            @error('dni') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Sexo -->
                        <div>
                            <label for="sexo" class="form-label">Sexo / Género</label>
                            <select id="sexo" wire:model="sexo" class="form-input">
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                            </select>
                            @error('sexo') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100 dark:border-slate-800">

                <!-- Sección 2: Información de Contacto -->
                <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800/80">
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4">
                        2. Datos de Contacto
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Correo electrónico -->
                        <div>
                            <label for="correo" class="form-label">Correo Electrónico (Opcional)</label>
                            <input type="email" id="correo" wire:model="correo" class="form-input">
                            @error('correo') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Celular -->
                        <div>
                            <label for="celular" class="form-label">Celular / Teléfono (Opcional)</label>
                            <input type="text" id="celular" wire:model="celular" class="form-input" placeholder="Ej: 1122334455">
                            @error('celular') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100 dark:border-slate-800">

                <!-- Sección 3: Configuración de Membresía e Ingreso -->
                <div>
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4">
                        3. Membresía, Fechas y Estado
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Membresía -->
                        <div>
                            <label for="membresia_id" class="form-label">Membresía</label>
                            <select id="membresia_id" wire:model="membresia_id" class="form-input">
                                @foreach($membresias as $membresia)
                                    <option value="{{ $membresia->id }}">{{ $membresia->nombre }} - ${{ number_format($membresia->precio, 2, ',', '.') }}</option>
                                @endforeach
                            </select>
                            @error('membresia_id') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Fecha de Alta -->
                        <div>
                            <label for="fecha_alta" class="form-label">Fecha de Alta</label>
                            <input type="date" id="fecha_alta" wire:model="fecha_alta" class="form-input">
                            @error('fecha_alta') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Fecha de Vencimiento -->
                        <div>
                            <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                            <input type="date" id="fecha_vencimiento" wire:model="fecha_vencimiento" class="form-input">
                            @error('fecha_vencimiento') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Estado del Socio: Solo visible / editable por Administradores -->
                    @if(auth()->check() && auth()->user()->rol === 'admin')
                        <div class="mt-6 max-w-xs">
                            <label for="estado" class="form-label">Estado de la Ficha (Baja Lógica)</label>
                            <select id="estado" wire:model="estado" class="form-input">
                                <option value="activo">Activo (Acceso Permitido)</option>
                                <option value="inactivo">Inactivo (Acceso Bloqueado)</option>
                            </select>
                            @error('estado') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <!-- Si es recepcionista, se muestra como información de lectura -->
                        <div class="mt-6 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 max-w-sm">
                            <span class="block text-2xs font-extrabold uppercase tracking-widest text-slate-400 mb-1">Estado del Socio</span>
                            <span class="text-sm font-bold capitalize text-slate-800 dark:text-slate-200">
                                {{ $estado === 'activo' ? 'Activo (Acceso Permitido)' : 'Inactivo (Acceso Bloqueado)' }}
                            </span>
                            <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-semibold">
                                Solo los administradores pueden cambiar el estado del socio.
                            </span>
                        </div>
                    @endif
                </div>

                <!-- Botones del Formulario -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('socios.show', $socio->id) }}" class="btn-secondary">
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
