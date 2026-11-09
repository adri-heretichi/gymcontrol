<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Services\ControlAccesoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AsistenciaApiController extends Controller
{
    /**
     * Lista el historial de asistencias con filtros de búsqueda y fechas.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'buscar' => ['sometimes', 'string', 'max:100'],
            'fecha_desde' => ['sometimes', 'date_format:Y-m-d'],
            'fecha_hasta' => ['sometimes', 'date_format:Y-m-d'],
            'estado' => ['sometimes', 'in:en_sala,finalizados'],
        ]);
        if (isset($validated['fecha_desde'], $validated['fecha_hasta'])
            && $validated['fecha_desde'] > $validated['fecha_hasta']) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha desde no puede ser posterior a la fecha hasta.',
            ], 422);
        }

        $query = Asistencia::query()
            ->select([
                'id', 'socio_id', 'fecha', 'hora_ingreso', 'hora_salida',
                'fecha_salida', 'tiempo_permanencia', 'metodo_acceso',
                'metodo_salida', 'dispositivo_origen',
            ])
            ->with('socio:id,nombre,apellido,dni');

        if (isset($validated['buscar'])) {
            $buscar = $validated['buscar'];
            $query->whereHas('socio', function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%");
            });
        }

        if (isset($validated['fecha_desde'])) {
            $query->where('fecha', '>=', $validated['fecha_desde']);
        }

        if (isset($validated['fecha_hasta'])) {
            $query->where('fecha', '<=', $validated['fecha_hasta']);
        }

        if (isset($validated['estado'])) {
            $estado = $validated['estado'];
            if ($estado === 'en_sala') {
                $query->whereNull('hora_salida');
            } else {
                $query->whereNotNull('hora_salida');
            }
        }

        $asistencias = $query->orderBy('fecha', 'desc')
                            ->orderBy('hora_ingreso', 'desc')
                            ->paginate(15)
                            ->through(fn (Asistencia $asistencia) => $this->presentar($asistencia));

        return response()->json(['success' => true, 'data' => $asistencias]);
    }

    /**
     * Muestra el detalle de una asistencia específica.
     */
    public function show($id)
    {
        $asistencia = Asistencia::query()
            ->select([
                'id', 'socio_id', 'fecha', 'hora_ingreso', 'hora_salida',
                'fecha_salida', 'tiempo_permanencia', 'metodo_acceso',
                'metodo_salida', 'dispositivo_origen',
            ])
            ->with('socio:id,nombre,apellido,dni')
            ->find($id);

        if (!$asistencia) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de asistencia no encontrado'
            ], 404);
        }

        return response()->json(['success' => true, 'data' => $this->presentar($asistencia)]);
    }

    /**
     * Edita un registro de asistencia existente (Solo Admin).
     */
    public function update(Request $request, $id, ControlAccesoService $controlAcceso)
    {
        Gate::authorize('gestionar-asistencias');

        $asistencia = Asistencia::find($id);

        if (!$asistencia) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de asistencia no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'fecha' => 'sometimes|required|date|before_or_equal:today',
            'hora_ingreso' => 'sometimes|required|date_format:H:i:s',
            'fecha_salida' => 'nullable|date|before_or_equal:today',
            'hora_salida' => 'nullable|date_format:H:i:s',
        ]);

        $fecha = $validated['fecha'] ?? $asistencia->fecha->toDateString();
        $horaIngreso = $validated['hora_ingreso'] ?? $asistencia->hora_ingreso;
        $horaSalida = array_key_exists('hora_salida', $validated)
            ? $validated['hora_salida']
            : $asistencia->hora_salida;
        $fechaSalida = array_key_exists('fecha_salida', $validated)
            ? $validated['fecha_salida']
            : ($horaSalida ? ($asistencia->fecha_salida?->toDateString() ?? $fecha) : null);
        if (($fechaSalida === null) !== ($horaSalida === null)) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha y la hora de salida deben indicarse juntas.'
            ], 422);
        }

        $asistencia = $controlAcceso->corregirPermanencia(
            $asistencia,
            $fecha,
            $horaIngreso,
            $fechaSalida,
            $horaSalida,
            $request->user()
        );

        return response()->json(['success' => true, 'data' => $this->presentar($asistencia)]);
    }

    private function presentar(Asistencia $asistencia): array
    {
        return [
            'id' => $asistencia->id,
            'socio' => $asistencia->socio ? [
                'id' => $asistencia->socio->id,
                'nombre' => $asistencia->socio->nombre,
                'apellido' => $asistencia->socio->apellido,
                'dni' => $asistencia->socio->dni,
            ] : null,
            'fecha' => $asistencia->fecha?->toDateString(),
            'hora_ingreso' => $asistencia->hora_ingreso,
            'fecha_salida' => $asistencia->fecha_salida?->toDateString(),
            'hora_salida' => $asistencia->hora_salida,
            'tiempo_permanencia' => $asistencia->tiempo_permanencia,
            'metodo_acceso' => $asistencia->metodo_acceso,
            'metodo_salida' => $asistencia->metodo_salida,
            'dispositivo_origen' => $asistencia->dispositivo_origen,
        ];
    }
}
