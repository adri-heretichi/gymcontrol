<?php

namespace Tests\Feature;

use App\Livewire\Disciplinas\EditarDisciplina;
use App\Livewire\Grupos\CrearGrupo;
use App\Livewire\Grupos\EditarGrupo;
use App\Livewire\Membresias\ListarMembresias;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Socio;
use App\Models\User;
use App\Services\HorarioGimnasioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembresiasDisciplinasTest extends TestCase
{
    use RefreshDatabase;

    private User $duena;
    private Membresia $membresiaLegacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->duena = User::factory()->create(['rol' => 'admin']);
        $this->membresiaLegacy = Membresia::create([
            'nombre' => 'Plan heredado de prueba',
            'precio' => 0,
            'horas_mensuales' => null,
            'estado' => 'activo',
        ]);
    }

    public function test_los_cuatro_planes_fenix_reutilizan_el_catalogo_de_disciplinas(): void
    {
        $this->assertSame([
            'Krav Maga Kids',
            'Kapap Krav Maga',
            'Kickboxing Muay Thai',
            'Funcional',
        ], Disciplina::PLANES_FENIX);

        foreach (Disciplina::PLANES_FENIX as $nombre) {
            Disciplina::create([
                'nombre' => $nombre,
                'estado' => 'activo',
            ]);
        }

        $this->actingAs($this->duena);

        Livewire::test(ListarMembresias::class)
            ->assertSee('Membresías')
            ->assertSee('Krav Maga Kids')
            ->assertSee('Kapap Krav Maga')
            ->assertSee('Kickboxing Muay Thai')
            ->assertSee('Funcional')
            ->assertSee('Precio pendiente de definición')
            ->assertDontSee('Básica')
            ->assertDontSee('Premium');

        $this->assertSame(4, Disciplina::whereIn('nombre', Disciplina::PLANES_FENIX)->count());
    }

    public function test_una_disciplina_admite_varios_grupos_y_la_vista_muestra_cupos_por_grupo(): void
    {
        $disciplina = $this->crearDisciplina('Funcional');
        $grupoConVacante = $this->crearGrupo($disciplina, 'Funcional Mañana');
        $grupoCompleto = $this->crearGrupo($disciplina, 'Funcional Tarde');

        $this->crearHorario($grupoConVacante, 'lunes', '08:00:00', '09:00:00');
        $this->crearHorario($grupoCompleto, 'martes', '15:00:00', '16:00:00');
        $this->crearInscriptosAprobados($grupoConVacante, 14, 10000000);
        $this->crearInscriptosAprobados($grupoCompleto, 15, 20000000);

        $this->actingAs($this->duena);

        Livewire::test(ListarMembresias::class)
            ->set('disciplina', (string) $disciplina->id)
            ->assertSee('Funcional Mañana')
            ->assertSee('Funcional Tarde')
            ->assertSee('Lunes, 08:00–09:00')
            ->assertSee('Martes, 15:00–16:00')
            ->assertSee('14/15')
            ->assertSee('Vacantes: 1')
            ->assertSee('15/15')
            ->assertSee('Vacantes: 0')
            ->assertSee('Inscripción abierta')
            ->assertSee('Completo');

        $this->assertSame(2, $disciplina->grupos()->count());
        $this->assertSame($disciplina->id, $grupoConVacante->fresh()->disciplina_id);
        $this->assertSame($disciplina->id, $grupoCompleto->fresh()->disciplina_id);
    }

    public function test_precio_de_disciplina_es_opcional_y_se_administra_desde_editar_membresia(): void
    {
        $disciplina = $this->crearDisciplina('Krav Maga Kids');

        $this->actingAs($this->duena);

        Livewire::test(EditarDisciplina::class, ['disciplina' => $disciplina])
            ->assertSet('precio', '')
            ->set('precio', '12500.50')
            ->call('actualizar')
            ->assertRedirect(route('disciplinas.index'));

        $this->assertSame('12500.50', $disciplina->fresh()->precio);

        Livewire::test(EditarDisciplina::class, ['disciplina' => $disciplina->fresh()])
            ->set('precio', '')
            ->call('actualizar');

        $this->assertNull($disciplina->fresh()->precio);
    }

    public function test_membresias_reutiliza_las_pantallas_existentes_de_creacion_y_edicion_de_grupos(): void
    {
        $disciplina = $this->crearDisciplina('Kapap Krav Maga');
        $grupo = $this->crearGrupo($disciplina, 'Kapap Adultos');
        $this->crearHorario($grupo, 'martes', '18:00:00', '19:00:00');
        $this->actingAs($this->duena);

        $this->get(route('membresias.index'))
            ->assertOk()
            ->assertSee(route('grupos.create', [
                'disciplina_id' => $disciplina->id,
                'origen' => 'membresias',
            ]))
            ->assertSee(route('grupos.edit', [
                'grupo' => $grupo->id,
                'origen' => 'membresias',
            ]));

        $this->get(route('grupos.create', [
            'disciplina_id' => $disciplina->id,
            'origen' => 'membresias',
        ]))->assertOk();

        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $disciplina->id)
            ->set('volverAMembresias', true)
            ->set('nombre', 'Kapap Principiantes')
            ->set('horarios', [
                ['dia_semana' => 'jueves', 'hora_inicio' => '16:00', 'hora_fin' => '17:00'],
            ])
            ->call('guardar')
            ->assertRedirect(route('membresias.index', ['disciplina' => $disciplina->id]));

        Livewire::test(EditarGrupo::class, ['grupo' => $grupo])
            ->set('volverAMembresias', true)
            ->set('nombre', 'Kapap Adultos Actualizado')
            ->call('actualizar')
            ->assertRedirect(route('membresias.index', ['disciplina' => $disciplina->id]));

        $this->assertDatabaseHas('grupos', [
            'id' => $grupo->id,
            'nombre' => 'Kapap Adultos Actualizado',
            'disciplina_id' => $disciplina->id,
        ]);
    }

    public function test_horarios_admiten_solo_dias_habiles_y_turnos_de_apertura(): void
    {
        $validos = [
            ['dia_semana' => 'lunes', 'hora_inicio' => '08:00', 'hora_fin' => '12:00'],
            ['dia_semana' => 'viernes', 'hora_inicio' => '15:00', 'hora_fin' => '20:00'],
        ];

        $this->assertNull(HorarioGimnasioService::validarColeccionHorarios($validos));
        $this->assertNotNull(HorarioGimnasioService::validarColeccionHorarios([
            ['dia_semana' => 'sabado', 'hora_inicio' => '09:00', 'hora_fin' => '10:00'],
        ]));
        $this->assertNotNull(HorarioGimnasioService::validarColeccionHorarios([
            ['dia_semana' => 'lunes', 'hora_inicio' => '19:30', 'hora_fin' => '20:30'],
        ]));

        $this->assertSame([
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
        ], HorarioGimnasioService::DIAS_SEMANA);
    }

    private function crearDisciplina(string $nombre): Disciplina
    {
        return Disciplina::create([
            'nombre' => $nombre,
            'estado' => 'activo',
        ]);
    }

    private function crearGrupo(Disciplina $disciplina, string $nombre): Grupo
    {
        return Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => $nombre,
            'profesor' => 'Coach Fénix',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
    }

    private function crearHorario(Grupo $grupo, string $dia, string $inicio, string $fin): void
    {
        GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]);
    }

    private function crearInscriptosAprobados(Grupo $grupo, int $cantidad, int $dniBase): void
    {
        for ($i = 1; $i <= $cantidad; $i++) {
            $socio = Socio::create([
                'membresia_id' => $this->membresiaLegacy->id,
                'apellido' => 'Prueba',
                'nombre' => "Socio {$i}",
                'dni' => (string) ($dniBase + $i),
                'sexo' => 'X',
                'token' => "TOKEN-{$dniBase}-{$i}",
                'fecha_alta' => now()->toDateString(),
                'estado' => 'activo',
            ]);

            Inscripcion::create([
                'socio_id' => $socio->id,
                'grupo_id' => $grupo->id,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => Inscripcion::ESTADO_APROBADA,
            ]);
        }
    }
}
