<?php

namespace Tests\Unit;

use App\Events\GymRealtimeUpdate;
use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Solicitud;
use App\Models\Socio;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GymRealtimeUpdateTest extends TestCase
{
    public function test_authorized_pin_entry_event_contains_only_screen_fields(): void
    {
        $event = GymRealtimeUpdate::access([
            'autorizado' => true,
            'codigo' => 'acceso_autorizado',
            'asistencia' => (new Asistencia)->forceFill([
                'id' => 17,
                'fecha' => '2026-11-09',
                'hora_ingreso' => '18:30:00',
                'metodo_acceso' => 'pin_terminal',
            ]),
            'actividad' => ['grupo' => 'Funcional tarde', 'disciplina' => 'Funcional'],
        ], 'pin', new Socio(['nombre' => 'Ana', 'apellido' => 'Paz']));

        $payload = $event->broadcastWith();
        $this->assertSame('access', $payload['category']);
        $this->assertSame('authorized', $payload['result']);
        $this->assertSame('pin_terminal', $payload['source']);
        $this->assertSame('Ana Paz', $payload['member']);
        $this->assertSame(17, $payload['movement_id']);
        $this->assertSame(['category', 'movement_id', 'operation', 'result', 'occurred_at', 'member', 'group', 'discipline', 'source', 'message'], array_keys($payload));
    }

    public function test_denied_access_event_is_generic_and_does_not_expose_medical_or_credential_data(): void
    {
        $event = GymRealtimeUpdate::access([
            'autorizado' => false,
            'codigo' => 'certificado_vencido',
            'mensaje' => 'Sensitive medical detail',
            'actividad' => null,
        ], 'qr', new Socio(['nombre' => 'Ana', 'apellido' => 'Paz', 'pin' => '123456', 'qr_token' => str_repeat('x', 64)]));

        $payload = $event->broadcastWith();
        $this->assertSame('denied', $payload['result']);
        $this->assertSame('Acceso denegado.', $payload['message']);
        $this->assertSame('qr_movil', $payload['source']);
        $this->assertStringNotContainsString('certificado', json_encode($payload));
        $this->assertStringNotContainsString('123456', json_encode($payload));
        $this->assertStringNotContainsString(str_repeat('x', 64), json_encode($payload));
    }

    public function test_exit_and_reception_events_preserve_operation_and_origin(): void
    {
        $event = GymRealtimeUpdate::access([
            'autorizado' => true,
            'codigo' => 'salida_registrada',
            'asistencia' => new Asistencia([
                'id' => 8,
                'fecha' => '2026-11-09',
                'hora_ingreso' => '18:00:00',
            ]),
        ], 'salida', new Socio(['nombre' => 'Ana', 'apellido' => 'Paz']), 'pin_recepcion');

        $this->assertSame('salida', $event->broadcastWith()['operation']);
        $this->assertSame('pin_recepcion', $event->broadcastWith()['source']);
        $this->assertSame(['private-gym.admin', 'private-gym.reception'], array_map(
            fn (PrivateChannel $channel) => $channel->name,
            $event->broadcastOn()
        ));
    }

    public function test_class_attendance_event_includes_the_specific_group_and_status(): void
    {
        $attendance = (new AsistenciaClase)->forceFill([
            'id' => 3,
            'asistencia_id' => 17,
            'estado' => 'tarde',
            'origen_registro' => 'recepcion',
        ]);
        $attendance->created_at = now();

        $payload = GymRealtimeUpdate::classAttendance(
            $attendance,
            new Socio(['nombre' => 'Ana', 'apellido' => 'Paz']),
            ['grupo' => 'Kickboxing noche', 'disciplina' => 'Kickboxing']
        )->broadcastWith();

        $this->assertSame(3, $payload['attendance_id']);
        $this->assertSame('Kickboxing noche', $payload['group']);
        $this->assertSame('Kickboxing', $payload['discipline']);
        $this->assertSame('tarde', $payload['status']);
        $this->assertSame('recepcion', $payload['source']);
    }

    public function test_request_events_are_private_to_the_owner_and_requesting_receptionist(): void
    {
        $request = new Solicitud([
            'id' => 9,
            'solicitante_id' => 44,
            'tipo' => 'excepcion_medica',
            'estado' => 'aprobada',
            'descripcion' => 'Private medical details',
        ]);
        $request->setRelation('socio', new Socio(['nombre' => 'Ana', 'apellido' => 'Paz']));
        $request->fecha_decision = now();

        $event = GymRealtimeUpdate::request($request, 'resolved');
        $payload = $event->broadcastWith();

        $this->assertSame(['private-gym.admin', 'private-gym.reception.44'], array_map(
            fn (PrivateChannel $channel) => $channel->name,
            $event->broadcastOn()
        ));
        $this->assertSame('resolved', $payload['action']);
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertStringNotContainsString('Private medical details', json_encode($payload));
    }

    public function test_enrollment_event_is_allowlisted_and_channel_is_private(): void
    {
        $member = new Socio(['nombre' => 'Ana', 'apellido' => 'Paz', 'pin' => '123456']);
        $discipline = new Disciplina(['nombre' => 'Funcional']);
        $group = new Grupo(['nombre' => 'Tarde']);
        $group->setRelation('disciplina', $discipline);
        $enrollment = new Inscripcion([
            'id' => 5,
            'estado' => 'aprobada',
            'pin' => '123456',
        ]);
        $enrollment->setRelation('socio', $member);
        $enrollment->setRelation('grupo', $group);
        $enrollment->updated_at = now();

        $event = GymRealtimeUpdate::enrollment($enrollment, 'approved');
        $payload = $event->broadcastWith();

        $this->assertSame('approved', $payload['action']);
        $this->assertSame('Tarde', $payload['group']);
        $this->assertSame(['private-gym.admin', 'private-gym.reception'], array_map(
            fn (PrivateChannel $channel) => $channel->name,
            $event->broadcastOn()
        ));
        $this->assertArrayNotHasKey('pin', $payload);
    }

    public function test_administrative_indicator_updates_are_owner_only(): void
    {
        $event = GymRealtimeUpdate::administrativeChange('payment-recorded');

        $this->assertSame(['private-gym.admin'], array_map(
            fn (PrivateChannel $channel) => $channel->name,
            $event->broadcastOn()
        ));
        $this->assertSame('administrative-change', $event->broadcastWith()['category']);
    }

    public function test_operational_indicator_updates_reach_authorized_staff_without_operational_details(): void
    {
        $event = GymRealtimeUpdate::operationalChange('payment-recorded');

        $this->assertSame(['private-gym.admin', 'private-gym.reception'], array_map(
            fn (PrivateChannel $channel) => $channel->name,
            $event->broadcastOn()
        ));
        $this->assertSame([
            'category' => 'administrative-change',
            'action' => 'payment-recorded',
            'occurred_at' => $event->broadcastWith()['occurred_at'],
        ], $event->broadcastWith());
    }

    public function test_broadcast_uses_the_realtime_database_queue_and_after_commit(): void
    {
        $event = GymRealtimeUpdate::administrativeChange('member-created');
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        Queue::fake();
        event($event);
        Queue::assertPushedOn('realtime', BroadcastEvent::class, fn (BroadcastEvent $job) => (
            $job->event->connection === 'database' && $job->afterCommit === true
        ));
    }

    public function test_broadcast_is_not_queued_when_transaction_rolls_back(): void
    {
        Queue::fake();
        DB::beginTransaction();
        event(GymRealtimeUpdate::administrativeChange('member-created'));
        Queue::assertNothingPushed();
        DB::rollBack();
        Queue::assertNothingPushed();
    }
}
