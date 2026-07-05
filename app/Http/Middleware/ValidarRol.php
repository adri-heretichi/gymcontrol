<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidarRol
{
    /**
     * Maneja una petición entrante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $rol  El rol requerido para acceder a la ruta
     */
    public function handle(Request $request, Closure $next, string $rol): Response
    {
        // 1. Verificamos si el usuario ha iniciado sesión
        if (!$request->user()) {
            abort(401, 'No autenticado.');
        }

        // 2. Comparamos el rol del usuario logueado con el rol requerido por la ruta
        if ($request->user()->rol !== $rol) {
            // Si no coincide, abortamos con un código 403 (Prohibido) y un mensaje descriptivo
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }

        // Si pasa las validaciones, permitimos que continúe hacia la ruta correspondiente
        return $next($request);
    }
}
