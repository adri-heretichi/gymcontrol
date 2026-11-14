<?php

namespace Tests\Feature;

use App\Livewire\Inscripciones\CrearInscripcion;
use App\Livewire\Inscripciones\ListarInscripciones;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\ListaEspera;
use App\Models\Membresia;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\InscripcionService;
use App\Services\HabilitacionSocioService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NucleoFuncionalInscripcionesCuposTest extends TestCase
{
    use RefreshDatabase;

    protected User $duena;
    protected User $recepcionista;
    protected Membresia $membresia;
    protected Disciplina $disciplinaFuncional;
    protected Disciplina $disciplinaKickboxing;
    protected Grupo $grupoFuncional;
    protected InscripcionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InscripcionService::class);

        // 1. Dueña del gimnasio
        $this->duena = User::factory()->create([
            'name' => 'Adriana Dueña',
            'email' => 'duena@fenixgym.com',
            'rol' => 'admin',
        ]);

        // 2. Recepcionista
        $this->recepcionista = User::factory()->create([
            'name' => 'Recepcionista Turno',
            'email' => 'recepcion@fenixgym.com',
            'rol' => 'recepcionista',
        ]);

        // 3. Membresía
        $this->membresia = Membresia::create([
            'nombre' => 'Pase Mensual Actividades',
            'precio' => 18000.00,
            'horas_mensuales' => null,
            'estado' => 'activo',
        ]);

        // 4. Disciplinas
        $this->disciplinaFuncional = Disciplina::create([
            'nombre' => 'Funcional',
            'descripcion' => 'Entrenamiento funcional de fuerza y movilidad',
            'estado' => 'activo',
        ]);

        $this->disciplinaKickboxing = Disciplina::create([
            'nombre' => 'Kickboxing Muay Thai',
            'descripcion' => 'Artes marciales y acondicionamiento',
            'estado' => 'activo',
        ]);

        // 5. Grupo base Funcional A (Cupo 15, Lunes 08:30 a 09:30)
        $this->grupoFuncional = $this->crearGrupo(
            $this->disciplinaFuncional,
            'Funcional A',
            15,
            [['dia_semana' => 'lunes', 'hora_inicio' => '08:30:00', 'hora_fin' => '09:30:00']]
        );
    }

    protected function crearSocio(array $atributos = []): Socio
    {
        static $dniContador = 30000000;
        $dniContador++;

        return Socio::create(array_merge([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Socio',
            'apellido' => 'Test',
            'dni' => (string) $dniContador,
            'token' => 'TOK-' . $dniContador,
            'sexo' => 'M',
            'correo' => "socio{$dniContador}@fenixgym.com",
            'fecha_alta' => now()->toDateString(),
            'decision_medica' => 'pendiente',
            'estado' => 'activo',
        ], $atributos));
    }

    protected function crearGrupo(Disciplina $disciplina, string $nombre, int $cupo = 15, array $horarios = []): Grupo
    {
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => $nombre,
            'profesor' => 'Adriana Dueña',
            'cupo_maximo' => $cupo,
            'estado' => 'activo',
        ]);

        if (empty($horarios)) {
            $horarios = [
                ['dia_semana' => 'lunes', 'hora_inicio' => '18:00:00', 'hora_fin' => '19:00:00']
            ];
        }

        foreach ($horarios as $h) {
            GrupoHorario::create([
                'grupo_id' => $grupo->id,
                'dia_semana' => $h['dia_semana'],
                'hora_inicio' => $h['hora_inicio'],
                'hora_fin' => $h['hora_fin'],
            ]);
        }

        return $grupo;
    }

    /**
     * Test 1: Recepción solicita una inscripción.
     */
    public function test_01_recepcion_confirma_inscripcion_directamente(): void
    {
        $socio = $this->crearSocio();

        $resultado = $this->service->solicitarInscripcion(
            $socio,
            $this->grupoFuncional,
            $this->recepcionista,
            'Solicitud inicial recepcion'
        );

        $this->assertSame('inscripcion_confirmada', $resultado['tipo']);
        $this->assertDatabaseHas('inscripciones', [
            'socio_id' => $socio->id,
            'grupo_id' => $this->grupoFuncional->id,
            'estado' => Inscripcion::ESTADO_APROBADA,
        ]);
        $this->assertDatabaseMissing('solicitudes', ['socio_id' => $socio->id]);
        $this->assertStringContainsString('quedan 14 vacantes', $resultado['mensaje']);
    }

    public function test_la_pantalla_registra_inscripcion_en_grupo_de_la_disciplina_elegida(): void
    {
        $socio = $this->crearSocio();
        $this->actingAs($this->recepcionista);

        Livewire::test(CrearInscripcion::class)
            ->set('socio_id', $socio->id)
            ->set('disciplina_id', $this->disciplinaFuncional->id)
            ->set('grupo_id', $this->grupoFuncional->id)
            ->call('guardar')
            ->assertRedirect(route('inscripciones.index'));

        $this->assertDatabaseHas('inscripciones', [
            'socio_id' => $socio->id,
            'grupo_id' => $this->grupoFuncional->id,
            'estado' => Inscripcion::ESTADO_APROBADA,
        ]);
    }

    public function test_la_duena_consulta_la_inscripcion_confirmada_sin_aprobarla(): void
    {
        $socio = $this->crearSocio();
        $resultado = $this->service->solicitarInscripcion(
            $socio,
            $this->grupoFuncional,
            $this->recepcionista
        );

        $this->actingAs($this->duena);
        Livewire::test(ListarInscripciones::class)
            ->set('tab', 'aprobadas')
            ->assertSee('Inscripciones confirmadas')
            ->assertSee($socio->nombre_completo);

        $this->assertSame(
            Inscripcion::ESTADO_APROBADA,
            $resultado['modelo']->fresh()->estado
        );
    }

    public function test_la_pantalla_rechaza_un_grupo_que_no_pertenece_a_la_disciplina_elegida(): void
    {
        $socio = $this->crearSocio();
        $this->actingAs($this->recepcionista);

        Livewire::test(CrearInscripcion::class)
            ->set('socio_id', $socio->id)
            ->set('disciplina_id', $this->disciplinaKickboxing->id)
            ->set('grupo_id', $this->grupoFuncional->id)
            ->call('guardar')
            ->assertHasErrors(['grupo_id']);

        $this->assertDatabaseMissing('inscripciones', ['socio_id' => $socio->id]);
    }

    /**
     * La recepción confirma una inscripción y asigna una vacante.
     */
    public function test_02_la_inscripcion_queda_confirmada_sin_aprobacion_individual(): void
    {
        $socio = $this->crearSocio();
        $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $inscripcion = Inscripcion::where('socio_id', $socio->id)->firstOrFail();
        $this->assertTrue($inscripcion->estaAprobada());
        $this->assertNotNull($inscripcion->aprobado_at);
        $this->assertNull($inscripcion->aprobado_por);
    }

    /**
     * Test 3: La inscripción directa ocupa una plaza inmediatamente.
     */
    public function test_03_la_inscripcion_directa_ocupa_cupo(): void
    {
        $socio = $this->crearSocio();
        $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $this->grupoFuncional->refresh();
        $this->assertEquals(1, $this->grupoFuncional->alumnosInscriptosCount());
        $this->assertEquals(14, $this->grupoFuncional->vacantesDisponibles());
        $this->assertEquals('1/15 – 14 vacantes', $this->grupoFuncional->texto_cupo);
    }

    public function test_cada_grupo_tiene_cupo_independiente_y_los_dias_no_multiplican_plazas(): void
    {
        $grupoDosDias = $this->crearGrupo(
            $this->disciplinaFuncional,
            'Funcional dos días',
            15,
            [
                ['dia_semana' => 'lunes', 'hora_inicio' => '08:30:00', 'hora_fin' => '09:30:00'],
                ['dia_semana' => 'miercoles', 'hora_inicio' => '08:30:00', 'hora_fin' => '09:30:00'],
            ]
        );
        $otroGrupo = $this->crearGrupo(
            $this->disciplinaFuncional,
            'Funcional segundo grupo',
            15,
            [['dia_semana' => 'martes', 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00']]
        );

        for ($i = 0; $i < 15; $i++) {
            $this->service->solicitarInscripcion(
                $this->crearSocio(),
                $grupoDosDias,
                $this->recepcionista
            );
        }

        $this->assertCount(2, $grupoDosDias->horarios);
        $this->assertSame(15, $grupoDosDias->fresh()->alumnosInscriptosCount());
        $this->assertSame(0, $grupoDosDias->fresh()->vacantesDisponibles());
        $this->assertSame(0, $otroGrupo->fresh()->alumnosInscriptosCount());
        $this->assertSame(15, $otroGrupo->fresh()->vacantesDisponibles());

        $resultado = $this->service->solicitarInscripcion(
            $this->crearSocio(),
            $otroGrupo,
            $this->recepcionista
        );

        $this->assertSame('inscripcion_confirmada', $resultado['tipo']);
        $this->assertSame(1, $otroGrupo->fresh()->alumnosInscriptosCount());
    }

    public function test_inscripcion_confirmada_sin_pago_no_habilita_el_ingreso(): void
    {
        $socio = $this->crearSocio(['decision_medica' => 'no_requerido']);
        $resultado = $this->service->solicitarInscripcion(
            $socio,
            $this->grupoFuncional,
            $this->recepcionista
        );

        $habilitacion = app(HabilitacionSocioService::class)->evaluar($socio->fresh());

        $this->assertSame(Inscripcion::ESTADO_APROBADA, $resultado['modelo']->fresh()->estado);
        $this->assertFalse($habilitacion['habilitado']);
        $this->assertStringContainsString('cobertura', $habilitacion['por_inscripcion'][0]['detalle']);
    }

    /**
     * Las altas nuevas no dependen de la aprobación individual de la dueña.
     */
    public function test_04_la_inscripcion_directa_no_asigna_aprobador_duena(): void
    {
        $socio = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $inscripcion = $res['modelo'];
        $confirmada = $this->service->aprobarInscripcion($inscripcion, $this->duena, 'Aprobada ok');

        $this->assertTrue($confirmada->estaAprobada());
        $this->assertEquals(Inscripcion::ESTADO_APROBADA, $confirmada->estado);
        $this->assertNull($confirmada->aprobado_por);
        $this->assertNotNull($confirmada->aprobado_at);
    }

    /**
     * Test 5: La confirmación directa ocupa efectivamente una plaza del grupo.
     */
    public function test_05_la_confirmacion_directa_ocupa_una_plaza(): void
    {
        $socio = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $this->grupoFuncional->refresh();
        $this->assertEquals(1, $this->grupoFuncional->alumnosInscriptosCount());
        $this->assertEquals(14, $this->grupoFuncional->vacantesDisponibles());
        $this->assertEquals('1/15 – 14 vacantes', $this->grupoFuncional->texto_cupo);
    }

    /**
     * Test 6: La recepcionista no puede aprobar inscripciones.
     */
    public function test_06_la_recepcionista_no_puede_aprobar(): void
    {
        $socio = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $this->expectException(AuthorizationException::class);
        $this->service->aprobarInscripcion($res['modelo'], $this->recepcionista);
    }

    /**
     * Test 7: El alumno número 15 puede ser aprobado si queda una plaza.
     */
    public function test_07_alumno_numero_15_puede_ser_aprobado_si_queda_plaza(): void
    {
        // Aprobar 14 alumnos
        for ($i = 1; $i <= 14; $i++) {
            $s = $this->crearSocio();
            $res = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $this->service->aprobarInscripcion($res['modelo'], $this->duena);
        }

        $this->grupoFuncional->refresh();
        $this->assertEquals(14, $this->grupoFuncional->alumnosInscriptosCount());
        $this->assertEquals(1, $this->grupoFuncional->vacantesDisponibles());

        // Inscribir y aprobar al alumno número 15
        $socio15 = $this->crearSocio();
        $res15 = $this->service->solicitarInscripcion($socio15, $this->grupoFuncional, $this->recepcionista);
        $this->service->aprobarInscripcion($res15['modelo'], $this->duena);

        $this->grupoFuncional->refresh();
        $this->assertEquals(15, $this->grupoFuncional->alumnosInscriptosCount());
        $this->assertEquals(0, $this->grupoFuncional->vacantesDisponibles());
        $this->assertEquals('Cupo completo: 15/15', $this->grupoFuncional->texto_cupo);
    }

    /**
     * Test 8: El alumno número 16 no puede superar el cupo.
     */
    public function test_08_alumno_numero_16_no_puede_superar_cupo(): void
    {
        // Llenar grupo con 15 alumnos aprobados
        for ($i = 1; $i <= 15; $i++) {
            $s = $this->crearSocio();
            $res = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $this->service->aprobarInscripcion($res['modelo'], $this->duena);
        }

        $socio16 = $this->crearSocio();

        try {
            $this->service->solicitarInscripcion($socio16, $this->grupoFuncional, $this->recepcionista);
            $this->fail('No debe aceptar una inscripción número 16.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('está completo (15/15)', $e->getMessage());
        }

        $this->assertDatabaseMissing('inscripciones', ['socio_id' => $socio16->id]);
        $this->assertDatabaseMissing('listas_espera', ['socio_id' => $socio16->id]);
    }

    /**
     * Test 9: Dos aprobaciones serializadas no generan sobrecupo.
     */
    public function test_09_aprobaciones_serializadas_no_generan_sobrecupo(): void
    {
        // Dejar 14 alumnos aprobados en grupo de 15
        for ($i = 1; $i <= 14; $i++) {
            $s = $this->crearSocio();
            $res = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $this->service->aprobarInscripcion($res['modelo'], $this->duena);
        }

        // La primera inscripción directa ocupa la última vacante y la segunda se rechaza.
        $socioA = $this->crearSocio();
        $socioB = $this->crearSocio();

        $this->service->solicitarInscripcion($socioA, $this->grupoFuncional, $this->recepcionista);
        try {
            $this->service->solicitarInscripcion($socioB, $this->grupoFuncional, $this->recepcionista);
            $this->fail('La inscripción concurrente no debe exceder el cupo.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('está completo', $e->getMessage());
        }

        $this->grupoFuncional->refresh();
        $this->assertEquals(15, $this->grupoFuncional->alumnosInscriptosCount());
    }

    /**
     * Test 10: No se duplica una inscripción vigente o pendiente.
     */
    public function test_10_no_se_duplica_inscripcion(): void
    {
        $socio = $this->crearSocio();
        $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        // Reintentar sobre una inscripción confirmada
        $this->expectException(DomainException::class);
        $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
    }

    /**
     * Test 11: Un socio puede inscribirse en varias disciplinas compatibles.
     */
    public function test_11_socio_puede_inscribirse_en_varias_disciplinas(): void
    {
        $socio = $this->crearSocio();

        // 1. Inscribir y aprobar en Funcional (Lunes 08:30 a 09:30)
        $res1 = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $this->service->aprobarInscripcion($res1['modelo'], $this->duena);

        // 2. Inscribir y aprobar en Kickboxing (Martes 18:00 a 19:30)
        $grupoKick = $this->crearGrupo(
            $this->disciplinaKickboxing,
            'Kickboxing Noche',
            15,
            [['dia_semana' => 'martes', 'hora_inicio' => '18:00:00', 'hora_fin' => '19:30:00']]
        );

        $res2 = $this->service->solicitarInscripcion($socio, $grupoKick, $this->recepcionista);
        $this->service->aprobarInscripcion($res2['modelo'], $this->duena);

        $this->assertEquals(2, $socio->inscripciones()->where('estado', Inscripcion::ESTADO_APROBADA)->count());
    }

    /**
     * Test 12: Se impiden horarios incompatibles para el socio, permitiendo consecutivos.
     */
    public function test_12_se_impiden_horarios_incompatibles_socio(): void
    {
        $socio = $this->crearSocio();

        // Inscripción 1: Lunes 08:30 a 09:30
        $res1 = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $this->service->aprobarInscripcion($res1['modelo'], $this->duena);

        // Caso Incompatible: Lunes 09:00 a 10:00 (solapamiento de 30 mins)
        $grupoSolapado = $this->crearGrupo(
            $this->disciplinaKickboxing,
            'Kickboxing Mañana',
            15,
            [['dia_semana' => 'lunes', 'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00']]
        );

        try {
            $this->service->solicitarInscripcion($socio, $grupoSolapado, $this->recepcionista);
            $this->fail("Debió fallar por incompatibilidad horaria.");
        } catch (DomainException $e) {
            $this->assertStringContainsString("Incompatibilidad horaria", $e->getMessage());
        }

        // Caso Compatible Consecutivo: Lunes 09:30 a 10:30 (contiguo exacto)
        $grupoConsecutivo = $this->crearGrupo(
            $this->disciplinaKickboxing,
            'Kickboxing Continuo',
            15,
            [['dia_semana' => 'lunes', 'hora_inicio' => '09:30:00', 'hora_fin' => '10:30:00']]
        );

        $resConsecutivo = $this->service->solicitarInscripcion($socio, $grupoConsecutivo, $this->recepcionista);
        $this->assertEquals('inscripcion_confirmada', $resConsecutivo['tipo']);
    }

    /**
     * Test 13: Se registra lista de espera cuando el grupo está lleno.
     */
    public function test_13_un_grupo_completo_rechaza_nuevas_inscripciones_sin_crear_lista_espera(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $s = $this->crearSocio();
            $r = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $this->service->aprobarInscripcion($r['modelo'], $this->duena);
        }

        $espera1 = $this->crearSocio();
        try {
            $this->service->solicitarInscripcion($espera1, $this->grupoFuncional, $this->recepcionista);
            $this->fail('El grupo completo debe rechazar nuevas inscripciones.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Seleccione otro grupo', $e->getMessage());
        }

        $this->assertDatabaseMissing('listas_espera', ['socio_id' => $espera1->id]);
        $this->assertDatabaseMissing('inscripciones', ['socio_id' => $espera1->id]);
    }

    /**
     * Test 14: No se duplica una posición activa en lista de espera y asigna orden FIFO.
     */
    public function test_14_se_conservan_listas_de_espera_heredadas_sin_altas_automaticas(): void
    {
        // Llenar grupo
        for ($i = 1; $i <= 15; $i++) {
            $s = $this->crearSocio();
            $r = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $this->service->aprobarInscripcion($r['modelo'], $this->duena);
        }

        $espera1 = $this->crearSocio();
        $espera2 = $this->crearSocio();

        ListaEspera::create([
            'socio_id' => $espera1->id,
            'grupo_id' => $this->grupoFuncional->id,
            'posicion' => 1,
            'fecha_solicitud' => now(),
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);
        ListaEspera::create([
            'socio_id' => $espera2->id,
            'grupo_id' => $this->grupoFuncional->id,
            'posicion' => 2,
            'fecha_solicitud' => now(),
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);

        $this->assertDatabaseHas('listas_espera', ['socio_id' => $espera1->id, 'posicion' => 1]);
        $this->assertDatabaseHas('listas_espera', ['socio_id' => $espera2->id, 'posicion' => 2]);

        // Los registros preexistentes siguen intactos y no permiten otra inscripción paralela.
        try {
            $this->service->solicitarInscripcion($espera1, $this->grupoFuncional, $this->recepcionista);
            $this->fail("No debió permitir duplicar al socio en lista de espera.");
        } catch (DomainException $e) {
            $this->assertStringContainsString("ya se encuentra registrado en la lista de espera", $e->getMessage());
        }
    }

    public function test_no_se_puede_saltar_a_una_persona_en_lista_de_espera(): void
    {
        $grupo = $this->crearGrupo(
            $this->disciplinaFuncional,
            'Grupo con espera ordenada',
            1,
            [['dia_semana' => 'miercoles', 'hora_inicio' => '18:00:00', 'hora_fin' => '19:00:00']]
        );
        $primera = ListaEspera::create([
            'socio_id' => $this->crearSocio()->id,
            'grupo_id' => $grupo->id,
            'posicion' => 1,
            'fecha_solicitud' => now()->subMinute(),
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);
        $segunda = ListaEspera::create([
            'socio_id' => $this->crearSocio()->id,
            'grupo_id' => $grupo->id,
            'posicion' => 2,
            'fecha_solicitud' => now(),
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);

        $this->actingAs($this->duena);
        Livewire::test(ListarInscripciones::class)
            ->set('tab', 'espera')
            ->assertSee('Incorporar')
            ->assertSee('Espera #2');

        try {
            $this->service->incorporarDesdeListaEspera($segunda, $this->duena);
            $this->fail('No se debe poder saltar la primera posición de la lista de espera.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('orden de la lista de espera', $e->getMessage());
        }

        $this->service->incorporarDesdeListaEspera($primera, $this->duena);
        $this->assertSame(Inscripcion::ESTADO_APROBADA, Inscripcion::query()
            ->where('socio_id', $primera->socio_id)
            ->where('grupo_id', $grupo->id)
            ->value('estado'));
        $this->assertSame(ListaEspera::ESTADO_ESPERANDO, $segunda->fresh()->estado);
    }

    public function test_un_socio_en_espera_no_puede_evitar_el_orden_al_abrirse_un_cupo(): void
    {
        for ($i = 0; $i < 14; $i++) {
            $socioAprobado = $this->crearSocio();
            Inscripcion::create([
                'socio_id' => $socioAprobado->id,
                'grupo_id' => $this->grupoFuncional->id,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => Inscripcion::ESTADO_APROBADA,
            ]);
        }

        $socio = $this->crearSocio();
        $espera = ListaEspera::create([
            'socio_id' => $socio->id,
            'grupo_id' => $this->grupoFuncional->id,
            'posicion' => 1,
            'fecha_solicitud' => now(),
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);

        try {
            $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
            $this->fail('El socio no debe crear otra solicitud para evitar su posición en espera.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('lista de espera', $e->getMessage());
        }

        $this->assertSame(ListaEspera::ESTADO_ESPERANDO, $espera->fresh()->estado);
        $this->assertDatabaseMissing('inscripciones', ['socio_id' => $socio->id]);
    }

    public function test_recepcionista_no_puede_cancelar_un_turno_de_espera(): void
    {
        $espera = ListaEspera::create([
            'socio_id' => $this->crearSocio()->id,
            'grupo_id' => $this->grupoFuncional->id,
            'posicion' => 1,
            'fecha_solicitud' => now(),
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->cancelarListaEspera($espera, $this->recepcionista);
    }

    public function test_no_se_puede_solicitar_o_aprobar_una_inscripcion_sin_horarios(): void
    {
        $this->grupoFuncional->horarios()->delete();
        $socio = $this->crearSocio();

        try {
            $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
            $this->fail('No se debe aceptar una solicitud para un grupo sin horarios configurados.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('horarios configurados', $e->getMessage());
        }

        $inscripcion = Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $this->grupoFuncional->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => Inscripcion::ESTADO_PENDIENTE,
        ]);

        try {
            $this->service->aprobarInscripcion($inscripcion, $this->duena);
            $this->fail('No se debe aprobar una inscripción para un grupo sin horarios configurados.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('horarios configurados', $e->getMessage());
        }

        $this->assertSame(Inscripcion::ESTADO_PENDIENTE, $inscripcion->fresh()->estado);
    }

    public function test_el_limite_operativo_sigue_siendo_15_aunque_un_registro_tenga_capacidad_mayor(): void
    {
        $grupo = $this->crearGrupo(
            $this->disciplinaFuncional,
            'Grupo con capacidad histórica inválida',
            20,
            [['dia_semana' => 'jueves', 'hora_inicio' => '18:00:00', 'hora_fin' => '19:00:00']]
        );

        for ($i = 0; $i < 15; $i++) {
            $socio = $this->crearSocio();
            Inscripcion::create([
                'socio_id' => $socio->id,
                'grupo_id' => $grupo->id,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => Inscripcion::ESTADO_APROBADA,
            ]);
        }

        $this->assertSame(0, $grupo->fresh()->vacantesDisponibles());
        $this->assertSame('Cupo completo: 15/15', $grupo->fresh()->texto_cupo);

        try {
            $this->service->solicitarInscripcion($this->crearSocio(), $grupo, $this->recepcionista);
            $this->fail('Un grupo con 15 socios debe rechazar una nueva inscripción.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('está completo', $e->getMessage());
        }
    }

    /**
     * Test 15: Se libera cupo ante baja aprobada y se registra historial.
     */
    public function test_15_se_libera_cupo_ante_baja_aprobada(): void
    {
        $socio = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $inscripcion = $this->service->aprobarInscripcion($res['modelo'], $this->duena);

        $this->assertEquals(1, $this->grupoFuncional->alumnosInscriptosCount());

        $baja = $this->service->registrarBaja($inscripcion, $this->duena, 'Mudanza de domicilio');
        $this->assertTrue($baja['vacante_disponible']);

        $inscripcion->refresh();
        $this->assertEquals(Inscripcion::ESTADO_BAJA, $inscripcion->estado);
        $this->assertEquals('Mudanza de domicilio', $inscripcion->motivo_baja);
        $this->assertEquals($this->duena->id, $inscripcion->baja_por);

        $this->grupoFuncional->refresh();
        $this->assertEquals(0, $this->grupoFuncional->alumnosInscriptosCount());
    }

    /**
     * Test 16: La vacante liberada NO aprueba automáticamente a otra persona.
     */
    public function test_16_vacante_no_aprueba_automaticamente(): void
    {
        // Llenar grupo a 15 y conservar una fila heredada de lista de espera
        $inscripciones = [];
        for ($i = 1; $i <= 15; $i++) {
            $s = $this->crearSocio();
            $r = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $inscripciones[] = $this->service->aprobarInscripcion($r['modelo'], $this->duena);
        }

        $enEspera = $this->crearSocio();
        $registroEspera = ListaEspera::create([
            'socio_id' => $enEspera->id,
            'grupo_id' => $this->grupoFuncional->id,
            'posicion' => 1,
            'fecha_solicitud' => now(),
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);

        // Dar de baja una inscripción
        $this->service->registrarBaja($inscripciones[0], $this->duena, 'Baja voluntaria');

        // El cupo baja a 14 y el de espera sigue en estado 'esperando' (no promovido automáticamente)
        $this->grupoFuncional->refresh();
        $this->assertEquals(14, $this->grupoFuncional->alumnosInscriptosCount());

        $this->assertEquals(ListaEspera::ESTADO_ESPERANDO, $registroEspera->fresh()->estado);
    }

    /**
     * Test 17: La dueña puede aprobar la incorporación desde la lista de espera.
     */
    public function test_17_duena_puede_aprobar_incorporacion_desde_espera(): void
    {
        $socioActivo = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socioActivo, $this->grupoFuncional, $this->recepcionista);
        $inscripcionActiva = $this->service->aprobarInscripcion($res['modelo'], $this->duena);

        // Crear registro en espera
        $socioEspera = $this->crearSocio();
        $regEspera = ListaEspera::create([
            'socio_id' => $socioEspera->id,
            'grupo_id' => $this->grupoFuncional->id,
            'posicion' => 1,
            'fecha_solicitud' => now(),
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);

        // Dar de baja al activo
        $this->service->registrarBaja($inscripcionActiva, $this->duena, 'Baja por viaje');

        // La dueña incorpora al socio en espera
        $nuevaInscripcion = $this->service->incorporarDesdeListaEspera($regEspera, $this->duena);

        $this->assertTrue($nuevaInscripcion->estaAprobada());
        $regEspera->refresh();
        $this->assertEquals(ListaEspera::ESTADO_PROMOVIDO, $regEspera->estado);
        $this->assertEquals(1, $this->grupoFuncional->alumnosInscriptosCount());
    }

    /**
     * Test 18: El cambio de grupo es transaccional e indivisible.
     */
    public function test_18_cambio_de_grupo_transaccional(): void
    {
        $socio = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $inscripcionOrigen = $this->service->aprobarInscripcion($res['modelo'], $this->duena);

        // Grupo Destino con cupo disponible
        $grupoDestino = $this->crearGrupo(
            $this->disciplinaFuncional,
            'Funcional Tarde B',
            15,
            [['dia_semana' => 'miercoles', 'hora_inicio' => '18:00:00', 'hora_fin' => '19:00:00']]
        );

        $resultadoCambio = $this->service->ejecutarCambioGrupo(
            $inscripcionOrigen,
            $grupoDestino,
            $this->duena,
            'Cambio por trabajo'
        );

        $this->assertEquals('cambio_completado', $resultadoCambio['tipo']);

        $inscripcionOrigen->refresh();
        $this->assertEquals(Inscripcion::ESTADO_BAJA, $inscripcionOrigen->estado);

        $this->assertDatabaseHas('inscripciones', [
            'socio_id' => $socio->id,
            'grupo_id' => $grupoDestino->id,
            'estado' => Inscripcion::ESTADO_APROBADA,
        ]);
    }

    /**
     * Test 19: Un rechazo conserva historial y motivo.
     */
    public function test_19_rechazo_conserva_historial(): void
    {
        $socio = $this->crearSocio();
        $inscripcionPendienteHeredada = Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $this->grupoFuncional->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => Inscripcion::ESTADO_PENDIENTE,
        ]);

        $inscripcion = $this->service->rechazarInscripcion(
            $inscripcionPendienteHeredada,
            $this->duena,
            'Falta presentar certificado de vacunación'
        );

        $this->assertEquals(Inscripcion::ESTADO_RECHAZADA, $inscripcion->estado);
        $this->assertEquals('Falta presentar certificado de vacunación', $inscripcion->motivo_rechazo);
        $this->assertEquals($this->duena->id, $inscripcion->rechazado_por);
        $this->assertNotNull($inscripcion->rechazado_at);
    }

    /**
     * Test 20: Una petición HTTP manipulada no permite aprobar sin permiso.
     */
    public function test_20_peticion_manipulada_no_permite_aprobar(): void
    {
        $socio = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $inscripcion = $res['modelo'];

        // Recepcionista intenta invocar aprobación en componente Livewire
        $this->actingAs($this->recepcionista);
        Livewire::test(ListarInscripciones::class)
            ->call('aprobar', $inscripcion->id)
            ->assertSee('Solo la dueña del gimnasio tiene permisos para aprobar inscripciones');

        $inscripcion->refresh();
        $this->assertEquals(Inscripcion::ESTADO_APROBADA, $inscripcion->estado);

        // Invitado no autenticado
        auth()->logout();
        $this->get(route('inscripciones.index'))->assertRedirect(route('login'));
    }

    /**
     * Test 21: La auditoría registra todas las decisiones.
     */
    public function test_21_auditoria_registra_decisiones(): void
    {
        $socio = $this->crearSocio();

        // 1. Confirmación directa
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'inscripcion_confirmada',
            'user_id' => $this->recepcionista->id,
        ]);

        // Las altas normales no generan una decisión posterior de aprobación.
        $this->service->aprobarInscripcion($res['modelo'], $this->duena);
        $this->assertDatabaseMissing('auditorias', ['accion' => 'aprobacion_inscripcion']);
    }

    /**
     * Test 22: No se emiten credenciales QR/PIN por el solo hecho de aprobar la inscripción.
     */
    public function test_22_no_se_emiten_qr_o_pin_por_aprobar_inscripcion(): void
    {
        $socio = $this->crearSocio([
            'pin' => null,
            'qr_token' => null,
            'decision_medica' => 'pendiente',
        ]);

        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $this->service->aprobarInscripcion($res['modelo'], $this->duena);

        $socio->refresh();
        $this->assertNull($socio->pin);
        $this->assertNull($socio->qr_token);
        $this->assertEquals('pendiente', $socio->decision_medica);
        $this->assertFalse($socio->cumpleRequisitosParaCredenciales());
    }
}
