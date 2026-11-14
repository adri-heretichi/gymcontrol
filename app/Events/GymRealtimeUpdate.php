<?php

namespace App\Events;

use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\Inscripcion;
use App\Models\Solicitud;
use App\Models\Socio;
use App\Services\ControlAccesoService;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class GymRealtimeUpdate implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use InteractsWithSockets;

    public string $connection = 'database';
    public string $queue = 'realtime';
    public bool $afterCommit = true;

    private function __construct(
        private readonly string $category,
        private readonly array $payload,
        private readonly string $audience = 'staff',
        private readonly ?int $requesterId = null
    ) {
    }

    public static function access(array $result, string $method, ?Socio $socio, ?string $origin = null): self
    {
        /** @var Asistencia|null $movement */
        $movement = $result['asistencia'] ?? null;
        $activity = $result['actividad'] ?? null;
        $operation = ($result['codigo'] ?? null) === 'salida_registrada' || $method === 'salida'
            ? 'salida'
            : 'ingreso';
        $source = $origin
            ?? $movement?->metodo_salida
            ?? $movement?->metodo_acceso
            ?? ($method === 'qr' ? 'qr_movil' : ($method === 'salida' ? 'operador' : 'pin_terminal'));

        return new self('access', [
            'movement_id' => $movement?->id,
            'operation' => $operation,
            'result' => ($result['autorizado'] ?? false) ? 'authorized' : 'denied',
            'occurred_at' => self::movementTime($movement),
            'member' => self::memberName($socio),
            'group' => is_array($activity) ? ($activity['grupo'] ?? null) : null,
            'discipline' => is_array($activity) ? ($activity['disciplina'] ?? null) : null,
            'source' => in_array($source, ['pin_terminal', 'pin_recepcion', 'qr_movil', 'manual_operador'], true)
                ? $source
                : 'operador',
            'message' => ($result['autorizado'] ?? false)
                ? ($operation === 'salida' ? 'Salida registrada.' : 'Acceso autorizado.')
                : ControlAccesoService::mensajeOperativo((string) ($result['codigo'] ?? '')),
        ]);
    }

    public static function classAttendance(AsistenciaClase $attendance, Socio $member, array $activity): self
    {
        return new self('class-attendance', [
            'attendance_id' => $attendance->id,
            'movement_id' => $attendance->asistencia_id,
            'occurred_at' => $attendance->created_at?->toIso8601String(),
            'member' => self::memberName($member),
            'group' => $activity['grupo'] ?? null,
            'discipline' => $activity['disciplina'] ?? null,
            'status' => $attendance->estado,
            'source' => in_array($attendance->origen_registro, ['recepcion', 'administracion', 'operador'], true)
                ? $attendance->origen_registro
                : 'operador',
        ]);
    }

    public static function request(Solicitud $request, string $action): self
    {
        return new self('request', [
            'request_id' => $request->id,
            'action' => $action,
            'request_type' => $request->tipo,
            'status' => $request->estado,
            'occurred_at' => ($action === 'resolved' ? $request->fecha_decision : $request->created_at)?->toIso8601String(),
            'member' => self::memberName($request->socio),
        ], 'requester', (int) $request->solicitante_id);
    }

    public static function enrollment(Inscripcion $enrollment, string $action): self
    {
        $enrollment->loadMissing(['socio:id,nombre,apellido', 'grupo:id,nombre,disciplina_id', 'grupo.disciplina:id,nombre']);

        return new self('enrollment', [
            'enrollment_id' => $enrollment->id,
            'action' => $action,
            'status' => $enrollment->estado,
            'occurred_at' => $enrollment->updated_at?->toIso8601String(),
            'member' => self::memberName($enrollment->socio),
            'group' => $enrollment->grupo?->nombre,
            'discipline' => $enrollment->grupo?->disciplina?->nombre,
        ]);
    }

    public static function administrativeChange(string $action): self
    {
        return new self('administrative-change', [
            'action' => $action,
            'occurred_at' => now()->toIso8601String(),
        ], 'owner');
    }

    public static function operationalChange(string $action): self
    {
        return new self('administrative-change', [
            'action' => $action,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('gym.admin')];

        if ($this->audience === 'staff') {
            $channels[] = new PrivateChannel('gym.reception');
        } elseif ($this->audience === 'requester' && $this->requesterId) {
            $channels[] = new PrivateChannel('gym.reception.' . $this->requesterId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'gym.realtime-update';
    }

    public function broadcastWith(): array
    {
        return ['category' => $this->category] + $this->payload;
    }

    private static function memberName(?Socio $member): ?string
    {
        return $member ? trim($member->nombre . ' ' . $member->apellido) : null;
    }

    private static function movementTime(?Asistencia $movement): ?string
    {
        if (!$movement || !$movement->fecha || !$movement->hora_ingreso) {
            return now()->toIso8601String();
        }

        return Carbon::parse(
            $movement->fecha->toDateString() . ' ' . $movement->hora_ingreso,
            config('app.timezone')
        )->toIso8601String();
    }
}
