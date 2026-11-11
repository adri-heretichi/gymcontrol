<?php

namespace App\Services;

use App\Models\Inscripcion;
use App\Models\Socio;
use Carbon\Carbon;

class HabilitacionSocioService
{
    public function __construct(
        private readonly PagoService $pagoService,
        private readonly CertificadoMedicoService $certificadoMedicoService
    ) {
    }

    /**
     * Evalúa la habilitación administrativa por inscripción, no como una vigencia global del socio.
     *
     * @return array{habilitado: bool, etiqueta: string, detalle: string, tipo: string, por_inscripcion: array}
     */
    public function evaluar(Socio $socio, ?string $fecha = null): array
    {
        $fecha = $fecha
            ? Carbon::parse($fecha)->toDateString()
            : Carbon::now(config('app.timezone'))->toDateString();
        $inscripciones = $socio->inscripciones()
            ->with(['grupo.disciplina'])
            ->get();

        if ($socio->estado !== 'activo') {
            return $this->resultado(
                false,
                'Administrativamente inactivo',
                'No puede ingresar mientras se mantenga inactivo.',
                'bloqueado'
            );
        }

        if ($inscripciones->isEmpty()) {
            return $this->resultado(
                false,
                'Registro preliminar',
                'Sin inscripción a disciplina y grupo; todavía no puede ingresar.',
                'pendiente'
            );
        }

        $evaluacionMedica = $this->certificadoMedicoService->evaluarRequisitoMedico($socio, $fecha);
        $porInscripcion = $inscripciones->map(function (Inscripcion $inscripcion) use ($fecha, $evaluacionMedica) {
            $disciplina = $inscripcion->grupo?->disciplina?->nombre ?? 'Sin disciplina';
            $grupo = $inscripcion->grupo?->nombre ?? 'Sin grupo';
            $requisitosPendientes = [];
            $cobertura = null;

            if ($inscripcion->estado === Inscripcion::ESTADO_PENDIENTE) {
                $requisitosPendientes[] = 'Inscripción pendiente de aprobación.';
            } elseif ($inscripcion->estado !== Inscripcion::ESTADO_APROBADA) {
                $requisitosPendientes[] = 'La inscripción no está aprobada.';
            } else {
                $cobertura = $this->pagoService->consultarCoberturaInscripcion($inscripcion, $fecha);
                if (!$cobertura['vigente']) {
                    $requisitosPendientes[] = $cobertura['estado'] === 'vencida'
                        ? 'La cobertura de esta disciplina está vencida.'
                        : 'Falta una cobertura vigente para esta disciplina.';
                }

                if (!$evaluacionMedica['cumple']) {
                    $requisitosPendientes[] = $evaluacionMedica['mensaje'];
                }
            }

            $habilitado = $inscripcion->estado === Inscripcion::ESTADO_APROBADA
                && $cobertura !== null
                && $cobertura['vigente']
                && $evaluacionMedica['cumple'];

            return [
                'inscripcion_id' => $inscripcion->id,
                'disciplina' => $disciplina,
                'grupo' => $grupo,
                'estado_inscripcion' => $inscripcion->estado,
                'habilitado' => $habilitado,
                'detalle' => $requisitosPendientes === []
                    ? 'Inscripción, cobertura y requisito médico cumplidos.'
                    : implode(' ', $requisitosPendientes),
            ];
        })->values();

        $habilitadas = $porInscripcion->where('habilitado', true);
        $aprobadas = $porInscripcion->where('estado_inscripcion', Inscripcion::ESTADO_APROBADA);

        if ($habilitadas->isNotEmpty()) {
            $disciplinas = $habilitadas->pluck('disciplina')->unique()->implode(', ');
            $pendientes = $aprobadas->where('habilitado', false);
            $detalle = "Habilitado para: {$disciplinas}.";

            if ($pendientes->isNotEmpty()) {
                $detalle .= ' Hay otras inscripciones con requisitos pendientes.';
            }

            return $this->resultado(
                true,
                $habilitadas->count() === 1
                    ? 'Habilitado para 1 disciplina'
                    : 'Habilitado para ' . $habilitadas->count() . ' disciplinas',
                $detalle,
                'completo',
                $porInscripcion->all()
            );
        }

        if ($aprobadas->isEmpty()) {
            $pendiente = $porInscripcion->contains(
                'estado_inscripcion',
                Inscripcion::ESTADO_PENDIENTE
            );

            return $this->resultado(
                false,
                $pendiente ? 'Inscripción pendiente' : 'Sin inscripción aprobada',
                $pendiente
                    ? 'La inscripción requiere aprobación de la dueña.'
                    : 'No puede ingresar sin una inscripción aprobada.',
                'pendiente',
                $porInscripcion->all()
            );
        }

        $detalle = $aprobadas
            ->map(fn (array $inscripcion) => "{$inscripcion['disciplina']}: {$inscripcion['detalle']}")
            ->implode(' ');
        $soloPagoPendiente = $evaluacionMedica['cumple']
            && $aprobadas->every(fn (array $inscripcion) => str_contains($inscripcion['detalle'], 'cobertura'));
        $etiquetaPendiente = match (true) {
            $evaluacionMedica['decision'] === CertificadoMedicoService::ESTADO_PENDIENTE
                => 'Pendiente de decisión médica',
            $evaluacionMedica['decision'] === CertificadoMedicoService::ESTADO_REQUERIDO
                && !$evaluacionMedica['cumple'] => 'Documentación médica pendiente',
            $soloPagoPendiente => 'Pendiente de pago/cobertura',
            default => 'Requisitos de habilitación pendientes',
        };

        return $this->resultado(
            false,
            $etiquetaPendiente,
            $detalle,
            'pendiente',
            $porInscripcion->all()
        );
    }

    private function resultado(
        bool $habilitado,
        string $etiqueta,
        string $detalle,
        string $tipo,
        array $porInscripcion = []
    ): array {
        return [
            'habilitado' => $habilitado,
            'etiqueta' => $etiqueta,
            'detalle' => $detalle,
            'tipo' => $tipo,
            'por_inscripcion' => $porInscripcion,
        ];
    }
}
