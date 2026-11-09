<?php

namespace App\Http\Controllers;

use App\Services\ControlAccesoService;
use Illuminate\Http\Request;

class ScanQrController extends Controller
{
    /**
     * La apertura del QR solo consulta reglas; no registra entrada ni salida.
     */
    public function scan(Request $request, string $token, ControlAccesoService $controlAcceso)
    {
        $resultado = $controlAcceso->evaluarAcceso(
            identificador: $token,
            metodo: ControlAccesoService::METODO_QR,
            grupoId: $request->integer('grupo_id') ?: null
        );

        $puedeSalir = $resultado['socio'] !== null
            && $resultado['socio']->tieneCredencialesActivas()
            && $resultado['socio']->asistencias()->whereNull('hora_salida')->exists();

        return $this->respuestaMovil($resultado, $token, false, $puedeSalir);
    }

    /**
     * Acción explícita de ingreso. El token se recibe en el cuerpo y nunca se
     * incluye en respuestas, auditorías o mensajes.
     */
    public function ingresar(Request $request, ControlAccesoService $controlAcceso)
    {
        $datos = $request->validate([
            'identificador' => ['required', 'string', 'max:64'],
            'grupo_id' => ['nullable', 'integer', 'exists:grupos,id'],
        ]);

        $resultado = $controlAcceso->registrarIngreso(
            identificador: $datos['identificador'],
            metodo: ControlAccesoService::METODO_QR,
            grupoId: $datos['grupo_id'] ?? null,
            origen: 'qr_movil'
        );

        return $this->respuestaMovil(
            $resultado,
            $datos['identificador'],
            true,
            $resultado['socio'] !== null
                && $resultado['socio']->tieneCredencialesActivas()
                && $resultado['socio']->asistencias()->whereNull('hora_salida')->exists()
        );
    }

    public function salir(Request $request, ControlAccesoService $controlAcceso)
    {
        $datos = $request->validate([
            'identificador' => ['required', 'string', 'max:64'],
        ]);

        $resultado = $controlAcceso->registrarSalidaPorCredencial(
            identificador: $datos['identificador'],
            metodo: ControlAccesoService::METODO_QR,
            metodoSalida: 'qr_movil'
        );

        return $this->respuestaMovil($resultado, $datos['identificador'], true, false);
    }

    private function respuestaMovil(array $resultado, string $token, bool $desdePost, bool $puedeSalir)
    {
        return response()
            ->view('terminal.scan-mobile', [
                'resultado' => $resultado,
                'token' => $token,
                'desdePost' => $desdePost,
                'puedeSalir' => $puedeSalir,
            ], $resultado['autorizado'] ? 200 : 403)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
