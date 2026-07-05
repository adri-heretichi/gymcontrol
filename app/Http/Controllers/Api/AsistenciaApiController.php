<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Socio;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AsistenciaApiController extends Controller
{
    /**
     * Lista el historial de asistencias con filtros de búsqueda y fechas.
     */
    public function index(Request $request)
    {
        $query = Asistencia::with('socio.membresia');

        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->whereHas('socio', function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('estado')) {
            $estado = $request->input('estado');
            if ($estado === 'en_sala') {
                $query->whereNull('hora_salida');
            } elseif ($estado === 'finalizados') {
                $query->whereNotNull('hora_salida');
            }
        }

        $asistencias = $query->orderBy('fecha', 'desc')
                            ->orderBy('hora_ingreso', 'desc')
                            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $asistencias
        ], 200);
    }

    /**
     * Muestra el detalle de una asistencia específica.
     */
    public function show($id)
    {
        $asistencia = Asistencia::with('socio.membresia')->find($id);

        if (!$asistencia) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de asistencia no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $asistencia
        ], 200);
    }

    /**
     * Registra manualmente una marca de asistencia.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'socio_id' => 'required|exists:socios,id',
            'fecha' => 'required|date|before_or_equal:today',
            'hora_ingreso' => 'required',
            'hora_salida' => 'nullable',
        ]);

        $socio = Socio::findOrFail($validated['socio_id']);

        if ($socio->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede registrar asistencia para un socio inactivo'
            ], 400);
        }

        $tiempo_permanencia = null;

        if ($request->filled('hora_salida')) {
            $ingreso = Carbon::parse($validated['fecha'] . ' ' . $validated['hora_ingreso']);
            $salida = Carbon::parse($validated['fecha'] . ' ' . $validated['hora_salida']);

            if ($salida->lte($ingreso)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La hora de salida debe ser posterior a la de ingreso.'
                ], 422);
            }

            $tiempo_permanencia = (int) $ingreso->diffInMinutes($salida);
        }

        $asistencia = Asistencia::create([
            'socio_id' => $validated['socio_id'],
            'fecha' => $validated['fecha'],
            'hora_ingreso' => Carbon::parse($validated['hora_ingreso'])->format('H:i:s'),
            'hora_salida' => $request->filled('hora_salida') ? Carbon::parse($validated['hora_salida'])->format('H:i:s') : null,
            'tiempo_permanencia' => $tiempo_permanencia,
        ]);

        return response()->json([
            'success' => true,
            'data' => $asistencia
        ], 201);
    }

    /**
     * Edita un registro de asistencia existente (Solo Admin).
     */
    public function update(Request $request, $id)
    {
        // Defensa de rol adicional
        if ($request->user()->rol !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Rol Administrador requerido.'
            ], 403);
        }

        $asistencia = Asistencia::find($id);

        if (!$asistencia) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de asistencia no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'fecha' => 'sometimes|required|date|before_or_equal:today',
            'hora_ingreso' => 'sometimes|required',
            'hora_salida' => 'nullable',
        ]);

        $fecha = $request->input('fecha', $asistencia->fecha->format('Y-m-d'));
        $horaIngreso = $request->input('hora_ingreso', $asistencia->hora_ingreso);
        $horaSalida = $request->input('hora_salida', $asistencia->hora_salida);

        $tiempo_permanencia = null;

        if ($horaSalida) {
            $ingreso = Carbon::parse($fecha . ' ' . $horaIngreso);
            $salida = Carbon::parse($fecha . ' ' . $horaSalida);

            if ($salida->lte($ingreso)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La hora de salida debe ser posterior a la de ingreso.'
                ], 422);
            }

            $tiempo_permanencia = (int) $ingreso->diffInMinutes($salida);
        }

        $asistencia->update([
            'fecha' => $fecha,
            'hora_ingreso' => Carbon::parse($horaIngreso)->format('H:i:s'),
            'hora_salida' => $horaSalida ? Carbon::parse($horaSalida)->format('H:i:s') : null,
            'tiempo_permanencia' => $tiempo_permanencia,
        ]);

        return response()->json([
            'success' => true,
            'data' => $asistencia
        ], 200);
    }
}
