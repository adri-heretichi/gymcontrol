<?php

namespace App\Services;

use App\Events\GymRealtimeUpdate;
use App\Models\AptoFisico;
use App\Models\Auditoria;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * CertificadoMedicoService – Fase 6.
 *
 * Centraliza toda la lógica médica administrativa de Gimnasio Fénix:
 * 1. Decisión médica individual de la Dueña ('pendiente', 'requerido', 'no_requerido').
 * 2. Evaluación del requisito médico (Casos A, B, C, D, E).
 * 3. Registro administrativo de certificados médicos por recepción/administración.
 * 4. Consulta de historial y notificación/solicitud a la Dueña.
 * 5. Auditoría obligatoria con protección de datos sensibles.
 */
class CertificadoMedicoService
{
    public const ESTADO_PENDIENTE    = 'pendiente';
    public const ESTADO_REQUERIDO    = 'requerido';
    public const ESTADO_NO_REQUERIDO = 'no_requerido';

    /**
     * Evalúa integralmente el estado del requisito médico de un socio.
     *
     * Retorna un array con:
     *   - 'cumple'          => (bool) true si el requisito está satisfecho
     *   - 'caso'            => (string) 'A' | 'B' | 'C' | 'D' | 'E'
     *   - 'decision'        => (string) 'pendiente' | 'requerido' | 'no_requerido'
     *   - 'mensaje'         => (string) Explicación legible
     *   - 'certificado'     => (?AptoFisico) Último certificado registrado si existe
     *   - 'dias_restantes'  => (int) Días hasta el vencimiento (0 si no aplica o vencido)
     */
    public function evaluarRequisitoMedico(Socio $socio, ?string $fecha = null): array
    {
        $hoy = $fecha ? Carbon::parse($fecha)->startOfDay() : Carbon::now(config('app.timezone'))->startOfDay();
        $decision = $socio->decision_medica ?? self::ESTADO_PENDIENTE;

        // Caso A: Decisión pendiente
        if ($decision === self::ESTADO_PENDIENTE) {
            return [
                'cumple'         => false,
                'caso'           => 'A',
                'decision'       => self::ESTADO_PENDIENTE,
                'mensaje'        => 'Decisión médica pendiente de definición por la dueña.',
                'certificado'    => null,
                'dias_restantes' => 0,
            ];
        }

        // Caso B: No requiere certificado
        if ($decision === self::ESTADO_NO_REQUERIDO) {
            return [
                'cumple'         => true,
                'caso'           => 'B',
                'decision'       => self::ESTADO_NO_REQUERIDO,
                'mensaje'        => 'Requisito médico cumplido (no requiere certificado según decisión de la dueña).',
                'certificado'    => $this->obtenerUltimoCertificado($socio),
                'dias_restantes' => 0,
            ];
        }

        // Caso C-H: requiere una documentación aprobada y vigente.
        if ($decision === self::ESTADO_REQUERIDO) {
            $certificadoVigente = $socio->aptosFisicos()
                ->where('estado', AptoFisico::ESTADO_VIGENTE)
                ->whereDate('fecha_emision', '<=', $hoy->toDateString())
                ->whereDate('fecha_vencimiento', '>=', $hoy->toDateString())
                ->latest('fecha_emision')
                ->latest('id')
                ->first();

            if ($certificadoVigente) {
                $vencimiento = Carbon::parse($certificadoVigente->fecha_vencimiento)->startOfDay();
                $diasRestantes = (int) $hoy->diffInDays($vencimiento, false);

                return [
                    'cumple'         => true,
                    'caso'           => 'D',
                    'decision'       => self::ESTADO_REQUERIDO,
                    'mensaje'        => "Certificado médico aprobado y vigente hasta el {$vencimiento->format('d/m/Y')}.",
                    'certificado'    => $certificadoVigente,
                    'dias_restantes' => max(0, $diasRestantes),
                ];
            }

            $ultimo = $this->obtenerUltimoCertificado($socio);

            // Caso C: Requiere certificado, pero no lo presentó
            if (!$ultimo) {
                return [
                    'cumple'         => false,
                    'caso'           => 'C',
                    'decision'       => self::ESTADO_REQUERIDO,
                    'mensaje'        => 'Requiere certificado médico, pero no ha presentado documentación.',
                    'certificado'    => null,
                    'dias_restantes' => 0,
                ];
            }

            if ($ultimo->estado === AptoFisico::ESTADO_PENDIENTE_REVISION) {
                return [
                    'cumple'         => false,
                    'caso'           => 'F',
                    'decision'       => self::ESTADO_REQUERIDO,
                    'mensaje'        => 'El certificado médico presentado está pendiente de aprobación de la dueña.',
                    'certificado'    => $ultimo,
                    'dias_restantes' => 0,
                ];
            }

            if ($ultimo->estado === AptoFisico::ESTADO_RECHAZADO) {
                return [
                    'cumple'         => false,
                    'caso'           => 'G',
                    'decision'       => self::ESTADO_REQUERIDO,
                    'mensaje'        => 'El certificado médico presentado no fue aprobado.',
                    'certificado'    => $ultimo,
                    'dias_restantes' => 0,
                ];
            }

            // Una fecha de vencimiento es válida durante todo ese día.
            $vencimiento = Carbon::parse($ultimo->fecha_vencimiento)->startOfDay();

            if ($ultimo->estado === AptoFisico::ESTADO_VENCIDO || $vencimiento->lt($hoy)) {
                // Caso E: Requiere certificado y está vencido
                return [
                    'cumple'         => false,
                    'caso'           => 'E',
                    'decision'       => self::ESTADO_REQUERIDO,
                    'mensaje'        => "El certificado médico venció el {$vencimiento->format('d/m/Y')}.",
                    'certificado'    => $ultimo,
                    'dias_restantes' => 0,
                ];
            }

            if ($ultimo->fecha_emision->startOfDay()->gt($hoy)) {
                return [
                    'cumple'         => false,
                    'caso'           => 'H',
                    'decision'       => self::ESTADO_REQUERIDO,
                    'mensaje'        => 'El certificado médico todavía no está vigente.',
                    'certificado'    => $ultimo,
                    'dias_restantes' => 0,
                ];
            }

            return [
                'cumple'         => false,
                'caso'           => 'G',
                'decision'       => self::ESTADO_REQUERIDO,
                'mensaje'        => 'El certificado médico no está aprobado.',
                'certificado'    => $ultimo,
                'dias_restantes' => 0,
            ];
        }

        return [
            'cumple'         => false,
            'caso'           => 'A',
            'decision'       => self::ESTADO_PENDIENTE,
            'mensaje'        => 'Estado médico no reconocido.',
            'certificado'    => null,
            'dias_restantes' => 0,
        ];
    }

