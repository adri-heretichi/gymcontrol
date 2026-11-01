{{--
    Componente: x-gym.marca
    Identidad de GymControl (software) y Gimnasio Fénix (cliente).

    Propiedad:
        variante (opcional)  panel (por defecto) | terminal
            panel:    Fénix y GymControl equilibrados, en una fila.
            terminal: Fénix grande y centrado, GymControl como referencia secundaria.

    Uso:
        <x-gym.marca />
        <x-gym.marca variante="terminal" />
--}}
@props([
    'variante' => 'panel',
])

@php
    $rutaLogo = 'images/fenix/logo-oficial.jpg';

    // Si el logo todavía no se copió a public/, mostramos el nombre en texto.
    $hayLogo = file_exists(public_path($rutaLogo));

    $esTerminal = $variante === 'terminal';
@endphp

@if ($esTerminal)
    <div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-4 text-center']) }}>
        @if ($hayLogo)
            <img src="{{ asset($rutaLogo) }}" alt="Gimnasio Fénix"
                 class="h-32 w-auto max-w-full rounded-2xl object-contain shadow-lg shadow-orange-500/20 sm:h-44">
        @else
            <span class="rounded-2xl bg-black px-6 py-4 text-3xl font-black uppercase tracking-widest text-amber-400 sm:text-5xl">
                Gimnasio Fénix
            </span>
        @endif

        <span class="flex items-center gap-2 text-2xs font-bold uppercase tracking-widest text-slate-400">
            Sistema
            <x-application-logo class="!text-sm" />
        </span>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3 sm:gap-4']) }}>
        @if ($hayLogo)
            <img src="{{ asset($rutaLogo) }}" alt="Gimnasio Fénix"
                 class="h-12 w-auto rounded-lg object-contain sm:h-14">
        @else
            <span class="rounded-lg bg-black px-3 py-2 text-sm font-black uppercase tracking-widest text-amber-400">
                Fénix
            </span>
        @endif

        <span class="h-10 w-px bg-orange-500" aria-hidden="true"></span>

        <div class="leading-tight">
            <x-application-logo />
            <span class="block text-2xs font-bold uppercase tracking-widest text-orange-600 dark:text-orange-400">
                Gimnasio Fénix
            </span>
        </div>
    </div>
@endif