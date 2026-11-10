<?php

namespace Tests\Feature;

use App\Livewire\Disciplinas\CrearDisciplina;
use App\Livewire\Disciplinas\EditarDisciplina;
use App\Livewire\Disciplinas\ListarDisciplinas;
use App\Livewire\Grupos\CrearGrupo;
use App\Livewire\Grupos\EditarGrupo;
use App\Livewire\Grupos\ListarGrupos;
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

class NucleoFuncionalDisciplinasGruposTest extends TestCase
{
    use RefreshDatabase;

    protected User $duena;
    protected User $recepcionista;
    protected Membresia $membresia;
    protected Disciplina $disciplinaBase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Dueña / Administradora del gimnasio
        $this->duena = User::factory()->create([
            'name' => 'Adriana Heretichi',
            'email' => 'duena@fenixgym.com',
            'rol' => 'admin',
        ]);

        // 2. Recepcionista
        $this->recepcionista = User::factory()->create([
            'name' => 'Recepcionista Turno',
            'email' => 'recepcion@fenixgym.com',
            'rol' => 'recepcionista',
        ]);

        // 3. Membresía de referencia
        $this->membresia = Membresia::create([
            'nombre' => 'Pase Libre Marcial',
            'precio' => 20000.00,
            'horas_mensuales' => null,
            'estado' => 'activo',
        ]);