    /**
     * La Dueña define o modifica la decisión médica individual de un socio.
     * Operación transaccional y rigurosamente auditada.
     */
    public function definirDecision(
        Socio $socio,
        string $nuevaDecision,
        User $duena,
        ?string $observacion = null
    ): Socio {
        Gate::forUser($duena)->authorize('gestionarDecisionMedica', $socio);

        $validas = [self::ESTADO_PENDIENTE, self::ESTADO_REQUERIDO, self::ESTADO_NO_REQUERIDO];
        if (!in_array($nuevaDecision, $validas, true)) {
            throw new \InvalidArgumentException("La decisión médica '{$nuevaDecision}' no es válida.");
        }

        if ($observacion !== null && mb_strlen(trim($observacion)) > 500) {
            throw new \InvalidArgumentException('La observación médica no puede superar los 500 caracteres.');
        }

        $decisionAnterior = $socio->decision_medica;

        return DB::transaction(function () use (
            $socio, $nuevaDecision, $duena, $observacion, $decisionAnterior
        ) {
            $socio->update([
                'decision_medica'     => $nuevaDecision,
                'decision_medica_por' => $duena->id,
                'decision_medica_at'  => now(),
                'decision_medica_obs' => $observacion ? trim($observacion) : null,
            ]);

            // Determinar acción semántica de auditoría
            $accion = match (true) {
                $decisionAnterior === $nuevaDecision => 'decision_medica_actualizada',
                $decisionAnterior === self::ESTADO_PENDIENTE => 'decision_medica_inicial',
                $decisionAnterior === self::ESTADO_REQUERIDO && $nuevaDecision === self::ESTADO_NO_REQUERIDO => 'decision_medica_a_no_requerido',
                $decisionAnterior === self::ESTADO_NO_REQUERIDO && $nuevaDecision === self::ESTADO_REQUERIDO => 'decision_medica_a_requerido',
                default => 'decision_medica_modificada',
            };

            Auditoria::registrar(
                tipoEvento: 'medico',
                accion: $accion,
                entidad: $socio,
                datosAntes: [
                    'decision_medica' => $decisionAnterior,
                ],
                datosDespues: [
                    'decision_medica' => $nuevaDecision,
                    'operador_id' => $duena->id,
                ],
                userId: $duena->id
            );

            event(GymRealtimeUpdate::administrativeChange('medical-decision-updated'));

            return $socio->fresh();
        });
    }

