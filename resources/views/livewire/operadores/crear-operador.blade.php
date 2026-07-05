<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <a href="{{ route('operadores.index') }}" class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 font-medium transition duration-150 ease-in-out mb-2 group">
                <svg class="w-4 h-4 mr-1.5 transform group-hover:-translate-x-1 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Cancelar y volver
            </a>
            <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                Registrar Nuevo Operador
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-semibold">
                Complete los datos para crear una nueva cuenta de acceso administrativo u operativo.
            </p>
        </div>

        <!-- Formulario principal -->
        <div class="main-card">
            <form wire:submit.prevent="guardar" class="space-y-6">
                
                <div>
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4">
                        Credenciales e Información Personal
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nombre del Operador -->
                        <div>
                            <label for="name" class="form-label">Nombre Completo</label>
                            <input type="text" id="name" wire:model="name" placeholder="Ej: Juan Pérez" class="form-input">
                            @error('name') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Correo Electrónico -->
                        <div>
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" id="email" wire:model="email" placeholder="Ej: juan.perez@gymcontrol.com" class="form-input">
                            @error('email') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Rol de Permisos -->
                        <div>
                            <label for="rol" class="form-label">Rol en el Sistema</label>
                            <select id="rol" wire:model="rol" class="form-input">
                                <option value="recepcionista">Recepcionista (Acceso limitado)</option>
                                <option value="admin">Administrador (Acceso total)</option>
                            </select>
                            @error('rol') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Contraseña Inicial -->
                        <div>
                            <label for="password" class="form-label">Contraseña de Acceso</label>
                            <input type="password" id="password" wire:model="password" placeholder="Mínimo 8 caracteres" class="form-input">
                            @error('password') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Botones del Formulario -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('operadores.index') }}" class="btn-secondary">
                        Cancelar
                    </a>
                    
                    <button type="submit" class="btn-primary">
                        Registrar Operador
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
