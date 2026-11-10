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
    public function test_01_recepcion_solicita_inscripcion(): void
    {
        $socio = $this->crearSocio();

        $resultado = $this->service->solicitarInscripcion(
            $socio,
            $this->grupoFuncional,
            $this->recepcionista,
            'Solicitud inicial recepcion'
        );

        $this->assertEquals('inscripcion_pendiente', $resultado['tipo']);
        $this->assertDatabaseHas('inscripciones', [
            'socio_id' => $socio->id,
            'grupo_id' => $this->grupoFuncional->id,
            'estado' => Inscripcion::ESTADO_PENDIENTE,
        ]);
        $this->assertDatabaseHas('solicitudes', [
            'socio_id' => $socio->id,
            'solicitante_id' => $this->recepcionista->id,
            'tipo' => Solicitud::TIPO_INSCRIPCION,
            'estado' => Solicitud::ESTADO_PENDIENTE,
        ]);
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
            'estado' => Inscripcion::ESTADO_PENDIENTE,
        ]);
    }

    public function test_la_duena_aprueba_la_solicitud_desde_la_pantalla_de_inscripciones(): void
    {
        $socio = $this->crearSocio();
        $resultado = $this->service->solicitarInscripcion(
            $socio,
            $this->grupoFuncional,
            $this->recepcionista
        );

        $this->actingAs($this->duena);
        Livewire::test(ListarInscripciones::class)
            ->call('aprobar', $resultado['modelo']->id)
            ->assertHasNoErrors();

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
     * Test 2: La solicitud queda en estado pendiente sin aprobar.
     */
    public function test_02_la_solicitud_queda_pendiente(): void
    {
        $socio = $this->crearSocio();
        $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $inscripcion = Inscripcion::where('socio_id', $socio->id)->firstOrFail();
        $this->assertTrue($inscripcion->estaPendiente());
        $this->assertFalse($inscripcion->estaAprobada());
        $this->assertNull($inscripcion->aprobado_at);
        $this->assertNull($inscripcion->aprobado_por);
    }

    /**
     * Test 3: La solicitud pendiente no ocupa cupo.
     */
    public function test_03_la_solicitud_pendiente_no_ocupa_cupo(): void
    {
        $socio = $this->crearSocio();
        $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $this->grupoFuncional->refresh();
        $this->assertEquals(0, $this->grupoFuncional->alumnosInscriptosCount());
        $this->assertEquals(15, $this->grupoFuncional->vacantesDisponibles());
        $this->assertEquals('0/15 – 15 vacantes', $this->grupoFuncional->texto_cupo);
    }

    /**
     * Test 4: La dueña aprueba la inscripción.
     */
    public function test_04_la_duena_aprueba_inscripcion(): void
    {
        $socio = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $inscripcion = $res['modelo'];
        $aprobada = $this->service->aprobarInscripcion($inscripcion, $this->duena, 'Aprobada ok');

        $this->assertTrue($aprobada->estaAprobada());
        $this->assertEquals(Inscripcion::ESTADO_APROBADA, $aprobada->estado);
        $this->assertEquals($this->duena->id, $aprobada->aprobado_por);
        $this->assertNotNull($aprobada->aprobado_at);
    }

    /**
     * Test 5: La aprobación ocupa efectivamente una plaza del grupo.
     */
    public function test_05_la_aprobacion_ocupa_una_plaza(): void
    {
        $socio = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $this->service->aprobarInscripcion($res['modelo'], $this->duena);

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

        // Al solicitar, va directo a Lista de Espera por cupo colmado
        $res16 = $this->service->solicitarInscripcion($socio16, $this->grupoFuncional, $this->recepcionista);
        $this->assertEquals('lista_espera', $res16['tipo']);

        // Si se intenta forzar una aprobación en un grupo colmado, se lanza DomainException
        $inscripcionForzada = Inscripcion::create([
            'socio_id' => $socio16->id,
            'grupo_id' => $this->grupoFuncional->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => Inscripcion::ESTADO_PENDIENTE,
        ]);

        $this->expectException(DomainException::class);
        $this->service->aprobarInscripcion($inscripcionForzada, $this->duena);
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

        // Crear dos solicitudes pendientes para la última plaza
        $socioA = $this->crearSocio();
        $socioB = $this->crearSocio();

        $resA = $this->service->solicitarInscripcion($socioA, $this->grupoFuncional, $this->recepcionista);
        $resB = $this->service->solicitarInscripcion($socioB, $this->grupoFuncional, $this->recepcionista);

        // La primera aprobación ocupa la última vacante
        $this->service->aprobarInscripcion($resA['modelo'], $this->duena);

        // La segunda aprobación falla con excepción de cupo después de agotarse la última plaza.
        try {
            $this->service->aprobarInscripcion($resB['modelo'], $this->duena);
            $this->fail("Debió fallar por cupo agotado.");
        } catch (DomainException $e) {
            $this->assertStringContainsString("cupo máximo", $e->getMessage());
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

        // Reintentar sobre solicitud pendiente
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
        $this->assertEquals('inscripcion_pendiente', $resConsecutivo['tipo']);
    }

    /**
     * Test 13: Se registra lista de espera cuando el grupo está lleno.
     */
    public function test_13_se_registra_lista_espera(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $s = $this->crearSocio();
            $r = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $this->service->aprobarInscripcion($r['modelo'], $this->duena);
        }

        $espera1 = $this->crearSocio();
        $res = $this->service->solicitarInscripcion($espera1, $this->grupoFuncional, $this->recepcionista);

        $this->assertEquals('lista_espera', $res['tipo']);
        $this->assertDatabaseHas('listas_espera', [
            'socio_id' => $espera1->id,
            'grupo_id' => $this->grupoFuncional->id,
            'posicion' => 1,
            'estado' => ListaEspera::ESTADO_ESPERANDO,
        ]);
    }

    /**
     * Test 14: No se duplica una posición activa en lista de espera y asigna orden FIFO.
     */
    public function test_14_no_se_duplica_posicion_activa(): void
    {
        // Llenar grupo
        for ($i = 1; $i <= 15; $i++) {
            $s = $this->crearSocio();
            $r = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $this->service->aprobarInscripcion($r['modelo'], $this->duena);
        }

        $espera1 = $this->crearSocio();
        $espera2 = $this->crearSocio();

        $this->service->solicitarInscripcion($espera1, $this->grupoFuncional, $this->recepcionista);
        $this->service->solicitarInscripcion($espera2, $this->grupoFuncional, $this->recepcionista);

        $this->assertDatabaseHas('listas_espera', ['socio_id' => $espera1->id, 'posicion' => 1]);
        $this->assertDatabaseHas('listas_espera', ['socio_id' => $espera2->id, 'posicion' => 2]);

        // Reintentar el mismo socio en espera debe ser rechazado
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

        $resultado = $this->service->solicitarInscripcion(
            $this->crearSocio(),
            $grupo,
            $this->recepcionista
        );
        $this->assertSame('lista_espera', $resultado['tipo']);
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
        // Llenar grupo a 15 y poner 1 en espera
        $inscripciones = [];
        for ($i = 1; $i <= 15; $i++) {
            $s = $this->crearSocio();
            $r = $this->service->solicitarInscripcion($s, $this->grupoFuncional, $this->recepcionista);
            $inscripciones[] = $this->service->aprobarInscripcion($r['modelo'], $this->duena);
        }

        $enEspera = $this->crearSocio();
        $this->service->solicitarInscripcion($enEspera, $this->grupoFuncional, $this->recepcionista);

        // Dar de baja una inscripción
        $this->service->registrarBaja($inscripciones[0], $this->duena, 'Baja voluntaria');

        // El cupo baja a 14 y el de espera sigue en estado 'esperando' (no promovido automáticamente)
        $this->grupoFuncional->refresh();
        $this->assertEquals(14, $this->grupoFuncional->alumnosInscriptosCount());

        $regEspera = ListaEspera::where('socio_id', $enEspera->id)->firstOrFail();
        $this->assertEquals(ListaEspera::ESTADO_ESPERANDO, $regEspera->estado);
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
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);

        $inscripcion = $this->service->rechazarInscripcion(
            $res['modelo'],
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
        $this->assertEquals(Inscripcion::ESTADO_PENDIENTE, $inscripcion->estado);

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

        // 1. Solicitud
        $res = $this->service->solicitarInscripcion($socio, $this->grupoFuncional, $this->recepcionista);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'solicitud_inscripcion',
            'user_id' => $this->recepcionista->id,
        ]);

        // 2. Aprobación
        $this->service->aprobarInscripcion($res['modelo'], $this->duena);
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'aprobacion_inscripcion',
            'user_id' => $this->duena->id,
        ]);
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