        // 4. Disciplina base
        $this->disciplinaBase = Disciplina::create([
            'nombre' => 'Krav Maga Kids',
            'descripcion' => 'Defensa personal para niños',
            'estado' => 'activo',
        ]);
    }

    protected function crearSocio(array $atributos = []): Socio
    {
        static $dniContador = 10000000;
        $dniContador++;

        return Socio::create(array_merge([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Alumno',
            'apellido' => 'Prueba',
            'dni' => (string) $dniContador,
            'token' => 'TOK-' . $dniContador,
            'sexo' => 'M',
            'correo' => "socio{$dniContador}@fenixgym.com",
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ], $atributos));
    }

    /**
     * Test 1: Crear una disciplina válida.
     */
    public function test_01_crear_disciplina_valida(): void
    {
        $this->actingAs($this->duena);

        Livewire::test(CrearDisciplina::class)
            ->set('nombre', 'Defensa Personal Táctica')
            ->set('descripcion', 'Técnicas avanzadas de autoprotección')
            ->set('estado', 'activo')
            ->call('guardar')
            ->assertRedirect(route('disciplinas.index'));

        $this->assertDatabaseHas('disciplinas', [
            'nombre' => 'Defensa Personal Táctica',
            'estado' => 'activo',
        ]);
    }

    /**
     * Test 2: Impedir disciplinas duplicadas por nombre.
     */
    public function test_02_impedir_disciplinas_duplicadas(): void
    {
        $this->actingAs($this->duena);

        Livewire::test(CrearDisciplina::class)
            ->set('nombre', 'Krav Maga Kids') // Ya existe
            ->set('descripcion', 'Otra descripción')
            ->call('guardar')
            ->assertHasErrors(['nombre']);

        $this->assertEquals(1, Disciplina::where('nombre', 'Krav Maga Kids')->count());
    }

    /**
     * Test 3: Desactivar una disciplina sin eliminar su historial ni sus grupos.
     */
    public function test_03_desactivar_disciplina_sin_eliminar_su_historial(): void
    {
        $this->actingAs($this->duena);

        $grupo = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Infantiles A',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        Livewire::test(ListarDisciplinas::class)
            ->call('alternarEstado', $this->disciplinaBase->id);

        $this->disciplinaBase->refresh();
        $this->assertEquals('inactivo', $this->disciplinaBase->estado);

        // El grupo sigue existiendo y vinculado intacto
        $this->assertDatabaseHas('grupos', [
            'id' => $grupo->id,
            'disciplina_id' => $this->disciplinaBase->id,
        ]);
    }

    /**
     * Test 4: Crear un grupo válido con cupo permitido.
     */
    public function test_04_crear_grupo_valido_con_cupo_permitido(): void
    {
        $this->actingAs($this->duena);

        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Turno Mañana A')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 15)
            ->set('estado', 'activo')
            ->set('horarios', [
                [
                    'dia_semana' => 'lunes',
                    'hora_inicio' => '09:00',
                    'hora_fin' => '10:30',
                ],
            ])
            ->call('guardar')
            ->assertRedirect(route('grupos.index'));

        $this->assertDatabaseHas('grupos', [
            'nombre' => 'Turno Mañana A',
            'cupo_maximo' => 15,
            'disciplina_id' => $this->disciplinaBase->id,
        ]);

        $this->assertDatabaseHas('grupo_horarios', [
            'dia_semana' => 'lunes',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '10:30:00',
        ]);
    }

    public function test_crear_grupo_sin_inventar_horario_predeterminado(): void
    {
        $this->actingAs($this->duena);

        Livewire::test(CrearGrupo::class)
            ->assertSet('horarios.0.dia_semana', '')
            ->assertSet('horarios.0.hora_inicio', '')
            ->assertSet('horarios.0.hora_fin', '')
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Grupo sin cronograma')
            ->set('profesor', 'Adriana Heretichi')
            ->call('guardar')
            ->assertHasErrors([
                'horarios.0.dia_semana',
                'horarios.0.hora_inicio',
                'horarios.0.hora_fin',
            ]);

        $this->assertDatabaseMissing('grupos', ['nombre' => 'Grupo sin cronograma']);
    }

    public function test_editar_grupo_legado_sin_horarios_exige_configuracion_explicita(): void
    {
        $grupo = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Grupo legado sin horario',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
        $this->actingAs($this->duena);

        Livewire::test(EditarGrupo::class, ['grupo' => $grupo])
            ->assertSet('horarios.0.dia_semana', '')
            ->assertSet('horarios.0.hora_inicio', '')
            ->assertSet('horarios.0.hora_fin', '')
            ->call('actualizar')
            ->assertHasErrors([
                'horarios.0.dia_semana',
                'horarios.0.hora_inicio',
                'horarios.0.hora_fin',
            ]);

        $this->assertSame(0, $grupo->horarios()->count());
    }

    /**
     * Test 5: Rechazo estricto de grupo con capacidad superior a 15 alumnos.
     */
    public function test_05_rechazar_grupo_con_capacidad_superior_a_15(): void
    {
        $this->actingAs($this->duena);

        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Sobrecupo Invalido')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 16) // Mayor a 15
            ->set('horarios', [
                [
                    'dia_semana' => 'lunes',
                    'hora_inicio' => '18:00',
                    'hora_fin' => '19:00',
                ],
            ])
            ->call('guardar')
            ->assertHasErrors(['cupo_maximo']);

        $this->assertDatabaseMissing('grupos', [
            'nombre' => 'Sobrecupo Invalido',
        ]);
    }

    /**
     * Test 6: Permitir capacidad inferior a 15 alumnos.
     */
    public function test_06_permitir_capacidad_inferior_a_15(): void
    {
        $this->actingAs($this->duena);

        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Grupo Reducido Especial')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 8) // Menor a 15 permitido
            ->set('horarios', [
                [
                    'dia_semana' => 'martes',
                    'hora_inicio' => '18:00',
                    'hora_fin' => '19:00',
                ],
            ])
            ->call('guardar')
            ->assertRedirect(route('grupos.index'));

        $this->assertDatabaseHas('grupos', [
            'nombre' => 'Grupo Reducido Especial',
            'cupo_maximo' => 8,
        ]);
    }

    /**
     * Test 7: Consulta correcta de cupos y vacantes.
     */
    public function test_07_consultar_cupos_y_vacantes(): void
    {
        $grupo = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Adultos Tarde',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        // Sin alumnos
        $this->assertEquals(15, $grupo->vacantesDisponibles());
        $this->assertTrue($grupo->tieneCupoDisponible());
        $this->assertEquals('0/15 – 15 vacantes', $grupo->texto_cupo);

        // Inscribir 12 alumnos aprobados
        for ($i = 1; $i <= 12; $i++) {
            $socio = $this->crearSocio([
                'nombre' => "Alumno{$i}",
            ]);

            Inscripcion::create([
                'socio_id' => $socio->id,
                'grupo_id' => $grupo->id,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => 'aprobada',
            ]);
        }

        $grupo->refresh();
        $this->assertEquals(3, $grupo->vacantesDisponibles());
        $this->assertTrue($grupo->tieneCupoDisponible());
        $this->assertEquals('12/15 – 3 vacantes', $grupo->texto_cupo);

        // Inscribir 3 alumnos más para completar el cupo de 15
        for ($i = 13; $i <= 15; $i++) {
            $socio = $this->crearSocio([
                'nombre' => "Alumno{$i}",
            ]);

            Inscripcion::create([
                'socio_id' => $socio->id,
                'grupo_id' => $grupo->id,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => 'aprobada',
            ]);
        }

        $grupo->refresh();
        $this->assertEquals(0, $grupo->vacantesDisponibles());
        $this->assertFalse($grupo->tieneCupoDisponible());
        $this->assertEquals('Cupo completo: 15/15', $grupo->texto_cupo);
    }

    public function test_listado_muestra_disciplina_horarios_y_vacantes_confirmadas(): void
    {
        $grupo = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Krav Maga Kids Tarde',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
        GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => 'miercoles',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
        ]);

        for ($i = 1; $i <= 14; $i++) {
            $socio = $this->crearSocio();
            Inscripcion::create([
                'socio_id' => $socio->id,
                'grupo_id' => $grupo->id,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => Inscripcion::ESTADO_APROBADA,
            ]);
        }

        $this->actingAs($this->recepcionista);
        Livewire::test(ListarGrupos::class)
            ->assertSee('Krav Maga Kids Tarde')
            ->assertSee('Krav Maga Kids')
            ->assertSee('Miércoles 18:00 - 19:00')
            ->assertSee('14/15 – 1 vacantes');
    }

    /**
     * Test 8: Guardar múltiples días y horarios para un grupo.
     */
    public function test_08_guardar_varios_dias_y_horarios_para_un_grupo(): void
    {
        $this->actingAs($this->duena);

        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Multi-Días Fénix')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 15)
            ->set('horarios', [
                ['dia_semana' => 'lunes', 'hora_inicio' => '18:00', 'hora_fin' => '19:00'],
                ['dia_semana' => 'miercoles', 'hora_inicio' => '18:00', 'hora_fin' => '19:00'],
                ['dia_semana' => 'viernes', 'hora_inicio' => '18:00', 'hora_fin' => '19:00'],
            ])
            ->call('guardar')
            ->assertRedirect(route('grupos.index'));

        $grupo = Grupo::where('nombre', 'Multi-Días Fénix')->firstOrFail();
        $this->assertEquals(3, $grupo->horarios()->count());

        $this->assertDatabaseHas('grupo_horarios', [
            'grupo_id' => $grupo->id,
            'dia_semana' => 'lunes',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
        ]);
        $this->assertDatabaseHas('grupo_horarios', [
            'grupo_id' => $grupo->id,
            'dia_semana' => 'miercoles',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
        ]);
        $this->assertDatabaseHas('grupo_horarios', [
            'grupo_id' => $grupo->id,
            'dia_semana' => 'viernes',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
        ]);
    }

    /**
     * Test 9: Rechazar hora de inicio posterior o igual a hora de finalización.
     */
    public function test_09_rechazar_hora_inicio_posterior_o_igual_a_fin(): void
    {
        $this->actingAs($this->duena);

        // Caso 1: Inicio posterior a fin
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Horario Invertido')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 15)
            ->set('horarios', [
                ['dia_semana' => 'lunes', 'hora_inicio' => '19:00', 'hora_fin' => '18:00'],
            ])
            ->call('guardar')
            ->assertHasErrors(['horarios']);

        // Caso 2: Inicio igual a fin
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Horario Cero')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 15)
            ->set('horarios', [
                ['dia_semana' => 'lunes', 'hora_inicio' => '18:00', 'hora_fin' => '18:00'],
            ])
            ->call('guardar')
            ->assertHasErrors(['horarios']);
    }

    /**
     * Test 10: Rechazar horarios superpuestos para la única profesora.
     */
    public function test_10_rechazar_horarios_superpuestos_para_unica_profesora(): void
    {
        $this->actingAs($this->duena);

        // Crear Grupo 1 activo de 18:00 a 19:30 los Martes
        $grupo1 = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Grupo Martes A',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
        GrupoHorario::create([
            'grupo_id' => $grupo1->id,
            'dia_semana' => 'martes',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:30:00',
        ]);

        // Intentar crear Grupo 2 activo de 19:00 a 20:30 los Martes (colisión de 30 mins)
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Grupo Martes Conflicto')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 15)
            ->set('estado', 'activo')
            ->set('horarios', [
                ['dia_semana' => 'martes', 'hora_inicio' => '19:00', 'hora_fin' => '20:30'],
            ])
            ->call('guardar')
            ->assertHasErrors(['horarios']);

        $this->assertDatabaseMissing('grupos', [
            'nombre' => 'Grupo Martes Conflicto',
        ]);
    }

    /**
     * Test 11: Permitir clases en horarios compatibles sin solapamiento.
     */
    public function test_11_permitir_clases_en_horarios_compatibles(): void
    {
        $this->actingAs($this->duena);

        // Grupo 1: Martes de 18:00 a 19:00
        $grupo1 = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Grupo Martes Turno 1',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
        GrupoHorario::create([
            'grupo_id' => $grupo1->id,
            'dia_semana' => 'martes',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
        ]);

        // Grupo 2: Martes de 19:00 a 20:00 (contiguo exacto, compatible)
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Grupo Martes Turno 2')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 15)
            ->set('estado', 'activo')
            ->set('horarios', [
                ['dia_semana' => 'martes', 'hora_inicio' => '19:00', 'hora_fin' => '20:00'],
            ])
            ->call('guardar')
            ->assertRedirect(route('grupos.index'));

        $this->assertDatabaseHas('grupos', ['nombre' => 'Grupo Martes Turno 2']);

        // Grupo 3: Jueves de 18:00 a 19:00 (día distinto, totalmente libre)
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Grupo Jueves Turno 1')
            ->set('profesor', 'Adriana Heretichi')
            ->set('cupo_maximo', 15)
            ->set('estado', 'activo')
            ->set('horarios', [
                ['dia_semana' => 'jueves', 'hora_inicio' => '18:00', 'hora_fin' => '19:00'],
            ])
            ->call('guardar')
            ->assertRedirect(route('grupos.index'));

        $this->assertDatabaseHas('grupos', ['nombre' => 'Grupo Jueves Turno 1']);
    }

    /**
     * Test 12: Rechazar horarios fuera de la apertura configurada o que crucen el mediodía.
     */
    public function test_12_rechazar_horarios_fuera_de_apertura(): void
    {
        $this->actingAs($this->duena);

        // Fuera de horario matutino (antes de las 08:00)
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Madrugadores')
            ->set('horarios', [
                ['dia_semana' => 'lunes', 'hora_inicio' => '07:00', 'hora_fin' => '08:30'],
            ])
            ->call('guardar')
            ->assertHasErrors(['horarios']);

        // Fuera de horario nocturno (después de las 22:00)
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Trasnochadores')
            ->set('horarios', [
                ['dia_semana' => 'lunes', 'hora_inicio' => '21:30', 'hora_fin' => '22:30'],
            ])
            ->call('guardar')
            ->assertHasErrors(['horarios']);

        // Cruzando el receso del mediodía (11:30 a 13:00)
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Mediodía Cruce')
            ->set('horarios', [
                ['dia_semana' => 'lunes', 'hora_inicio' => '11:30', 'hora_fin' => '13:00'],
            ])
            ->call('guardar')
            ->assertHasErrors(['horarios']);

        // Dentro del receso cerrado (12:30 a 14:00)
        Livewire::test(CrearGrupo::class)
            ->set('disciplina_id', $this->disciplinaBase->id)
            ->set('nombre', 'Receso Cerrado')
            ->set('horarios', [
                ['dia_semana' => 'lunes', 'hora_inicio' => '12:30', 'hora_fin' => '14:00'],
            ])
            ->call('guardar')
            ->assertHasErrors(['horarios']);
    }

    /**
     * Test 13: Impedir reducir la capacidad por debajo de los alumnos aprobados.
     */
    public function test_13_impedir_reducir_capacidad_por_debajo_de_alumnos_aprobados(): void
    {
        $this->actingAs($this->duena);

        $grupo = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Grupo Lleno Parcial',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
        GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => 'lunes',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:00:00',
        ]);

        // Registrar 6 alumnos aprobados
        for ($i = 1; $i <= 6; $i++) {
            $socio = $this->crearSocio([
                'nombre' => "Socio{$i}",
            ]);

            Inscripcion::create([
                'socio_id' => $socio->id,
                'grupo_id' => $grupo->id,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => 'aprobada',
            ]);
        }

        // Intentar reducir cupo a 5 (menor que los 6 inscriptos)
        Livewire::test(EditarGrupo::class, ['grupo' => $grupo])
            ->set('cupo_maximo', 5)
            ->call('actualizar')
            ->assertHasErrors(['cupo_maximo']);

        $grupo->refresh();
        $this->assertEquals(15, $grupo->cupo_maximo);

        // Reducir cupo a 10 (válido porque 10 >= 6 y 10 <= 15)
        Livewire::test(EditarGrupo::class, ['grupo' => $grupo])
            ->set('cupo_maximo', 10)
            ->call('actualizar')
            ->assertRedirect(route('grupos.index'));

        $grupo->refresh();
        $this->assertEquals(10, $grupo->cupo_maximo);
    }

    /**
     * Test 14: Conservar relaciones y prevenir eliminación inválida.
     */
    public function test_14_conservar_relaciones_y_prevenir_eliminacion_invalida(): void
    {
        $this->actingAs($this->duena);

        $grupo = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Grupo Con Historial',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        // 1. La disciplina tiene grupo: no puede eliminarse físicamente
        $this->assertFalse($this->disciplinaBase->puedeEliminarse());

        Livewire::test(ListarDisciplinas::class)
            ->call('eliminar', $this->disciplinaBase->id)
            ->assertSee('No es posible eliminar la disciplina');

        $this->assertDatabaseHas('disciplinas', ['id' => $this->disciplinaBase->id]);

        // 2. Asociar inscripción al grupo: el grupo no puede eliminarse físicamente
        $socio = $this->crearSocio([
            'nombre' => 'Carlos',
            'apellido' => 'Gómez',
        ]);
        Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'aprobada',
        ]);

        $this->assertFalse($grupo->puedeEliminarse());

        Livewire::test(ListarGrupos::class)
            ->call('eliminar', $grupo->id)
            ->assertSee('No es posible eliminar el grupo');

        $this->assertDatabaseHas('grupos', ['id' => $grupo->id]);

        // 3. Disciplina vacía sin grupos sí puede eliminarse
        $disciplinaVacia = Disciplina::create([
            'nombre' => 'Pilates Provisorio',
            'estado' => 'inactivo',
        ]);
        $this->assertTrue($disciplinaVacia->puedeEliminarse());

        Livewire::test(ListarDisciplinas::class)
            ->call('eliminar', $disciplinaVacia->id);

        $this->assertDatabaseMissing('disciplinas', ['id' => $disciplinaVacia->id]);
    }

    /**
     * Test 15: Verificar autorizaciones de la dueña, recepcionista e invitados.
     */
    public function test_15_verificar_autorizaciones_duena_recepcion_e_invitados(): void
    {
        $grupo = Grupo::create([
            'disciplina_id' => $this->disciplinaBase->id,
            'nombre' => 'Grupo Acceso',
            'profesor' => 'Adriana Heretichi',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        // --- RECEPCIONISTA ---
        $this->actingAs($this->recepcionista);

        // Puede consultar listados
        $this->get(route('disciplinas.index'))->assertStatus(200);
        $this->get(route('grupos.index'))->assertStatus(200);

        // NO puede acceder a pantallas de creación o edición (403)
        $this->get(route('disciplinas.create'))->assertStatus(403);
        $this->get(route('disciplinas.edit', $this->disciplinaBase->id))->assertStatus(403);
        $this->get(route('grupos.create'))->assertStatus(403);
        $this->get(route('grupos.edit', $grupo->id))->assertStatus(403);

        // Manipulación Livewire rechazada
        Livewire::test(ListarDisciplinas::class)
            ->call('alternarEstado', $this->disciplinaBase->id)
            ->assertSee('No tienes permisos');

        Livewire::test(ListarGrupos::class)
            ->call('alternarEstado', $grupo->id)
            ->assertSee('No tienes permisos');

        // --- INVITADO NO AUTENTICADO ---
        auth()->logout();

        $this->get(route('disciplinas.index'))->assertRedirect(route('login'));
        $this->get(route('grupos.index'))->assertRedirect(route('login'));
        $this->get(route('disciplinas.create'))->assertRedirect(route('login'));
        $this->get(route('grupos.create'))->assertRedirect(route('login'));

        // --- DUEÑA (ADMIN) ---
        $this->actingAs($this->duena);
        $this->get(route('disciplinas.index'))->assertStatus(200);
        $this->get(route('grupos.index'))->assertStatus(200);
        $this->get(route('disciplinas.create'))->assertStatus(200);
        $this->get(route('disciplinas.edit', $this->disciplinaBase->id))->assertStatus(200);
        $this->get(route('grupos.create'))->assertStatus(200);
        $this->get(route('grupos.edit', $grupo->id))->assertStatus(200);
    }
}
