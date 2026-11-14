<div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <a href="{{ route('socios.index') }}" class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 font-medium transition duration-150 ease-in-out mb-2 group">
                <svg class="w-4 h-4 mr-1.5 transform group-hover:-translate-x-1 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Cancelar y volver
            </a>
            <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                Registrar Nuevo Socio
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-semibold">
                Registrá los datos, las disciplinas y los cobros confirmados. El acceso depende de los requisitos de cada inscripción.
            </p>
        </div>

        <!-- Formulario principal -->
        <div class="main-card">
            <form wire:submit.prevent="guardar" class="space-y-6">
                
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
                <div>
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4">
                        2. Contacto
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

                <!-- Sección 3: Datos de alta -->
                <div>
                    <h3 class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-4">
                        3. Datos de alta
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="fecha_alta" class="form-label">Fecha de Alta</label>
                            <input type="date" id="fecha_alta" wire:model="fecha_alta" class="form-input">
                            @error('fecha_alta') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <p class="form-label">Acceso y pagos</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300">Se gestionan por disciplina, grupo, inscripción y cobertura; no por una membresía general.</p>
                        </div>
                    </div>
                </div>

                <section aria-label="Disciplinas y cobros del socio" class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">4. Disciplinas y grupos</h3>
                        <button type="button" wire:click="agregarInscripcion" class="btn-secondary">Agregar otra disciplina</button>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300">Podés elegir varias disciplinas con horarios compatibles o guardar los datos de forma preliminar. Cada inscripción se habilita solo con su pago vigente y el requisito médico resuelto.</p>
                    @error('inscripciones') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @foreach($inscripciones as $index => $seleccion)
                        @php
                            $disciplinaElegida = $disciplinas->firstWhere('id', (int) ($seleccion['disciplina_id'] ?? 0));
                            $grupoElegido = $disciplinaElegida?->grupos->firstWhere('id', (int) ($seleccion['grupo_id'] ?? 0));
                        @endphp
                        <fieldset wire:key="registro-inscripcion-{{ $index }}" class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <legend class="px-2 font-bold text-slate-800 dark:text-slate-100">Inscripción {{ $loop->iteration }}</legend>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="disciplina-{{ $index }}" class="form-label">Disciplina</label>
                                    <select id="disciplina-{{ $index }}" wire:model.live="inscripciones.{{ $index }}.disciplina_id" class="form-input">
                                        <option value="">Seleccionar disciplina</option>
                                        @foreach($disciplinas as $disciplina)
                                            <option value="{{ $disciplina->id }}">{{ $disciplina->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error("inscripciones.{$index}.disciplina_id") <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="grupo-{{ $index }}" class="form-label">Grupo y horario</label>
                                    <select id="grupo-{{ $index }}" wire:model.live="inscripciones.{{ $index }}.grupo_id" class="form-input" @disabled(!$disciplinaElegida)>
                                        <option value="">Seleccionar grupo</option>
                                        @foreach($disciplinaElegida?->grupos ?? [] as $grupo)
                                            <option value="{{ $grupo->id }}" @disabled(!$grupo->tieneCupoDisponible())>{{ $grupo->nombre }} · {{ $grupo->resumen_horarios }} · {{ $grupo->texto_cupo }}</option>
                                        @endforeach
                                    </select>
                                    @error("inscripciones.{$index}.grupo_id") <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            @if($grupoElegido)
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-100">
                                    Cuota de {{ $disciplinaElegida->nombre }}:
                                    {{ $disciplinaElegida->precio === null ? 'precio pendiente de definición' : '$'.number_format((float) $disciplinaElegida->precio, 2, ',', '.') }}
                                </p>
                                <label class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    <input type="checkbox" wire:model.live="inscripciones.{{ $index }}.confirmar_cobro">
                                    Confirmo el cobro real de esta cuota
                                </label>
                                @error("inscripciones.{$index}.confirmar_cobro") <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                @if(!empty($seleccion['confirmar_cobro']))
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label for="inicio-{{ $index }}" class="form-label">Inicio de cobertura de esta inscripción</label>
                                            <input id="inicio-{{ $index }}" type="date" wire:model="inscripciones.{{ $index }}.fecha_inicio" class="form-input">
                                            @error("inscripciones.{$index}.fecha_inicio") <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="fin-{{ $index }}" class="form-label">Vencimiento de esta inscripción</label>
                                            <input id="fin-{{ $index }}" type="date" wire:model="inscripciones.{{ $index }}.fecha_fin" class="form-input">
                                            @error("inscripciones.{$index}.fecha_fin") <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500">Se registrará un pago independiente para esta inscripción.</p>
                                @else
                                    <p class="text-xs text-slate-500">Cuota pendiente: no se registrará ningún pago para esta inscripción.</p>
                                @endif
                            @endif
                            <button type="button" wire:click="quitarInscripcion({{ $index }})" class="text-sm font-semibold text-rose-600">Quitar esta selección</button>
                        </fieldset>
                    @endforeach
                    <div>
                        <label for="metodo_pago" class="form-label">Medio de los cobros confirmados</label>
                        <select id="metodo_pago" wire:model="metodo_pago" class="form-input">
                            @foreach($mediosPago as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach
                        </select>
                        @error('metodo_pago') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </section>

                <section aria-label="Requisito médico individual" class="space-y-3">
                    <h3 class="text-sm font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">5. Requisito médico individual</h3>
                    @if(auth()->user()->esDuena())
                        <label for="decision_medica" class="form-label">Decisión de la dueña</label>
                        <select id="decision_medica" wire:model="decision_medica" class="form-input">
                            <option value="pendiente">Pendiente de revisión</option>
                            <option value="requerido">Requiere certificado aprobado</option>
                            <option value="no_requerido">No requiere certificado</option>
                        </select>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Si el certificado es requerido, se presenta y aprueba desde la ficha. Solo se emiten PIN y QR si ya existe una inscripción con todos los requisitos cumplidos.</p>
                    @else
                        <p class="text-sm text-slate-600 dark:text-slate-300">La decisión queda pendiente hasta que la dueña la resuelva. Confirmar un cobro no reemplaza el requisito médico ni habilita el acceso.</p>
                    @endif
                    @error('decision_medica') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                </section>

                <!-- Botones del Formulario -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('socios.index') }}" class="btn-secondary">
                        Cancelar
                    </a>
                    
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="guardar">
                        Registrar Socio
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
