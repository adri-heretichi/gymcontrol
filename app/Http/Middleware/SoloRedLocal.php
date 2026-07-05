<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SoloRedLocal
{
    /**
     * Maneja la petición entrante y valida si proviene de una IP de la red local.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si la restricción de red local no está activa, permitir el paso
        if (!config('gymcontrol.terminal_solo_red_local', true)) {
            return $next($request);
        }

        $ip = $request->ip();

        // 1. Validar rangos de IP locales
        if (!$this->isLocalIp($ip)) {
            abort(403, 'Acceso permitido solo desde la red local del gimnasio');
        }

        return $next($request);
    }

    /**
     * Evalúa si una dirección IP está dentro de los rangos locales requeridos.
     */
    private function isLocalIp(string $ip): bool
    {
        // localhost IPv4 e IPv6
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        // Redes locales Clase C (192.168.x.x) o Clase A (10.x.x.x)
        if (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return true;
        }

        return false;
    }
}
