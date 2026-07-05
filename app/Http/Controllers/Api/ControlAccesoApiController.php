<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Socio;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ControlAccesoApiController extends Controller
{
    /**
     * Procesa un escaneo de identificador (DNI o PIN token) en el molinete.
     */
    public function scan(Request $request)
    {
        $request->validate([
            'identificador' => 'required|string',
        ]);

        $term = trim($request->input('identificador'));

        // 1. Buscar socio por DNI o Token
        $socio = Socio::where('dni', $term)
            ->orWhere('token', $term)
            ->first();

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Socio no encontrado',
                'errors' => [
                    'identificador' => ['El DNI o Token ingresado no pertenece a ningún socio registrado.']
                ]
            ], 404);
        }

        // 2. Determinar si ya está en sala (asistencia abierta hoy)
        $asistenciaAbierta = Asistencia::where('socio_id', $socio->id)
            ->whereNull('hora_salida')
            ->first();

        if ($asistenciaAbierta) {
            // --- CASO DE SALIDA ---
            $fechaIngreso = Carbon::parse($asistenciaAbierta->fecha->format('Y-m-d') . ' ' . $asistenciaAbierta->hora_ingreso);
            $fechaSalida = Carbon::now();
            $tiempo_permanencia = max(0, (int) $fechaIngreso->diffInMinutes($fechaSalida));

            $asistenciaAbierta->update([
                'hora_salida' => $fechaSalida->format('H:i:s'),
                'tiempo_permanencia' => $tiempo_permanencia,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'tipo_marca' => 'salida',
                    'socio' => [
                        'id' => $socio->id,
                        'nombre' => $socio->nombre,
                        'apellido' => $socio->apellido,
                        'dni' => $socio->dni,
                    ],
                    'tiempo_permanencia_minutos' => $tiempo_permanencia,
                    'mensaje' => 'Salida registrada con éxito. ¡Hasta luego!'
                ]
            ], 200);
        } else {
            // --- CASO DE INGRESO ---
            $motivos = [];

            // Validación A: Socio activo
            if ($socio->estado !== 'activo') {
                $motivos[] = 'El socio se encuentra INACTIVO.';
            }

            // Validación B: Membresía vigente
            if (!$socio->membresia_id) {
                $motivos[] = 'El socio no tiene ninguna membresía contratada.';
            } elseif (!$socio->fecha_vencimiento) {
                $motivos[] = 'El socio no tiene una fecha de vencimiento registrada.';
            } elseif ($socio->fecha_vencimiento->isPast() && !$socio->fecha_vencimiento->isToday()) {
                $motivos[] = 'La membresía ha EXPIRADO el ' . $socio->fecha_vencimiento->format('d/m/Y') . '.';
            }

            // Validación C: Certificado Médico / Apto Físico vigente
            if (!$socio->aptoFisicoVigente()) {
                $motivos[] = 'Falta Certificado Médico (Apto Físico) vigente.';
            }

            if (count($motivos) > 0) {
                // ACCESO DENEGADO
                return response()->json([
                    'success' => false,
                    'message' => 'Ingreso denegado: el socio posee impedimentos de acceso.',
                    'errors' => $motivos
                ], 400);
            }

            // ACCESO PERMITIDO
            $asistencia = Asistencia::create([
                'socio_id' => $socio->id,
                'fecha' => Carbon::today(),
                'hora_ingreso' => Carbon::now()->format('H:i:s'),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'tipo_marca' => 'ingreso',
                    'socio' => [
                        'id' => $socio->id,
                        'nombre' => $socio->nombre,
                        'apellido' => $socio->apellido,
                        'dni' => $socio->dni,
                    ],
                    'asistencia_id' => $asistencia->id,
                    'mensaje' => '¡Ingreso Autorizado! Bienvenido/a.'
                ]
            ], 200);
        }
    }
}
