@props(['module'])

@php
    $sections = match ($module) {
        'Membresías' => [
            ['Catálogo de membresías', 'membresias.index', 'membresias.*'],
            ['Disciplinas', 'disciplinas.index', 'disciplinas.*'],
            ['Grupos y horarios', 'grupos.index', 'grupos.*'],
        ],
        'Socios' => [
            ['Listado de socios', 'socios.index', 'socios.*'],
            ['Inscripciones', 'inscripciones.index', 'inscripciones.*'],
        ],
        'Control de Acceso' => [
            ['Ingreso y salida', 'control-acceso', 'control-acceso'],
            ['Historial de asistencias', 'asistencias.index', 'asistencias.*'],
        ],
    };
@endphp

<nav aria-label="Secciones de {{ $module }}" class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
    <p class="mb-2 text-xs font-black uppercase tracking-wider text-orange-700 dark:text-orange-300">{{ $module }}</p>
    <div class="flex flex-wrap gap-2">
        @foreach($sections as [$label, $route, $active])
            <a href="{{ route($route) }}" wire:navigate
                @if(request()->routeIs($active)) aria-current="page" @endif
                @class(['btn-primary' => request()->routeIs($active), 'btn-secondary' => !request()->routeIs($active)])>
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
