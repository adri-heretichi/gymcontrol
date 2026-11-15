<?php

namespace Tests\Feature;

use App\Events\GymStateChanged;
use App\Livewire\Inscripciones\CrearInscripcion;
use App\Livewire\Inscripciones\ListarInscripciones;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\ListaEspera;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class SocioDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Socio $member;

    private Grupo $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Event::fake([GymStateChanged::class]);
        $this->owner = User::factory()->create(['rol' => 'admin']);
        $this->member = Socio::create(['nombre' => 'Espera', 'apellido' => 'Temporal Uno', 'dni' => '96666666', 'sexo' => 'F', 'token' => 'display-name-test', 'fecha_alta' => today(), 'estado' => 'activo']);
        $d = Disciplina::create(['nombre' => 'Funcional', 'precio' => 35000, 'estado' => 'activo']);
        $this->group = Grupo::create(['disciplina_id' => $d->id, 'nombre' => 'Grupo de prueba', 'cupo_maximo' => 15, 'estado' => 'activo']);
        GrupoHorario::create(['grupo_id' => $this->group->id, 'dia_semana' => 'lunes', 'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00']);
    }

    public function test_approved_enrollment_and_waiting_list_render_literal_member_name(): void
    {
        Inscripcion::create(['socio_id' => $this->member->id, 'grupo_id' => $this->group->id, 'fecha_inscripcion' => today(), 'estado' => 'aprobada']);
        ListaEspera::create(['socio_id' => $this->member->id, 'grupo_id' => $this->group->id, 'posicion' => 1, 'estado' => 'esperando', 'fecha_solicitud' => now()]);
        Livewire::actingAs($this->owner)->test(ListarInscripciones::class)->set('tab', 'aprobadas')->assertSee('Espera Temporal Uno');
        Livewire::actingAs($this->owner)->test(ListarInscripciones::class)->set('tab', 'espera')->assertSee('Espera Temporal Uno');
    }

    public function test_selection_keeps_member_name_and_dni_visible(): void
    {
        Livewire::actingAs($this->owner)->test(CrearInscripcion::class)->call('seleccionarSocio', $this->member->id)->assertSet('buscarSocio', 'Espera Temporal Uno (DNI 96666666)');
    }
}
