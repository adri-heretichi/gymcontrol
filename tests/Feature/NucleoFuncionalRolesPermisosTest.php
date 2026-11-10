<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class NucleoFuncionalRolesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected User $duena;
    protected User $recepcionista;
    protected Membresia $membresia;
    protected Socio $socio;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Dueña del gimnasio (rol admin)
        $this->duena = User::factory()->create([
            'name' => 'Adriana Dueña',
            'email' => 'duena@fenixgym.com',
            'rol' => 'admin',
        ]);

        // 2. Recepcionista de turno
        $this->recepcionista = User::factory()->create([
            'name' => 'Recepcionista Turno Tarde',
            'email' => 'recepcion@fenixgym.com',
            'rol' => 'recepcionista',
        ]);

        // 3. Membresía activa
        $this->membresia = Membresia::create([
            'nombre' => 'Pase Mensual General',
            'precio' => 15000.00,
            'horas_mensuales' => null,
            'estado' => 'activo',
        ]);

        // 4. Socio registrado
        $this->socio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Matías',
            'apellido' => 'Sosa',
            'dni' => '39111222',
            'sexo' => 'M',
            'correo' => 'matias@test.com',
            'token' => 'TOK_MATIAS',
            'pin' => null,
            'qr_token' => null,
            'estado_credencial' => 'sin_emitir',
            'decision_medica' => 'pendiente',
            'fecha_alta' => now()->toDateString(),
            'estado' => 'activo',
        ]);
    }

    /**
     * Requisito 1: La dueña puede acceder a sus operaciones administrativas completas.
     */
    public function test_duena_puede_acceder_a_operaciones_administrativas(): void
    {
        $this->actingAs($this->duena);

        $this->assertTrue($this->duena->esDuena());
        $this->assertFalse($this->duena->esRecepcionista());

        // Comprobación de Gates
        $this->assertTrue(Gate::allows('administrar-operadores'));
        $this->assertTrue(Gate::allows('gestionar-disciplinas'));
        $this->assertTrue(Gate::allows('gestionar-grupos'));
        $this->assertTrue(Gate::allows('aprobar-inscripciones'));
        $this->assertTrue(Gate::allows('gestionar-decision-medica'));
        $this->assertTrue(Gate::allows('resolver-solicitudes'));
        $this->assertTrue(Gate::allows('ver-auditoria'));
        $this->assertTrue(Gate::allows('ver-recaudacion'));

        // Rutas web
        $this->get('/operadores')->assertStatus(200);
        $this->get('/operadores/crear')->assertStatus(200);
        $this->get('/membresias/crear')->assertStatus(200);
        $this->get(route('reportes.pagos'))->assertStatus(200);
    }

    /**
     * Requisito 2: La recepcionista puede registrar nuevos socios desde la interfaz existente.
     */
    public function test_recepcionista_puede_registrar_socios_desde_interfaz(): void
    {
        $this->actingAs($this->recepcionista);

        $this->assertTrue($this->recepcionista->esRecepcionista());
        $this->assertFalse($this->recepcionista->esDuena());

        // Acceso a la ruta de creación de socios
        $this->get('/socios/crear')->assertStatus(200);

        // Registro funcional mediante el componente Livewire CrearSocio
        Livewire::test(\App\Livewire\Socios\CrearSocio::class)
            ->set('nombre', 'Federico')
            ->set('apellido', 'Navarro')
            ->set('dni', '42555666')
            ->set('sexo', 'M')
            ->set('correo', 'federico@test.com')
            ->set('celular', '3704112233')
            ->set('membresia_id', $this->membresia->id)
            ->set('fecha_alta', now()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors();

        $creado = Socio::where('dni', '42555666')->first();
        $this->assertNotNull($creado);
        $this->assertEquals('Federico', $creado->nombre);
        $this->assertEquals('sin_emitir', $creado->estado_credencial);
        $this->assertEquals('pendiente', $creado->decision_medica);
        $this->assertNull($creado->pin);
        $this->assertNull($creado->qr_token);
    }

    /**
     * Requisito 3: La recepcionista puede consultar los socios permitidos para su trabajo diario.
     */
    public function test_recepcionista_puede_consultar_socios(): void
    {
        $this->actingAs($this->recepcionista);

        $this->get('/socios')->assertStatus(200);
        $this->get('/socios/' . $this->socio->id)->assertStatus(200);
    }

    /**
     * Requisito 4: La recepcionista no puede aprobar inscripciones en el servidor.
     */
    public function test_recepcionista_no_puede_aprobar_inscripciones(): void
    {
        $disciplina = Disciplina::create(['nombre' => 'Crossfit Fénix', 'estado' => 'activa']);
        $grupo = Grupo::create(['disciplina_id' => $disciplina->id, 'nombre' => 'Turno 19hs', 'cupo_maximo' => 15]);

        $inscripcion = Inscripcion::create([
            'socio_id' => $this->socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'pendiente',
        ]);

        $this->actingAs($this->recepcionista);

        // Intento por Policy
        $this->assertFalse(Gate::forUser($this->recepcionista)->allows('aprobar', $inscripcion));
        $this->assertFalse(Gate::forUser($this->recepcionista)->allows('aprobar-inscripciones'));

        // Intento de ejecución directa de método de negocio
        try {
            if (!Gate::forUser($this->recepcionista)->allows('aprobar', $inscripcion)) {
                throw new AuthorizationException('No autorizado');
            }
            $inscripcion->update(['estado' => 'aprobada']);
            $this->fail('La recepcionista no debió poder aprobar la inscripción');
        } catch (AuthorizationException $e) {
            $this->assertEquals('No autorizado', $e->getMessage());
        }

        // El estado sigue pendiente
        $this->assertEquals('pendiente', $inscripcion->fresh()->estado);
    }

    /**
     * Requisito 5: La recepcionista no puede modificar la decisión médica de un socio.
     */
    public function test_recepcionista_no_puede_modificar_decision_medica(): void
    {
        $this->actingAs($this->recepcionista);

        $this->assertFalse(Gate::forUser($this->recepcionista)->allows('gestionarDecisionMedica', $this->socio));
        $this->assertFalse(Gate::forUser($this->recepcionista)->allows('gestionar-decision-medica'));

        // El estado debe permanecer en pendiente
        $this->assertEquals('pendiente', $this->socio->fresh()->decision_medica);
    }

    /**
     * Requisito 6: La recepcionista no puede modificar precios administrativos de membresías.
     */
    public function test_recepcionista_no_puede_modificar_precios_de_membresias(): void
    {
        $this->actingAs($this->recepcionista);

        // Ruta de edición de membresías denegada (403)
        $this->get('/membresias/' . $this->membresia->id . '/editar')->assertStatus(403);
        $this->get('/membresias/crear')->assertStatus(403);

        $this->assertFalse(Gate::forUser($this->recepcionista)->allows('update', $this->membresia));
        $this->assertFalse(Gate::forUser($this->recepcionista)->allows('create', Membresia::class));
    }

    /**
     * Requisito 7: La recepcionista no puede consultar la recaudación general reservada.
     */
    public function test_recepcionista_no_puede_consultar_recaudacion_general(): void
    {
        $this->actingAs($this->recepcionista);

        // Reporte PDF de recaudación denegado
        $this->get(route('reportes.pagos'))->assertStatus(403);

        // Endpoint API de recaudación denegado
        $token = $this->recepcionista->createToken('recep_token')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.pagos.recaudacion'));
        $response->assertStatus(403);
    }

    /**
     * Requisito 8: La recepcionista no puede administrar operadores ni sus permisos.
     */
    public function test_recepcionista_no_puede_administrar_operadores(): void
    {
        $this->actingAs($this->recepcionista);

        $this->get('/operadores')->assertStatus(403);
        $this->get('/operadores/crear')->assertStatus(403);
        $this->get('/operadores/' . $this->duena->id . '/editar')->assertStatus(403);

        $this->assertFalse(Gate::forUser($this->recepcionista)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($this->recepcionista)->allows('administrar-operadores'));
    }

    /**
     * Requisito 9: Un usuario invitado (sin autenticación) no puede acceder a operaciones administrativas.
     */
    public function test_invitado_sin_autenticacion_es_rechazado(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/socios')->assertRedirect(route('login'));
        $this->get('/socios/crear')->assertRedirect(route('login'));
        $this->get('/operadores')->assertRedirect(route('login'));
        $this->get('/membresias/crear')->assertRedirect(route('login'));
    }

    /**
     * Requisito 10: Una petición manipulada no puede eludir autorizaciones ni cambiar campos protegidos.
     */
    public function test_peticion_manipulada_no_puede_eludir_autorizaciones(): void
    {
        $this->actingAs($this->recepcionista);

        // Recepcionista intenta acceder a editar socio y enviar cambio de estado o decisión médica
        $this->get('/socios/' . $this->socio->id . '/editar')->assertStatus(403);

        // Si intenta invocar el componente EditarSocio directamente por Livewire
        Livewire::test(\App\Livewire\Socios\EditarSocio::class, ['socio' => $this->socio])
            ->assertStatus(403);
    }

    /**
     * Requisito 11: Las rutas API mantienen sus protecciones y roles.
     */
    public function test_rutas_api_mantienen_sus_protecciones(): void
    {
        $recepToken = $this->recepcionista->createToken('api_test_recep')->plainTextToken;

        // Lectura permitida
        $this->withHeader('Authorization', 'Bearer ' . $recepToken)
            ->getJson(route('api.socios.index'))
            ->assertStatus(200);

        // Modificación y eliminación prohibidas para recepción (403)
        $this->withHeader('Authorization', 'Bearer ' . $recepToken)
            ->deleteJson(route('api.socios.destroy', $this->socio->id))
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $recepToken)
            ->postJson(route('api.membresias.store'), [
                'nombre' => 'Hack Price',
                'precio' => 1.00,
            ])
            ->assertStatus(403);
    }

    /**
     * Requisito 12: La dueña puede realizar las operaciones exclusivas (resolver solicitudes, decisión médica, credenciales).
     */
    public function test_duena_puede_resolver_solicitudes_y_decision_medica(): void
    {
        $solicitud = Solicitud::create([
            'socio_id' => $this->socio->id,
            'solicitante_id' => $this->recepcionista->id,
            'tipo' => 'excepcion_medica',
            'descripcion' => 'Certificado en trámite ante cardiólogo',
            'estado' => 'pendiente',
        ]);

        $this->actingAs($this->duena);

        // Dueña está autorizada por Policy
        $this->assertTrue(Gate::forUser($this->duena)->allows('resolver', $solicitud));
        $this->assertTrue(Gate::forUser($this->duena)->allows('gestionarDecisionMedica', $this->socio));

        // Dueña resuelve solicitud
        $solicitud->update([
            'estado' => 'aprobada',
            'revisado_por' => $this->duena->id,
            'respuesta_duena' => 'Aprobado provisorio',
            'fecha_decision' => now(),
        ]);
        $this->assertEquals('aprobada', $solicitud->fresh()->estado);

        // Dueña define decisión médica individual
        $this->socio->update([
            'decision_medica' => 'no_requerido',
            'decision_medica_at' => now(),
            'decision_medica_por' => $this->duena->id,
            'decision_medica_obs' => 'Excepción médica autorizada en solicitud #' . $solicitud->id,
        ]);
        $this->assertEquals('no_requerido', $this->socio->fresh()->decision_medica);
        $this->assertTrue($this->socio->fresh()->cumpleRequisitoMedico());
    }

    /**
     * Requisito 13: Registrar un socio nuevo no emite automáticamente credenciales (QR ni PIN).
     */
    public function test_alta_socio_no_emite_credenciales_automaticamente(): void
    {
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\Socios\CrearSocio::class)
            ->set('nombre', 'Gabriel')
            ->set('apellido', 'Correa')
            ->set('dni', '35999888')
            ->set('sexo', 'M')
            ->set('correo', 'gabriel@test.com')
            ->set('membresia_id', $this->membresia->id)
            ->set('fecha_alta', now()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors();

        $socioCreado = Socio::where('dni', '35999888')->first();
        $this->assertNotNull($socioCreado);

        // Comprobación de que no se emitieron credenciales
        $this->assertNull($socioCreado->pin, 'El PIN no debe ser generado al crear el socio');
        $this->assertNull($socioCreado->qr_token, 'El token QR no debe ser generado al crear el socio');
        $this->assertEquals('sin_emitir', $socioCreado->estado_credencial);
        $this->assertEquals('pendiente', $socioCreado->decision_medica);
        $this->assertNull($socioCreado->credenciales_emitidas_at);

        // Comprobación de que NO se generó archivo SVG físico
        $qrPath = storage_path('app/private/qrs/qr_' . $socioCreado->id . '.svg');
        $this->assertFileDoesNotExist($qrPath, 'No debe generarse archivo QR SVG en el alta');
    }

    /**
     * Requisito 14: Los usuarios anteriores siguen funcionando con sus roles y helpers.
     */
    public function test_usuarios_anteriores_mantienen_roles_y_helpers(): void
    {
        $adminExistente = User::where('rol', 'admin')->first() ?? $this->duena;
        $recepExistente = User::where('rol', 'recepcionista')->first() ?? $this->recepcionista;

        $this->assertEquals('admin', $adminExistente->rol);
        $this->assertTrue($adminExistente->esDuena());
        $this->assertFalse($adminExistente->esRecepcionista());

        $this->assertEquals('recepcionista', $recepExistente->rol);
        $this->assertTrue($recepExistente->esRecepcionista());
        $this->assertFalse($recepExistente->esDuena());
    }
}
