<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ControlAccesoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ControlAccesoApiController extends Controller
{
    /**
     * Registra una acción explícita. No busca por DNI ni alterna entre entrada y salida.
     */
    public function scan(Request $request, ControlAccesoService $controlAcceso)
    {
        Gate::authorize('controlar-acceso');

        $datos = $request->validate([
            'identificador' => ['required', 'string', 'max:64'],
            'metodo' => ['required', 'in:pin,qr'],
            'accion' => ['required', 'in:ingreso,salida'],
            'grupo_id' => ['nullable', 'integer', 'exists:grupos,id'],
        ]);

        $resultado = $datos['accion'] === 'ingreso'
            ? $controlAcceso->registrarIngreso(
                identificador: $datos['identificador'],
                metodo: $datos['metodo'],
                grupoId: $datos['grupo_id'] ?? null,
                operadorId: $request->user()->id,
                origen: $datos['metodo'] === 'qr' ? 'qr_movil' : 'pin_recepcion'
            )
            : $controlAcceso->registrarSalidaPorCredencial(
                identificador: $datos['identificador'],
                metodo: $datos['metodo'],
                operadorId: $request->user()->id,
                metodoSalida: $datos['metodo'] === 'qr' ? 'qr_movil' : 'pin_recepcion'
            );

        if ($request->user()->esRecepcionista()) {
            $resultado = $controlAcceso->ocultarDetalleMedico($resultado);
        }

        return response()->json([
            'success' => $resultado['autorizado'],
            'data' => [
                'autorizado' => $resultado['autorizado'],
                'codigo' => $resultado['codigo'],
                'mensaje' => $resultado['mensaje'],
                'socio' => $resultado['socio'] ? [
                    'id' => $resultado['socio']->id,
                    'nombre' => $resultado['socio']->nombre,
                    'apellido' => $resultado['socio']->apellido,
                ] : null,
                'actividad' => $resultado['actividad'],
                'dia' => $resultado['dia'],
                'horario' => $resultado['horario'],
                'asistencia_id' => $resultado['asistencia']?->id,
            ],
        ], $resultado['autorizado'] ? 200 : 403);
    }
}
