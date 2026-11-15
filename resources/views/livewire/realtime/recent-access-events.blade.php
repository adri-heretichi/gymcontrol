<section id="eventos-acceso" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-800 dark:bg-slate-800">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">Últimos eventos de acceso</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-300">Resultado y motivo operativo, sin credenciales ni detalles médicos.</p>
                </div>
                <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    {{ count($ultimosEventosAcceso) }}
                </span>
            </div>
            <div class="max-h-60 space-y-2 overflow-y-auto">
                @forelse($ultimosEventosAcceso as $evento)
                    <div wire:key="acceso-{{ $evento['id'] }}" class="flex flex-col gap-1 rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/50 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $evento['socio'] }}</span>
                            <span class="ml-2 text-xs text-slate-500 dark:text-slate-400">{{ $evento['operacion'] }} · {{ $evento['metodo'] }}</span>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $evento['motivo'] }}</p>
                        </div>
                        <time class="shrink-0 text-xs text-slate-500">{{ $evento['momento']?->format('d/m/Y H:i') }}</time>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">Todavía no hay intentos de acceso registrados.</p>
                @endforelse
            </div>
        </section>
