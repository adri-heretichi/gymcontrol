{{--
    Componente: x-gym.tarjeta-indicador
    Tarjeta de indicador para los dashboards de Administración y Recepción.
    No calcula ni consulta nada: muestra los valores que recibe.

    Propiedades:
        titulo      (obligatoria)  Ej: "Personas presentes"
        valor       (obligatoria)  Ej: 12. Si viene vacío muestra un guión.
        descripcion (opcional)     Ej: "Dentro del gimnasio"
        tono        (opcional)     naranja (por defecto) | verde | rojo | ambar | gris

    Slot con nombre opcional: icono

    Uso:
        <x-gym.tarjeta-indicador titulo="Personas presentes" valor="12" descripcion="Dentro del gimnasio" />
        <x-gym.tarjeta-indicador titulo="Accesos denegados" :valor="$denegadosHoy" tono="rojo" />
--}}
@props([
    'titulo',
    'valor',
    'descripcion' => null,
    'tono' => 'naranja',
])

@php
    $tonos = [
        'naranja' => [
            'barra' => 'bg-orange-500',
            'valor' => 'text-orange-600 dark:text-orange-400',
            'icono' => 'bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-400',
        ],
        'verde' => [
            'barra' => 'bg-emerald-500',
            'valor' => 'text-emerald-600 dark:text-emerald-400',
            'icono' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400',
        ],
        'rojo' => [
            'barra' => 'bg-red-500',
            'valor' => 'text-red-600 dark:text-red-400',
            'icono' => 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400',
        ],
        'ambar' => [
            'barra' => 'bg-amber-500',
            'valor' => 'text-amber-600 dark:text-amber-400',
            'icono' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400',
        ],
        'gris' => [
            'barra' => 'bg-slate-400',
            'valor' => 'text-slate-900 dark:text-white',
            'icono' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        ],
    ];

    $estilo = $tonos[$tono] ?? $tonos['naranja'];

    // El 0 se muestra como 0; solo null o texto vacío se muestran como guión.
    $valorVisible = blank($valor) ? '—' : $valor;
@endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-3xl border border-slate-100 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-800']) }}>
    <span class="absolute inset-x-0 top-0 h-1 {{ $estilo['barra'] }}" aria-hidden="true"></span>

    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-2xs font-extrabold uppercase tracking-widest text-slate-500 dark:text-slate-300">
                {{ $titulo }}
            </p>

            <p class="mt-2 break-words text-3xl font-black {{ $estilo['valor'] }}">
                {{ $valorVisible }}
            </p>

            @if (filled($descripcion))
                <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    {{ $descripcion }}
                </p>
            @endif
        </div>

        @isset($icono)
            <div class="shrink-0 rounded-xl p-3 {{ $estilo['icono'] }}">
                {{ $icono }}
            </div>
        @endisset
    </div>
</div>