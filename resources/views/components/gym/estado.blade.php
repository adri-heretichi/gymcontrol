{{--
    Componente: x-gym.estado
    Etiqueta de color para el estado de un acceso, un socio o una cuota.
    NO calcula el estado: solo lo muestra con su color.

    Propiedades:
        estado (obligatoria)  autorizado | denegado | pendiente | vencido | activo | inactivo
        texto  (opcional)     reemplaza el texto por defecto

    Uso:
        <x-gym.estado estado="autorizado" />
        <x-gym.estado estado="denegado" texto="Acceso denegado" />
--}}
@props([
    'estado',
    'texto' => null,
])

@php
    // Normalizamos: " Autorizado " se convierte en "autorizado".
    $clave = mb_strtolower(trim((string) $estado));

    // Clases de Tailwind escritas COMPLETAS: Tailwind solo genera
    // las clases que encuentra escritas tal cual en los archivos.
    $verde = 'bg-emerald-100 text-emerald-800 ring-emerald-600/30 dark:bg-emerald-900/40 dark:text-emerald-300';
    $rojo  = 'bg-red-100 text-red-800 ring-red-600/30 dark:bg-red-900/40 dark:text-red-300';
    $ambar = 'bg-amber-100 text-amber-900 ring-amber-600/30 dark:bg-amber-900/40 dark:text-amber-300';
    $gris  = 'bg-slate-200 text-slate-700 ring-slate-500/30 dark:bg-slate-700 dark:text-slate-200';
    $neutral = 'bg-white text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600';

    $colores = [
        'autorizado' => $verde,
        'activo'     => $verde,
        'denegado'   => $rojo,
        'vencido'    => $rojo,
        'pendiente'  => $ambar,
        'inactivo'   => $gris,
    ];

    $textosPorDefecto = [
        'autorizado' => 'Autorizado',
        'activo'     => 'Activo',
        'denegado'   => 'Denegado',
        'vencido'    => 'Vencido',
        'pendiente'  => 'Pendiente',
        'inactivo'   => 'Inactivo',
    ];

    // Estado desconocido: se muestra con estilo neutral.
    $clasesColor = $colores[$clave] ?? $neutral;

    if (filled($texto)) {
        $textoVisible = $texto;
    } elseif (isset($textosPorDefecto[$clave])) {
        $textoVisible = $textosPorDefecto[$clave];
    } elseif ($clave !== '') {
        $textoVisible = \Illuminate\Support\Str::ucfirst($clave);
    } else {
        $textoVisible = 'Sin estado';
    }
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ring-1 ring-inset ' . $clasesColor]) }}>
    <span class="h-2 w-2 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
    {{ $textoVisible }}
</span>