    /**
     * Registra un certificado médico presentado por el socio. Solo la dueña puede
     * cargar documentación médica desde el panel administrativo.
     */
    public function registrarCertificado(
        Socio $socio,
        string $fechaEmision,
        string $fechaVencimiento,
        User $operador,
        ?UploadedFile $archivo = null
    ): AptoFisico {
        Gate::forUser($operador)->authorize('create', AptoFisico::class);

        Validator::make([
            'fecha_emision' => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'archivo' => $archivo,
        ], [
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_emision'],
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ])->validate();

        $emision = Carbon::parse($fechaEmision)->startOfDay();
        $vencimiento = Carbon::parse($fechaVencimiento)->startOfDay();

        $hoy = Carbon::now(config('app.timezone'))->startOfDay();
        $estado = $vencimiento->lt($hoy)
            ? AptoFisico::ESTADO_VENCIDO
            : AptoFisico::ESTADO_VIGENTE;
        $path = null;

        try {
            return DB::transaction(function () use (
                $socio, $emision, $vencimiento, $estado, $archivo, $operador, &$path
            ) {
                if ($archivo) {
                    $path = $archivo->store('secure/aptos_fisicos', 'local');
                    if ($path === false) {
                        throw new \RuntimeException('No se pudo guardar el archivo médico en el almacenamiento privado.');
                    }
                }

                $apto = AptoFisico::create([
                    'socio_id'          => $socio->id,
                    'archivo'           => $path,
                    'fecha_emision'     => $emision->toDateString(),
                    'fecha_vencimiento' => $vencimiento->toDateString(),
                    'estado'            => $estado,
                ]);

                Auditoria::registrar(
                    tipoEvento: 'medico',
                    accion: 'certificado_medico_registrado',
                    entidad: $apto,
                    datosAntes: null,
                    datosDespues: [
                        'socio_id' => $socio->id,
                        'apto_id' => $apto->id,
                        'fecha_emision' => $emision->toDateString(),
                        'fecha_vencimiento' => $vencimiento->toDateString(),
                        'tiene_archivo' => $path !== null,
                        'operador_id' => $operador->id,
                    ],
                    userId: $operador->id
                );

                event(GymRealtimeUpdate::administrativeChange('medical-certificate-registered'));

                return $apto;
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * Permite a recepción informar o solicitar a la Dueña revisión de situación médica.
     */
    public function informarRevisionADuena(
        Socio $socio,
        User $recepcionista
    ): Solicitud {
        Gate::forUser($recepcionista)->authorize('create', Solicitud::class);

        return DB::transaction(function () use ($socio, $recepcionista) {
            $solicitud = Solicitud::create([
                'socio_id'       => $socio->id,
                'solicitante_id' => $recepcionista->id,
                'tipo'           => Solicitud::TIPO_EXCEPCION_MEDICA,
                'descripcion'    => 'Revisión administrativa solicitada a la dueña.',
                'estado'         => Solicitud::ESTADO_PENDIENTE,
            ]);

            Auditoria::registrar(
                tipoEvento: 'medico',
                accion: 'solicitud_medica_creada',
                entidad: $solicitud,
                datosAntes: null,
                datosDespues: [
                    'socio_id'       => $socio->id,
                    'solicitud_id'   => $solicitud->id,
                    'tipo'           => $solicitud->tipo,
                    'solicitante_id' => $recepcionista->id,
                ],
                userId: $recepcionista->id
            );

            event(GymRealtimeUpdate::request($solicitud, 'created'));

            return $solicitud;
        });
    }

    /**
     * Retorna el último certificado médico registrado para el socio.
     */
    public function obtenerUltimoCertificado(Socio $socio): ?AptoFisico
    {
        return $socio->aptosFisicos()
            ->latest('fecha_emision')
            ->latest('id')
            ->first();
    }

    /**
     * Obtiene el historial completo de certificados médicos del socio.
     */
    public function obtenerHistorialCertificados(Socio $socio)
    {
        return $socio->aptosFisicos()
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get();
    }
}
