{{--
    Componente: x-gym.dato
    Muestra un dato del socio en formato "etiqueta + valor".
    Solo muestra lo que recibe: no consulta modelos ni la base de datos.

    Propiedades:
        etiqueta (obligatoria)  Ej: "Disciplina"
        valor    (opcional)     Ej: "Funcional". Si viene vacío muestra "Sin datos".

    Uso:
        <x-gym.dato etiqueta="Disciplina" valor="Funcional" />
        <x-gym.dato etiqueta="Horario" :valor="$horario" />
--}}
@props([
    'etiqueta',
    'valor' => null,
])

@php
    // Si la etiqueta habla de PIN, token o contraseña, nunca mostramos el valor.
    $etiquetaSimple = (string) \Illuminate\Support\Str::of($etiqueta)->lower()->ascii();
    $esDatoPrivado = preg_match('/\b(pin|token|contrasena|password|clave)\b/', $etiquetaSimple) === 1;

    // blank(): true para null, "" o solo espacios. El número 0 NO cuenta como vacío.
    $estaVacio = blank($valor);
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <p class="text-2xs font-extrabold uppercase tracking-widest text-slate-500 dark:text-slate-400">
        {{ $etiqueta }}
    </p>

    <p class="mt-1 break-words text-sm font-semibold text-slate-900 dark:text-white">
        @if ($esDatoPrivado)
            <span class="italic text-slate-400">Dato protegido</span>
        @elseif ($estaVacio)
            <span class="italic text-slate-400">Sin datos</span>
        @else
            {{ $valor }}
        @endif
    </p>
</div>