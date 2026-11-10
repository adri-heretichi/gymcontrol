{{--
    Componente: x-gym.alerta
    Mensaje de éxito, error, advertencia o información.
    No ejecuta acciones ni modifica registros.

    Propiedades:
        tipo    (opcional, por defecto "info")  exito | error | advertencia | info
        mensaje (opcional)  si no se pasa, se usa el contenido entre etiquetas

    Uso:
        <x-gym.alerta tipo="exito" mensaje="Pago registrado correctamente." />
        <x-gym.alerta tipo="advertencia">Inscripción pendiente de aprobación.</x-gym.alerta>
--}}
@props([
    'tipo' => 'info',
    'mensaje' => null,
])

@php
    $tipos = [
        'exito' => [
            'clases' => 'border-emerald-500 bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
            'titulo' => 'Éxito',
            'icono'  => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'error' => [
            'clases' => 'border-red-500 bg-red-50 text-red-900 dark:bg-red-950/40 dark:text-red-200',
            'titulo' => 'Error',
            'icono'  => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'advertencia' => [
            'clases' => 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
            'titulo' => 'Advertencia',
            'icono'  => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
        ],
        'info' => [
            'clases' => 'border-sky-500 bg-sky-50 text-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
            'titulo' => 'Información',
            'icono'  => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
        ],
    ];

    $clave = mb_strtolower(trim((string) $tipo));

    // Tipo desconocido: se muestra como "info".
    $config = $tipos[$clave] ?? $tipos['info'];

    $rol = in_array($clave, ['error', 'advertencia'], true) ? 'alert' : 'status';
@endphp

<div role="{{ $rol }}" {{ $attributes->merge(['class' => 'flex w-full items-start gap-3 rounded-xl border-l-4 p-4 text-sm font-semibold ' . $config['clases']]) }}>
    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $config['icono'] }}" />
    </svg>

    <div class="min-w-0 flex-1 break-words">
        <span class="sr-only">{{ $config['titulo'] }}:</span>
        {{ filled($mensaje) ? $mensaje : $slot }}
    </div>
</div>