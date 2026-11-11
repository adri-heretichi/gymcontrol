<div class="min-h-screen bg-slate-50 py-10 dark:bg-slate-900">
    <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
        <header class="flex flex-col gap-3 border-b border-orange-200 pb-6 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.2em] text-orange-600">GymControl + Fénix</p>
                <h1 class="mt-1 text-3xl font-black text-slate-950 dark:text-white">Membresías</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                    Cada membresía representa una disciplina existente. Los grupos, horarios y vacantes se administran desde el mismo catálogo.
                </p>
            </div>
        </header>

        @if (session()->has('message'))
            <div role="status" class="rounded-xl border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                {{ session('message') }}
            </div>
        @endif

        @if ($membresias->isEmpty())
            <div class="main-card text-center">
                <h2 class="text-lg font-black text-slate-900 dark:text-white">No hay disciplinas Fénix disponibles</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Las disciplinas se administran desde el catálogo existente. No se crean planes duplicados en esta pantalla.
                </p>
            </div>
        @else
            <section aria-label="Planes del gimnasio" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($membresias as $membresia)
                    <a
                        href="{{ route('membresias.index', ['disciplina' => $membresia->id]) }}"
                        wire:navigate
                        @class([
                            'rounded-2xl border-2 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                            'border-orange-500 bg-slate-950 text-white' => (int) $membresia->id === (int) ($membresiaSeleccionada?->id),
                            'border-slate-200 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-800 dark:text-white' => (int) $membresia->id !== (int) ($membresiaSeleccionada?->id),
                        ])
                    >
                        <span class="text-xs font-black uppercase tracking-widest text-orange-500">Disciplina</span>
                        <h2 class="mt-2 text-xl font-black">{{ $membresia->nombre }}</h2>
                        <p class="mt-3 text-sm font-semibold">
                            @if ($membresia->precio !== null)
                                ${{ number_format((float) $membresia->precio, 2, ',', '.') }}
                        @else
                                Precio pendiente de definición
                            @endif
                        </p>
                        <p class="mt-1 text-xs opacity-75">{{ $membresia->grupos->count() }} grupos</p>
                    </a>
                @endforeach
            </section>

            @if ($membresiaSeleccionada)
                <section class="main-card space-y-6">
                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between dark:border-slate-700">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-orange-600">Membresía / disciplina</p>
                            <h2 class="mt-1 text-2xl font-black text-slate-950 dark:text-white">{{ $membresiaSeleccionada->nombre }}</h2>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                Precio:
                                @if ($membresiaSeleccionada->precio !== null)
                                    <strong>${{ number_format((float) $membresiaSeleccionada->precio, 2, ',', '.') }}</strong>
                                @else
                                    <strong>Sin definir</strong>
                                @endif
                            </p>
                        </div>

                        @if (auth()->user()->rol === 'admin')
                            <div class="flex flex-wrap gap-2">
                                <a
                                    href="{{ route('disciplinas.edit', ['disciplina' => $membresiaSeleccionada->id, 'origen' => 'membresias']) }}"
                                    class="btn-secondary"
                                    wire:navigate
                                >Editar membresía</a>
                                <a
                                    href="{{ route('grupos.create', ['disciplina_id' => $membresiaSeleccionada->id, 'origen' => 'membresias']) }}"
                                    class="btn-primary"
                                    wire:navigate
                                >Crear grupo</a>
                            </div>
                        @endif
                    </div>

                    @if ($membresiaSeleccionada->grupos->isEmpty())
                        <p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-600 dark:border-slate-600 dark:text-slate-300">
                            Todavía no hay grupos configurados para esta disciplina.
                        </p>
                    @else
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($membresiaSeleccionada->grupos as $grupo)
                                @php
                                    $confirmados = (int) $grupo->alumnos_confirmados_count;
                                    $limite = $grupo->limiteCupo();
                                    $vacantes = max(0, $limite - $confirmados);
                                @endphp
                                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-lg font-black text-slate-950 dark:text-white">{{ $grupo->nombre }}</h3>
                                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $grupo->disciplina->nombre }}</p>
                                        </div>
                                        @if ($grupo->estado !== 'activo')
                                            <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-black text-slate-700 dark:bg-slate-700 dark:text-slate-200">Inactivo</span>
                                        @elseif ($vacantes === 0)
                                            <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-black text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">Completo</span>
                                        @else
                                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">Inscripción abierta</span>
                                        @endif
                                    </div>

                                    <div class="mt-4">
                                        <p class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Días y horarios</p>
                                        @forelse ($grupo->horarios as $horario)
                                            <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                {{ $horario->dia_semana_label }}, {{ substr($horario->hora_inicio, 0, 5) }}–{{ substr($horario->hora_fin, 0, 5) }}
                                            </p>
                                        @empty
                                            <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Sin horarios configurados</p>
                                        @endforelse
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-100 pt-4 text-sm dark:border-slate-700">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">Alumnos: {{ $confirmados }}/{{ $limite }}</span>
                                        <span class="font-bold text-slate-600 dark:text-slate-300">Vacantes: {{ $vacantes }}</span>
                                    </div>

                                    @if (auth()->user()->rol === 'admin')
                                        <div class="mt-4">
                                            <a
                                                href="{{ route('grupos.edit', ['grupo' => $grupo->id, 'origen' => 'membresias']) }}"
                                                class="btn-action-edit"
                                                wire:navigate
                                            >Editar grupo</a>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        @endif
    </div>
</div>
