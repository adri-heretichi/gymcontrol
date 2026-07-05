<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Socio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ScanQrController extends Controller
{
    /**
     * Procesa la solicitud de escaneo QR desde el celular del socio.
     */
    public function scan(Request $request, $token)
    {
        $socio = Socio::where('token', $token)->first();

        // Si se pide el estado "ready" para el próximo uso
        if ($request->has('ready')) {
            return view('terminal.scan-ready', compact('socio'));
        }

        // 1. Si no existe: mostrar pantalla roja "Token no válido"
        if (!$socio) {
            $estado = 'error';
            $mensaje = 'Token no válido';
            $nombre = '';
            $motivo = 'El token escaneado no pertenece a ningún socio registrado.';
            
            Cache::put('last_terminal_scan', [
                'time' => now()->timestamp,
                'estado' => $estado,
                'mensaje' => $mensaje,
                'motivo' => $motivo,
                'socio_id' => null,
            ], 10);

            return view('terminal.scan-result', compact('estado', 'mensaje', 'nombre', 'motivo', 'token'));
        }

        // Evitar procesar doble si hay un marcado en los últimos 10 segundos (recargas de página)
        $recentAsistencia = Asistencia::where('socio_id', $socio->id)
            ->where('fecha', today())
            ->where(function ($query) {
                $query->where('created_at', '>=', now()->subSeconds(10))
                      ->orWhere('updated_at', '>=', now()->subSeconds(10));
            })
            ->first();

        if ($recentAsistencia) {
            if ($recentAsistencia->hora_salida) {
                $estado = 'salida';
                $nombre = $socio->nombre;
                $mensaje = "¡Hasta luego, {$nombre}!";
                $motivo = '';
            } else {
                $estado = 'exito';
                $nombre = $socio->nombre;
                $mensaje = "¡Bienvenido/a, {$nombre}!";
                $motivo = '';
            }
            return view('terminal.scan-result', compact('socio', 'estado', 'mensaje', 'nombre', 'motivo', 'token'));
        }

        // 2. Comprobar si ya tiene una asistencia abierta para hoy
        $asistenciaAbierta = Asistencia::where('socio_id', $socio->id)
            ->whereNull('hora_salida')
            ->first();

        if ($asistenciaAbierta) {
            // --- REGISTRAR SALIDA ---
            $fechaIngreso = Carbon::parse($asistenciaAbierta->fecha->format('Y-m-d') . ' ' . $asistenciaAbierta->hora_ingreso);
            $fechaSalida = Carbon::now();
            $tiempo_permanencia = max(0, (int) $fechaIngreso->diffInMinutes($fechaSalida));

            $asistenciaAbierta->update([
                'hora_salida' => $fechaSalida->format('H:i:s'),
                'tiempo_permanencia' => $tiempo_permanencia,
            ]);

            $estado = 'salida';
            $nombre = $socio->nombre;
            $mensaje = "¡Hasta luego, {$nombre}!";
            $motivo = '';

            // Guardar en Cache para la terminal física
            Cache::put('last_terminal_scan', [
                'time' => now()->timestamp,
                'estado' => $estado,
                'mensaje' => "Salida registrada. Tiempo en sala: {$tiempo_permanencia} min.",
                'motivo' => '',
                'socio_id' => $socio->id,
            ], 10);

        } else {
            // --- REGISTRAR INGRESO ---
            $motivos = [];

            // Validación A: Socio activo
            if ($socio->estado !== 'activo') {
                $motivos[] = 'El socio se encuentra INACTIVO.';
            }

            // Validación B: Membresía vigente
            if (!$socio->fecha_vencimiento || $socio->fecha_vencimiento->isPast() && !$socio->fecha_vencimiento->isToday()) {
                $vencimiento = $socio->fecha_vencimiento ? $socio->fecha_vencimiento->format('d/m/Y') : 'no registrada';
                $motivos[] = "Membresía vencida (Expiró el {$vencimiento}).";
            }

            // Validación C: Certificado médico / Apto físico vigente
            $tieneAptoVigente = $socio->aptosFisicos()
                ->where('estado', 'vigente')
                ->whereDate('fecha_vencimiento', '>=', Carbon::today())
                ->exists();

            if (!$tieneAptoVigente) {
                $motivos[] = 'Falta Certificado Médico (Apto Físico) vigente.';
            }

            if (count($motivos) > 0) {
                // Ingreso denegado
                $estado = 'error';
                $mensaje = 'Acceso Denegado';
                $nombre = $socio->nombre;
                $motivo = implode(' | ', $motivos);

                // Guardar en Cache para la terminal física
                Cache::put('last_terminal_scan', [
                    'time' => now()->timestamp,
                    'estado' => $estado,
                    'mensaje' => $mensaje,
                    'motivo' => $motivo,
                    'socio_id' => $socio->id,
                ], 10);
            } else {
                // Ingreso autorizado
                Asistencia::create([
                    'socio_id' => $socio->id,
                    'fecha' => Carbon::today(),
                    'hora_ingreso' => Carbon::now()->format('H:i:s'),
                ]);

                $estado = 'exito';
                $nombre = $socio->nombre;
                $mensaje = "¡Bienvenido/a, {$nombre}!";
                $motivo = '';

                // Guardar en Cache para la terminal física
                Cache::put('last_terminal_scan', [
                    'time' => now()->timestamp,
                    'estado' => 'exito',
                    'mensaje' => "Bienvenido, {$socio->nombre} {$socio->apellido}",
                    'motivo' => '',
                    'socio_id' => $socio->id,
                ], 10);
            }
        }

        return view('terminal.scan-result', compact('socio', 'estado', 'mensaje', 'nombre', 'motivo', 'token'));
    }
}
