<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class Auditoria extends Model
{
    use HasFactory;

    protected $table = 'auditorias';

    // No utiliza updated_at, solo created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'tipo_evento',
        'accion',
        'auditable_type',
        'auditable_id',
        'ip',
        'user_agent',
        'datos_antes',
        'datos_despues',
        'created_at',
    ];

    protected $casts = [
        'datos_antes' => 'array',
        'datos_despues' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los registros de auditoría son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los registros de auditoría no se pueden eliminar.'));
    }

    /**
     * Campos que NUNCA deben quedar registrados en el log de auditoría por seguridad y privacidad.
     */
    protected static array $camposOcultos = [
        'password',
        'remember_token',
        'pin',
        'qr_token',
        'token',
        'diagnostico',
        'observaciones_medicas',
        'decision_medica_obs',
        'archivo',
        'documento',
    ];

    /**
     * Relación de Muchos a Uno: Operador responsable (null para eventos del sistema o terminal).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación polimórfica hacia la entidad auditada (Socio, Pago, Inscripción, etc.).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Método estático para registrar eventos de auditoría sanitizando información sensible.
     */
    public static function registrar(
        string $tipoEvento,
        string $accion,
        ?Model $entidad = null,
        ?array $datosAntes = null,
        ?array $datosDespues = null,
        ?int $userId = null
    ): self {
        return self::create([
            'user_id' => $userId ?? (auth()->check() ? auth()->id() : null),
            'tipo_evento' => $tipoEvento,
            'accion' => $accion,
            'auditable_type' => $entidad ? get_class($entidad) : null,
            'auditable_id' => $entidad?->getKey(),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'datos_antes' => self::sanitizar($datosAntes),
            'datos_despues' => self::sanitizar($datosDespues),
            'created_at' => now(),
        ]);
    }

    private static function sanitizar(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }

        foreach ($datos as $campo => $valor) {
            $clave = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $campo));
            $sensible = in_array($campo, self::$camposOcultos, true)
                || in_array($clave, ['password', 'remembertoken', 'pin', 'qrtoken', 'token', 'diagnostico', 'observacionesmedicas', 'decisionmedicaobs', 'archivo', 'documento'], true)
                || str_contains($clave, 'secret')
                || str_contains($clave, 'token')
                || str_contains($clave, 'pin')
                || str_contains($clave, 'diagnostico')
                || str_contains($clave, 'observacionesmedicas')
                || str_contains($clave, 'archivo')
                || str_contains($clave, 'documento');

            if ($sensible) {
                $datos[$campo] = '***OCULTO_POR_SEGURIDAD***';
            } elseif (is_array($valor)) {
                $datos[$campo] = self::sanitizar($valor);
            }
        }

        return $datos;
    }
}
