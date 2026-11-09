<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Inscripcion;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CredencialService
{
    public const ESTADO_SIN_EMITIR = 'sin_emitir';
    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_REVOCADO = 'revocado';

    public const ESTADO_NO_EMITIDA = self::ESTADO_SIN_EMITIR;
    public const ESTADO_EMITIDA = self::ESTADO_ACTIVO;
    public const ESTADO_REVOCADA = self::ESTADO_REVOCADO;

    public function __construct(
        private readonly PagoService $pagoService,
        private readonly CertificadoMedicoService $certificadoMedicoService,
    ) {
    }

    /**
     * Evalúa los requisitos para emitir por primera vez, sin modificar al socio.
     *
     * @return array{habilitado: bool, motivo: string|null}
     */
    public function evaluarRequisitosEmision(Socio $socio): array
    {
        if ($socio->estado !== 'activo') {
            return ['habilitado' => false, 'motivo' => 'El socio no está activo.'];
        }

        $inscripciones = $socio->inscripciones()
            ->where('estado', Inscripcion::ESTADO_APROBADA)
            ->get();

        if ($inscripciones->isEmpty()) {
            return ['habilitado' => false, 'motivo' => 'El socio no tiene inscripción aprobada.'];
        }

        $tieneCoberturaVigente = $inscripciones->contains(
            fn (Inscripcion $inscripcion) => $this->pagoService
                ->consultarCoberturaInscripcion($inscripcion)['vigente']
        );

        if (!$tieneCoberturaVigente) {
            return ['habilitado' => false, 'motivo' => 'El socio no tiene cobertura económica vigente.'];
        }

        $evaluacionMedica = $this->certificadoMedicoService->evaluarRequisitoMedico($socio);
        if (!in_array($evaluacionMedica['decision'], [
            CertificadoMedicoService::ESTADO_REQUERIDO,
            CertificadoMedicoService::ESTADO_NO_REQUERIDO,
        ], true)) {
            return ['habilitado' => false, 'motivo' => 'La decisión médica del socio está pendiente.'];
        }

        if (!$evaluacionMedica['cumple']) {
            return ['habilitado' => false, 'motivo' => 'El socio requiere un certificado médico vigente.'];
        }

        return ['habilitado' => true, 'motivo' => null];
    }

    /**
     * Emite credenciales por primera vez. La decisión de emisión es exclusiva de la dueña.
     *
     * @return array{pin: string, qr_token: string}
     */
    public function emitirPrimeraVez(Socio $socio, User $duena): array
    {
        $this->autorizarDueña($duena, $socio);

        return $this->reintentarColisionDeCredenciales(fn () => DB::transaction(function () use ($socio, $duena) {
            $socio = Socio::query()->lockForUpdate()->findOrFail($socio->getKey());

            if ($socio->estado_credencial !== self::ESTADO_SIN_EMITIR) {
                throw new InvalidArgumentException('El socio ya tiene credenciales emitidas o no está en estado sin emitir.');
            }

            $evaluacion = $this->evaluarRequisitosEmision($socio);
            if (!$evaluacion['habilitado']) {
                throw new InvalidArgumentException($evaluacion['motivo']);
            }

            $pin = $this->generarPin($socio);
            $qrToken = $this->generarQrTokenUnico();
            $fechaEmision = now();

            $socio->update([
                'pin' => $pin,
                'qr_token' => $qrToken,
                'estado_credencial' => self::ESTADO_ACTIVO,
                'credenciales_emitidas_at' => $fechaEmision,
                'credenciales_revocadas_at' => null,
                'motivo_revocacion' => null,
                'pin_revocado_hash' => null,
                'qr_token_revocado_hash' => null,
            ]);

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'credencial_emitida',
                entidad: $socio,
                datosAntes: ['estado_credencial' => self::ESTADO_SIN_EMITIR],
                datosDespues: [
                    'estado_credencial' => self::ESTADO_ACTIVO,
                    'credenciales_emitidas_at' => $fechaEmision,
                ],
                userId: $duena->id,
            );

            return ['pin' => $pin, 'qr_token' => $qrToken];
        }));
    }

    /**
     * Repone una credencial existente por un motivo excepcional documentado.
     *
     * @return array{pin: string, qr_token: string}
     */
    public function reponerCredenciales(Socio $socio, User $duena, string $motivo): array
    {
        $this->autorizarDueña($duena, $socio);
        $motivo = $this->validarMotivo($motivo);

        return $this->reintentarColisionDeCredenciales(fn () => DB::transaction(function () use ($socio, $duena, $motivo) {
            $socio = Socio::query()->lockForUpdate()->findOrFail($socio->getKey());

            if (!in_array($socio->estado_credencial, [self::ESTADO_ACTIVO, self::ESTADO_REVOCADO], true)) {
                throw new InvalidArgumentException('Solo se pueden reponer credenciales previamente emitidas.');
            }

            $estadoAnterior = $socio->estado_credencial;
            $pin = $this->generarPin($socio);
            $qrToken = $this->generarQrTokenUnico();
            $fechaEmision = now();

            $socio->update([
                'pin' => $pin,
                'qr_token' => $qrToken,
                'estado_credencial' => self::ESTADO_ACTIVO,
                'credenciales_emitidas_at' => $fechaEmision,
                'credenciales_revocadas_at' => null,
                'motivo_revocacion' => null,
                'pin_revocado_hash' => null,
                'qr_token_revocado_hash' => null,
            ]);

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'credencial_repuesta',
                entidad: $socio,
                datosAntes: ['estado_credencial' => $estadoAnterior],
                datosDespues: [
                    'estado_credencial' => self::ESTADO_ACTIVO,
                    'motivo' => $this->sanitizarMotivo($motivo, $socio),
                ],
                userId: $duena->id,
            );

            return ['pin' => $pin, 'qr_token' => $qrToken];
        }));
    }

    /**
     * Revoca credenciales únicamente por decisión autorizada y documentada de la dueña.
     */
    public function revocarCredenciales(Socio $socio, User $duena, string $motivo): void
    {
        $this->autorizarDueña($duena, $socio);
        $motivo = $this->validarMotivo($motivo);

        DB::transaction(function () use ($socio, $duena, $motivo) {
            $socio = Socio::query()->lockForUpdate()->findOrFail($socio->getKey());

            if ($socio->estado_credencial !== self::ESTADO_ACTIVO) {
                throw new InvalidArgumentException('Solo se pueden revocar credenciales activas.');
            }

            $fechaRevocacion = now();
            $pinRevocadoHash = $socio->pin === null
                ? null
                : hash_hmac('sha256', $socio->pin, (string) config('app.key'));
            $qrTokenRevocadoHash = $socio->qr_token === null
                ? null
                : hash_hmac('sha256', $socio->qr_token, (string) config('app.key'));

            $socio->update([
                'estado_credencial' => self::ESTADO_REVOCADO,
                'pin' => null,
                'qr_token' => null,
                'pin_revocado_hash' => $pinRevocadoHash,
                'qr_token_revocado_hash' => $qrTokenRevocadoHash,
                'credenciales_revocadas_at' => $fechaRevocacion,
                'motivo_revocacion' => $motivo,
            ]);

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'credencial_revocada',
                entidad: $socio,
                datosAntes: ['estado_credencial' => self::ESTADO_ACTIVO],
                datosDespues: [
                    'estado_credencial' => self::ESTADO_REVOCADO,
                    'credenciales_revocadas_at' => $fechaRevocacion,
                    'motivo_revocacion' => $this->sanitizarMotivo($motivo, $socio),
                ],
                userId: $duena->id,
            );
        });
    }

    /**
     * Genera un PIN de seis dígitos que no esté asignado a otra credencial.
     */
    public function generarPin(Socio $socio): string
    {
        for ($intentos = 0; $intentos < 100; $intentos++) {
            $pin = (string) random_int(100000, 999999);

            if ($pin !== $socio->pin
                && !$this->esSecuencial($pin)
                && !Socio::query()->where('pin', $pin)->exists()) {
                return $pin;
            }
        }

        throw new \RuntimeException('No se pudo generar un PIN único después de varios intentos.');
    }

    /**
     * Genera un token QR independiente, con 256 bits de entropía.
     */
    public function generarQrToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function generarQrTokenUnico(): string
    {
        do {
            $token = $this->generarQrToken();
        } while (Socio::query()->where('qr_token', $token)->exists());

        return $token;
    }

    private function autorizarDueña(User $duena, Socio $socio): void
    {
        Gate::forUser($duena)->authorize('emitirCredenciales', $socio);
    }

    private function validarMotivo(string $motivo): string
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('Se requiere un motivo para modificar credenciales.');
        }

        if (mb_strlen($motivo) > 1000) {
            throw new InvalidArgumentException('El motivo no puede superar los 1000 caracteres.');
        }

        return $motivo;
    }

    private function sanitizarMotivo(string $motivo, Socio $socio): string
    {
        foreach ([$socio->pin, $socio->qr_token] as $secreto) {
            if (is_string($secreto) && $secreto !== '') {
                $motivo = str_replace($secreto, '[dato oculto]', $motivo);
            }
        }

        $motivo = preg_replace('/(?<!\d)\d{6}(?!\d)/', '[dato oculto]', $motivo);
        $motivo = preg_replace('/\b[a-f0-9]{64}\b/i', '[dato oculto]', $motivo);

        return $motivo;
    }

    private function reintentarColisionDeCredenciales(callable $operacion): mixed
    {
        for ($intentos = 0; ; $intentos++) {
            try {
                return $operacion();
            } catch (QueryException $exception) {
                $mensaje = strtolower($exception->getMessage());
                $esColisionDeCredencial = str_contains($mensaje, 'socios_pin_unique')
                    || str_contains($mensaje, 'socios_qr_token_unique')
                    || str_contains($mensaje, 'socios.pin')
                    || str_contains($mensaje, 'socios.qr_token');

                if (!$esColisionDeCredencial || $intentos >= 4) {
                    throw $exception;
                }
            }
        }
    }

    private function esSecuencial(string $pin): bool
    {
        $digits = array_map('intval', str_split($pin));
        $ascending = true;
        $descending = true;

        for ($index = 1; $index < count($digits); $index++) {
            if ($digits[$index] !== $digits[$index - 1] + 1) {
                $ascending = false;
            }

            if ($digits[$index] !== $digits[$index - 1] - 1) {
                $descending = false;
            }
        }

        return $ascending || $descending;
    }
}
