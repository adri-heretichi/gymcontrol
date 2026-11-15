<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\CertificadoMedicoService;
use App\Services\InscripcionService;
use App\Services\SolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PendingRequestIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $reception;

    private Socio $member;

    private Inscripcion $enrollment;

    private Grupo $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Event::fake([GymStateChanged::class]);
        $this->owner = User::factory()->create(['rol' => 'admin']);
        $this->reception = User::factory()->create(['rol' => 'recepcionista']);
        $this->member = Socio::create(['nombre' => 'Ficticio', 'apellido' => 'Solicitud repetida', 'dni' => '91111111', 'sexo' => 'F', 'token' => 'request-idempotency', 'fecha_alta' => today(), 'estado' => 'activo', 'decision_medica' => 'pendiente']);
        $d = Disciplina::create(['nombre' => 'Funcional', 'precio' => 35000, 'estado' => 'activo']);
        $groups = [];
        foreach (['lunes', 'martes'] as $day) {
            $g = Grupo::create(['disciplina_id' => $d->id, 'nombre' => 'Prueba '.$day, 'cupo_maximo' => 15, 'estado' => 'activo']);
            GrupoHorario::create(['grupo_id' => $g->id, 'dia_semana' => $day, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00']);
            $groups[] = $g;
        }$this->destination = $groups[1];
        $this->enrollment = Inscripcion::create(['socio_id' => $this->member->id, 'grupo_id' => $groups[0]->id, 'fecha_inscripcion' => today(), 'estado' => 'aprobada', 'aprobado_at' => now()]);
    }

    public function test_repeated_medical_request_reuses_pending_id_and_audit(): void
    {
        $service = app(CertificadoMedicoService::class);
        $first = $service->informarRevisionADuena($this->member, $this->reception);
        $second = $service->informarRevisionADuena($this->member, $this->reception);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Solicitud::count());
        $this->assertSame(1, Auditoria::where('accion', 'solicitud_medica_creada')->count());
    }

    public function test_resolved_request_does_not_block_a_new_review(): void
    {
        $service = app(CertificadoMedicoService::class);
        $first = $service->informarRevisionADuena($this->member, $this->reception);
        app(SolicitudService::class)->resolver($first, $this->owner, 'rechazada', 'Solicitud ficticia revisada.');
        $second = $service->informarRevisionADuena($this->member, $this->reception);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Solicitud::count());
        $this->assertSame('rechazada', $first->fresh()->estado);
    }

    public function test_pending_review_is_scoped_to_its_requester(): void
    {
        $other = User::factory()->create(['rol' => 'recepcionista']);
        $service = app(CertificadoMedicoService::class);
        $first = $service->informarRevisionADuena($this->member, $this->reception);
        $second = $service->informarRevisionADuena($this->member, $other);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($other->id, $second->solicitante_id);
    }

    public function test_repeated_drop_request_reuses_pending_id_without_applying_drop(): void
    {
        $service = app(InscripcionService::class);
        $first = $service->registrarBaja($this->enrollment, $this->reception, 'Baja ficticia.');
        $second = $service->registrarBaja($this->enrollment, $this->reception, 'Baja ficticia.');
        $this->assertSame($first['solicitud']->id, $second['solicitud']->id);
        $this->assertSame(1, Solicitud::count());
        $this->assertSame('aprobada', $this->enrollment->fresh()->estado);
        $this->assertSame(1, Auditoria::where('accion', 'solicitud_baja_inscripcion')->count());
    }

    public function test_repeated_group_change_reuses_pending_id_without_moving_member(): void
    {
        $service = app(InscripcionService::class);
        $first = $service->solicitarCambioGrupo($this->enrollment, $this->destination, $this->reception, 'Cambio ficticio.');
        $second = $service->solicitarCambioGrupo($this->enrollment, $this->destination, $this->reception, 'Cambio ficticio.');
        $this->assertSame($first['solicitud']->id, $second['solicitud']->id);
        $this->assertSame(1, Solicitud::count());
        $this->assertNotSame($this->destination->id, $this->enrollment->fresh()->grupo_id);
        $this->assertSame(1,Auditoria::where('accion','solicitud_cambio_grupo')->count());
    }
}
