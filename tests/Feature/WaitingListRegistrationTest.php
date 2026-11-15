<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Livewire\Inscripciones\CrearInscripcion;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\ListaEspera;
use App\Models\Socio;
use App\Models\User;
use App\Services\InscripcionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class WaitingListRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $reception;

    private Grupo $group;

    private Socio $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Event::fake([GymStateChanged::class]);
        $this->owner = User::factory()->create(['rol' => 'admin']);
        $this->reception = User::factory()->create(['rol' => 'recepcionista']);
        $d = Disciplina::create(['nombre' => 'Funcional', 'precio' => 35000, 'estado' => 'activo']);
        $this->group = Grupo::create(['nombre' => 'Espera configurable', 'disciplina_id' => $d->id, 'cupo_maximo' => 1, 'estado' => 'activo']);
        GrupoHorario::create(['grupo_id' => $this->group->id, 'dia_semana' => 'lunes', 'hora_inicio' => '08:00', 'hora_fin' => '09:00']);
        $occupied = $this->member(1);
        Inscripcion::create(['grupo_id' => $this->group->id, 'socio_id' => $occupied->id, 'fecha_inscripcion' => today(), 'estado' => 'aprobada']);
        $this->member = $this->member(2);
    }

    private function member(int $n): Socio
    {
        return Socio::create(['nombre' => 'Nuevo', 'apellido' => 'Espera '.$n, 'dni' => (string) (91444000 + $n), 'sexo' => 'F', 'token' => 'wait-'.$n, 'fecha_alta' => today(), 'estado' => 'activo', 'decision_medica' => 'pendiente']);
    }

    public function test_reception_can_explicitly_register_new_member_in_full_group_without_enrollment(): void
    {
        Livewire::actingAs($this->reception)->test(CrearInscripcion::class)->set('socio_id', $this->member->id)->set('disciplina_id', $this->group->disciplina_id)->set('grupo_id', $this->group->id)->assertSee('Registrar en lista de espera')->call('registrarEspera')->assertHasNoErrors()->assertRedirect(route('inscripciones.index', ['tab' => 'espera']));
        $this->assertSame(1, Inscripcion::where('estado', 'aprobada')->count());
        $this->assertSame(0, Inscripcion::where('socio_id', $this->member->id)->count());
        $wait = ListaEspera::sole();
        $this->assertSame($this->member->id, $wait->socio_id);
        $this->assertSame(1, $wait->posicion);
        $this->assertSame('esperando', $wait->estado);
        $this->assertTrue(Auditoria::where('accion', 'registro_lista_espera')->exists());
    }

    public function test_duplicate_waiting_action_reuses_entry_without_duplicate_audit(): void
    {
        $s = app(InscripcionService::class);
        $a = $s->registrarEnListaEspera($this->member, $this->group, $this->reception);
        $b = $s->registrarEnListaEspera($this->member, $this->group, $this->owner);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, ListaEspera::count());
        $this->assertSame(1, Auditoria::where('accion', 'registro_lista_espera')->count());
    }

    public function test_free_slot_does_not_auto_promote_and_only_owner_can_promote(): void
    {
        $s = app(InscripcionService::class);
        $wait = $s->registrarEnListaEspera($this->member, $this->group, $this->reception);
        $s->registrarBaja(Inscripcion::sole(), $this->owner, 'Prueba ficticia');
        $this->assertSame('esperando', $wait->fresh()->estado);
        $this->assertSame(0, Inscripcion::where('estado', 'aprobada')->count());
        try {
            $s->incorporarDesdeListaEspera($wait, $this->reception);
            $this->fail('Recepción no debe promover');
        } catch (AuthorizationException) {
        }$s->incorporarDesdeListaEspera($wait, $this->owner);
        $this->assertSame(1, Inscripcion::where('estado', 'aprobada')->count());
        $this->assertSame('promovido', $wait->fresh()->estado);
    }

    public function test_waiting_for_group_with_vacancy_is_rejected(): void
    {
        $this->group->update(['cupo_maximo' => 2]);
        $this->expectException(\DomainException::class);
        app(InscripcionService::class)->registrarEnListaEspera($this->member, $this->group, $this->reception);
    }

    public function test_inactive_member_cannot_join_waiting_list(): void
    {
        $this->member->update(['estado' => 'inactivo']);
        $this->expectException(\DomainException::class);
        app(InscripcionService::class)->registrarEnListaEspera($this->member, $this->group, $this->reception);
    }

    public function test_unprivileged_user_cannot_join_waiting_list(): void
    {
        $u = User::factory()->create(['rol' => 'sin_rol']);
        $this->expectException(AuthorizationException::class);
        app(InscripcionService::class)->registrarEnListaEspera($this->member, $this->group, $u);
    }

    public function test_positions_follow_group_queue_and_no_capacity_is_consumed(): void
    {
        $s = app(InscripcionService::class);
        $a = $s->registrarEnListaEspera($this->member, $this->group, $this->reception);
        $b = $s->registrarEnListaEspera($this->member(3), $this->group, $this->reception);
        $this->assertSame([1, 2], [$a->posicion, $b->posicion]);
        $this->assertSame(1, Inscripcion::where('estado', 'aprobada')->count());
    }

    public function test_inactive_group_cannot_receive_waiting_entries(): void
    {
        $this->group->update(['estado' => 'inactivo']);
        $this->expectException(\DomainException::class);
        app(InscripcionService::class)->registrarEnListaEspera($this->member, $this->group, $this->reception);
    }

    public function test_current_enrollment_prevents_waiting_entry(): void
    {
        Inscripcion::create(['grupo_id' => $this->group->id, 'socio_id' => $this->member->id, 'fecha_inscripcion' => today(), 'estado' => 'aprobada']);
        $this->expectException(\DomainException::class);
        app(InscripcionService::class)->registrarEnListaEspera($this->member, $this->group, $this->reception);
    }

    public function test_overlapping_enrollment_prevents_waiting_entry(): void
    {
        $other = Grupo::create(['nombre' => 'Horario superpuesto', 'disciplina_id' => $this->group->disciplina_id, 'cupo_maximo' => 2, 'estado' => 'activo']);
        GrupoHorario::create(['grupo_id' => $other->id, 'dia_semana' => 'lunes', 'hora_inicio' => '08:15', 'hora_fin' => '09:15']);
        Inscripcion::create(['grupo_id' => $other->id, 'socio_id' => $this->member->id, 'fecha_inscripcion' => today(), 'estado' => 'aprobada']);
        $this->expectException(\DomainException::class);
        app(InscripcionService::class)->registrarEnListaEspera($this->member, $this->group, $this->reception);
    }

    public function test_tampered_group_discipline_is_rejected_by_component(): void
    {
        $d = Disciplina::create(['nombre' => 'Otra', 'precio' => 35000, 'estado' => 'activo']);
        Livewire::actingAs($this->reception)->test(CrearInscripcion::class)->set('socio_id', $this->member->id)->set('disciplina_id', $d->id)->set('grupo_id', $this->group->id)->call('registrarEspera')->assertHasErrors('grupo_id');
        $this->assertSame(0, ListaEspera::count());
    }

    public function test_hidden_waiting_action_cannot_bypass_fresh_vacancy_check(): void
    {
        $this->group->update(['cupo_maximo' => 2]);
        Livewire::actingAs($this->reception)->test(CrearInscripcion::class)->set('socio_id', $this->member->id)->set('disciplina_id', $this->group->disciplina_id)->set('grupo_id', $this->group->id)->call('registrarEspera')->assertHasErrors('grupo_id');
        $this->assertSame(0,ListaEspera::count());
    }
}
