<?php

namespace Tests\Feature;

use App\Models\AptoFisico;
use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\ListaEspera;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NucleoFuncionalBaseDatosTest extends TestCase
{
    use RefreshDatabase;

    protected User $duena;
    protected User $recepcionista;
    protected Membresia $membresia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->duena = User::factory()->create([
            'name' => 'Dueña Gimnasio',
            'email' => 'duena@gymcontrol.com',
            'rol' => 'admin',
        ]);

        $this->recepcionista = User::factory()->create([
            'name' => 'Recepcionista Turno',
            'email' => 'recep@gymcontrol.com',
            'rol' => 'recepcionista',
        ]);

        $this->membresia = Membresia::create([
            'nombre' => 'Pase Libre Musculación y Clases',
            'precio' => 25000.00,
            'horas_mensuales' => null,
            'estado' => 'activo',
        ]);
    }

    /**
     * Test: Verificación de estructura de tablas, credenciales (PIN 6 dígitos y QR token) y decisión médica.
     */
    public function test_socio_soporta_pin_seis_digitos_qr_token_y_decision_medica(): void
    {
        $socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Facundo',
            'apellido' => 'Quiroga',
            'dni' => '40111222',
            'sexo' => 'M',
            'correo' => 'facundo@test.com',
            'token' => 'TOKEN999',
            'pin' => '123456', // 6 dígitos
            'qr_token' => bin2hex(random_bytes(32)), // 64 chars
            'estado_credencial' => 'sin_emitir',
            'decision_medica' => 'pendiente',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        $this->assertDatabaseHas('socios', [
            'id' => $socio->id,
            'pin' => '123456',
            'decision_medica' => 'pendiente',
            'estado_credencial' => 'sin_emitir',
        ]);

        $this->assertEquals(6, strlen($socio->pin));
        $this->assertEquals('pendiente', $socio->decision_medica);
        // Inicialmente con decisión médica pendiente, no cumple el requisito médico
        $this->assertFalse($socio->cumpleRequisitoMedico());
    }

    /**
     * Test: Decisión médica individual de la Dueña: pendiente, requerida y no requerida.
     */
    public function test_decision_medica_individual_evalua_correctamente(): void
    {
        $socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Lorena',
            'apellido' => 'Vega',
            'dni' => '39888777',
            'sexo' => 'F',
            'correo' => 'lorena@test.com',
            'token' => 'TOKEN888',
            'decision_medica' => 'pendiente',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        // 1. Estado inicial 'pendiente': no cumple
        $this->assertFalse($socio->cumpleRequisitoMedico());

        // 2. Dueña dictamina 'no_requerido': cumple inmediatamente sin certificado médico
        $socio->update([
            'decision_medica' => 'no_requerido',
            'decision_medica_at' => now(),
            'decision_medica_por' => $this->duena->id,
            'decision_medica_obs' => 'Eximida por bajo impacto y declaración jurada',
        ]);
        $this->assertTrue($socio->fresh()->cumpleRequisitoMedico());
        $this->assertEquals($this->duena->id, $socio->fresh()->decisionMedicaPor->id);

        // 3. Dueña cambia a 'requerido': al no tener apto físico cargado, no cumple
        $socio->update(['decision_medica' => 'requerido']);
        $this->assertFalse($socio->fresh()->cumpleRequisitoMedico());

        // 4. Se carga apto físico vigente: ahora sí cumple
        AptoFisico::create([
            'socio_id' => $socio->id,
            'archivo' => 'apto_lorena.pdf',
            'fecha_emision' => now()->subDays(5)->toDateString(),
            'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
            'estado' => 'vigente',
        ]);
        $this->assertTrue($socio->fresh()->cumpleRequisitoMedico());
    }

    /**
     * Test: Un socio puede inscribirse en múltiples disciplinas y grupos.
     */
    public function test_socio_puede_inscribirse_en_multiples_disciplinas(): void
    {
        $socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Martín',
            'apellido' => 'Benítez',
            'dni' => '37444555',
            'sexo' => 'M',
            'correo' => 'martin@test.com',
            'token' => 'TOKEN777',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        $crossfit = Disciplina::create(['nombre' => 'Crossfit', 'estado' => 'activa']);
        $spinning = Disciplina::create(['nombre' => 'Spinning', 'estado' => 'activa']);

        $grupoCrossfit = Grupo::create([
            'disciplina_id' => $crossfit->id,
            'nombre' => 'Crossfit Mañana',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        $grupoSpinning = Grupo::create([
            'disciplina_id' => $spinning->id,
            'nombre' => 'Spinning Tarde',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        // Inscripción 1: Crossfit
        Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupoCrossfit->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'aprobada',
            'aprobado_por' => $this->duena->id,
        ]);

        // Inscripción 2: Spinning
        Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupoSpinning->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'aprobada',
            'aprobado_por' => $this->duena->id,
        ]);

        $this->assertCount(2, $socio->fresh()->inscripciones);
        $this->assertCount(2, $socio->fresh()->grupos);
        $this->assertTrue($socio->grupos->contains($grupoCrossfit));
        $this->assertTrue($socio->grupos->contains($grupoSpinning));
    }

    /**
     * Test: Capacidad máxima del grupo (15 alumnos) y rechazo / detección de falta de cupo para el alumno 16.
     */
    public function test_grupo_capacidad_maxima_quince_alumnos_y_control_de_cupo(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Funcional', 'estado' => 'activa']);
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => 'Funcional Turno Noche',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        $this->assertEquals(15, $grupo->cupo_maximo);

        // Inscribir 15 alumnos
        for ($i = 1; $i <= 15; $i++) {
            $socio = Socio::create([
                'membresia_id' => $this->membresia->id,
                'nombre' => "Alumno {$i}",
                'apellido' => 'Prueba',
                'dni' => "500000{$i}",
                'sexo' => 'M',
                'correo' => "alumno{$i}@test.com",
                'token' => "TOK{$i}",
                'fecha_alta' => now()->toDateString(),
                'estado' => 'activo',
            ]);

            $this->assertTrue($grupo->tieneCupoDisponible(), "El grupo debería tener cupo para el alumno {$i}");

            Inscripcion::create([
                'socio_id' => $socio->id,
                'grupo_id' => $grupo->id,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => 'aprobada',
            ]);
        }

        $this->assertEquals(15, $grupo->alumnosInscriptosCount());
        // El cupo está al 100%: no debe haber vacantes disponibles
        $this->assertFalse($grupo->tieneCupoDisponible(), 'El grupo con 15 alumnos inscriptos ya no debe tener cupo disponible');

        // Alumno 16 entra en Lista de Espera
        $alumno16 = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Alumno 16',
            'apellido' => 'En Espera',
            'dni' => '50000016',
            'sexo' => 'M',
            'correo' => 'alumno16@test.com',
            'token' => 'TOK16',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        $espera = ListaEspera::create([
            'socio_id' => $alumno16->id,
            'grupo_id' => $grupo->id,
            'posicion' => 1,
            'estado' => 'esperando',
        ]);

        $this->assertDatabaseHas('listas_espera', [
            'socio_id' => $alumno16->id,
            'grupo_id' => $grupo->id,
            'posicion' => 1,
            'estado' => 'esperando',
        ]);
        $this->assertCount(1, $grupo->fresh()->listasEspera);
    }

    /**
     * Test: Horarios de grupo con varios días y verificación de superposiciones sin cadenas separadas por coma.
     */
    public function test_horarios_de_grupo_y_deteccion_de_superposicion(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Yoga', 'estado' => 'activa']);
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => 'Yoga Intermedio',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        // Grupo con clases Lunes, Miércoles y Viernes de 18:00 a 19:30
        $horarioLunes = GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => 'lunes',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:30:00',
        ]);

        $horarioMiercoles = GrupoHorario::create([
            'grupo_id' => $grupo->id,
            'dia_semana' => 'miercoles',
            'hora_inicio' => '18:00:00',
            'hora_fin' => '19:30:00',
        ]);

        $this->assertCount(2, $grupo->fresh()->horarios);

        // Verificación de superposición exacta: Lunes 18:30 a 19:00 choca con horarioLunes
        $this->assertTrue($horarioLunes->seSuperponeCon('lunes', '18:30:00', '19:00:00'));

        // Horario en otro día (Martes 18:00 a 19:30) no se superpone
        $this->assertFalse($horarioLunes->seSuperponeCon('martes', '18:00:00', '19:30:00'));

        // Horario el mismo día pero más temprano (Lunes 16:00 a 17:30) no se superpone
        $this->assertFalse($horarioLunes->seSuperponeCon('lunes', '16:00:00', '17:30:00'));
    }

    /**
     * Test: Pagos con detalles vinculados a inscripciones y períodos de cobertura.
     */
    public function test_pago_con_detalles_vincula_inscripcion_y_periodo_de_cobertura(): void
    {
        $socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Camila',
            'apellido' => 'Sosa',
            'dni' => '42333444',
            'sexo' => 'F',
            'correo' => 'camila@test.com',
            'token' => 'TOK_CAMILA',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        $disciplina = Disciplina::create(['nombre' => 'Pilates Reformer', 'estado' => 'activa']);
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => 'Pilates Mañana',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);

        $inscripcion = Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'aprobada',
        ]);

        $pago = Pago::create([
            'socio_id' => $socio->id,
            'fecha_pago' => now()->toDateString(),
            'importe' => 20000.00,
            'metodo_pago' => 'transferencia',
        ]);

        $detalle = PagoDetalle::create([
            'pago_id' => $pago->id,
            'inscripcion_id' => $inscripcion->id,
            'concepto' => 'Cuota Mensual Pilates Noviembre',
            'fecha_inicio_cobertura' => now()->startOfMonth()->toDateString(),
            'fecha_fin_cobertura' => now()->endOfMonth()->toDateString(),
            'subtotal' => 20000.00,
        ]);

        $this->assertCount(1, $pago->fresh()->detalles);
        $this->assertTrue($inscripcion->fresh()->tieneCoberturaVigente(now()->toDateString()));
        $this->assertFalse($inscripcion->fresh()->tieneCoberturaVigente(now()->addMonths(2)->toDateString()));
    }

    /**
     * Test: Separación entre permanencia física (ingreso/salida del gym) y asistencias a clases.
     */
    public function test_separacion_entre_permanencia_fisica_y_asistencias_a_clases(): void
    {
        $socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Esteban',
            'apellido' => 'Ríos',
            'dni' => '33555666',
            'sexo' => 'M',
            'correo' => 'esteban@test.com',
            'token' => 'TOK_ESTEBAN',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        $disc1 = Disciplina::create(['nombre' => 'Spinning Pro', 'estado' => 'activa']);
        $disc2 = Disciplina::create(['nombre' => 'Stretching', 'estado' => 'activa']);

        $grupo1 = Grupo::create(['disciplina_id' => $disc1->id, 'nombre' => 'Spinning 18hs', 'cupo_maximo' => 15]);
        $grupo2 = Grupo::create(['disciplina_id' => $disc2->id, 'nombre' => 'Stretching 19hs', 'cupo_maximo' => 15]);

        // 1. Ingreso físico al gimnasio a las 17:50
        $asistenciaFisica = Asistencia::create([
            'socio_id' => $socio->id,
            'fecha' => now()->toDateString(),
            'hora_ingreso' => '17:50:00',
            'metodo_acceso' => 'pin_terminal',
            'dispositivo_origen' => 'terminal_principal',
        ]);

        // 2. Asiste a la primera clase: Spinning (18:00)
        $clase1 = AsistenciaClase::create([
            'asistencia_id' => $asistenciaFisica->id,
            'socio_id' => $socio->id,
            'grupo_id' => $grupo1->id,
            'fecha' => now()->toDateString(),
            'hora_marcado' => '18:02:00',
            'estado' => 'presente',
            'marcado_por' => $this->recepcionista->id,
        ]);

        // 3. Asiste a la segunda clase: Stretching (19:00)
        $clase2 = AsistenciaClase::create([
            'asistencia_id' => $asistenciaFisica->id,
            'socio_id' => $socio->id,
            'grupo_id' => $grupo2->id,
            'fecha' => now()->toDateString(),
            'hora_marcado' => '19:05:00',
            'estado' => 'presente',
            'marcado_por' => $this->recepcionista->id,
        ]);

        // 4. Salida física del gimnasio a las 20:00 (130 minutos totales)
        $asistenciaFisica->update([
            'hora_salida' => '20:00:00',
            'tiempo_permanencia' => 130,
        ]);

        $this->assertCount(2, $asistenciaFisica->fresh()->asistenciasClases);
        $this->assertEquals(130, $asistenciaFisica->tiempo_permanencia);
        $this->assertEquals('pin_terminal', $asistenciaFisica->metodo_acceso);
        $this->assertEquals($grupo1->id, $clase1->grupo_id);
        $this->assertEquals($grupo2->id, $clase2->grupo_id);
    }

    /**
     * Test: Solicitudes entre recepcionista y dueña con estados definidos.
     */
    public function test_flujo_solicitudes_entre_recepcionista_y_duena(): void
    {
        $socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Nadia',
            'apellido' => 'Díaz',
            'dni' => '38111999',
            'sexo' => 'F',
            'correo' => 'nadia@test.com',
            'token' => 'TOK_NADIA',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        // Recepcionista emite solicitud de excepción médica
        $solicitud = Solicitud::create([
            'socio_id' => $socio->id,
            'solicitante_id' => $this->recepcionista->id,
            'tipo' => 'excepcion_medica',
            'descripcion' => 'Socio presenta certificado médico digital en trámite',
            'estado' => 'pendiente',
        ]);

        $this->assertEquals('pendiente', $solicitud->estado);
        $this->assertEquals($this->recepcionista->id, $solicitud->solicitante->id);

        // Dueña aprueba la solicitud
        $solicitud->update([
            'estado' => 'aprobada',
            'revisado_por' => $this->duena->id,
            'respuesta_duena' => 'Aprobado provisorio por 7 días',
            'fecha_decision' => now(),
        ]);

        $this->assertEquals('aprobada', $solicitud->fresh()->estado);
        $this->assertEquals($this->duena->id, $solicitud->fresh()->revisadoPor->id);
    }

    /**
     * Test: Auditoría registra eventos administrativos y sanitiza PINs y datos sensibles.
     */
    public function test_auditoria_registra_operaciones_y_oculta_pin(): void
    {
        $socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Lucas',
            'apellido' => 'Paz',
            'dni' => '36777888',
            'sexo' => 'M',
            'correo' => 'lucas@test.com',
            'token' => 'TOK_LUCAS',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        $auditoria = Auditoria::registrar(
            tipoEvento: 'seguridad',
            accion: 'emision_credenciales',
            entidad: $socio,
            datosAntes: ['pin' => null, 'estado_credencial' => 'sin_emitir'],
            datosDespues: ['pin' => '654321', 'estado_credencial' => 'activo', 'diagnostico' => 'Apto para deporte'],
            userId: $this->duena->id
        );

        $this->assertDatabaseHas('auditorias', [
            'id' => $auditoria->id,
            'tipo_evento' => 'seguridad',
            'accion' => 'emision_credenciales',
        ]);

        // El PIN y diagnóstico NUNCA deben guardarse en texto claro en la auditoría
        $this->assertStringNotContainsString('654321', json_encode($auditoria->datos_despues));
        $this->assertEquals('***OCULTO_POR_SEGURIDAD***', $auditoria->datos_despues['pin']);
        $this->assertEquals('***OCULTO_POR_SEGURIDAD***', $auditoria->datos_despues['diagnostico']);
    }

    /**
     * Test: Habilitación de primera emisión de credenciales exige los 3 requisitos:
     * 1. Inscripción aprobada.
     * 2. Cuota abonada.
     * 3. Decisión médica cumplida.
     */
    public function test_primera_emision_credenciales_exige_tres_requisitos(): void
    {
        $socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Romina',
            'apellido' => 'Méndez',
            'dni' => '40999000',
            'sexo' => 'F',
            'correo' => 'romina@test.com',
            'token' => 'TOK_ROMINA',
            'decision_medica' => 'pendiente',
            'fecha_alta' => now()->toDateString(),
            'fecha_vencimiento' => null, // Cuota no abonada
            'estado' => 'activo',
        ]);

        $disciplina = Disciplina::create(['nombre' => 'Funcional', 'estado' => 'activa']);
        $grupo = Grupo::create(['disciplina_id' => $disciplina->id, 'nombre' => 'Funcional 17hs', 'cupo_maximo' => 15]);

        // Caso A: Sin inscripción, sin pago, decisión médica pendiente
        $this->assertFalse($socio->puedeEmitirCredenciales());

        // Caso B: Agregamos inscripción aprobada
        Inscripcion::create([
            'socio_id' => $socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'aprobada',
        ]);
        $this->assertFalse($socio->fresh()->puedeEmitirCredenciales()); // Sigue faltando cuota y apto

        // Caso C: Se registra pago de cuota (vence el próximo mes)
        $socio->update(['fecha_vencimiento' => now()->addMonth()->toDateString()]);
        $this->assertFalse($socio->fresh()->puedeEmitirCredenciales()); // Sigue faltando decisión médica

        // Caso D: Dueña resuelve decisión médica como 'no_requerido'
        $socio->update(['decision_medica' => 'no_requerido']);
        // Ahora los 3 requisitos están cumplidos -> HABILITADO
        $this->assertTrue($socio->fresh()->puedeEmitirCredenciales());
    }
}
