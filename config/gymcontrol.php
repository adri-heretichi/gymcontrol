<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Restricción de red local para la terminal
    |--------------------------------------------------------------------------
    |
    | Define si el acceso a la ruta /terminal se restringe únicamente
    | a direcciones IP locales (127.0.0.1, ::1, Clase C y Clase A).
    |
    */
    'terminal_solo_red_local' => env('TERMINAL_SOLO_RED_LOCAL', true),
];
