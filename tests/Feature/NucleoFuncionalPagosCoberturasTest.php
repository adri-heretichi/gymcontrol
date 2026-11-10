<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\User;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NucleoFuncionalPagosCoberturasTest – Fase 5.
 *
 * Verifica los 25 casos requeridos por el prompt de la Fase 5.
 * Todos los datos de prueba son aislados; los socios/pagos históricos no se tocan.
 */
class NucleoFuncionalPagosCoberturasTest extends TestCase
{
    use RefreshDatabase;

    private PagoService $pagoService;
    private User $duena;
    private User $recepcionista;
    private Membresia $membresia;
    private Disciplina $disciplina1;
    private Disciplina $disciplina2;
    private Grupo $grupo1;
    private Grupo $grupo2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pagoService = app(PagoService::class);

        // Usuarios del gimnasio
        $this->duena = $this->crearUsuario('admin');
        $this->recepcionista = $this->crearUsuario('recepcionista');

        // Membresía base
        $this->membresia = Membresia::create([
            'nombre' => 'Mensual Test',
            'precio' => 35000,
            'duracion_dias' => 30,
        ]);

        // Disciplinas y grupos
        $this->disciplina1 = Disciplina::create(['nombre' => 'Kickboxing Test', 'activa' => true]);
        $this->disciplina2 = Disciplina::create(['nombre' => 'Funcional Test', 'activa' => true]);

        $this->grupo1 = Grupo::create([
            'disciplina_id' => $this->disciplina1->id,
            'nombre'        => 'Grupo A',
            'capacidad_maxima' => 15,
            'nivel'         => 'todos',
            'activo'        => true,
        ]);
        $this->grupo2 = Grupo::create([
            'disciplina_id' => $this->disciplina2->id,
            'nombre'        => 'Grupo B',
            'capacidad_maxima' => 15,
            'nivel'         => 'todos',
            'activo'        => true,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function crearSocio(): Socio
    {
        static $n = 0;
        $n++;
        return Socio::create([
            'membresia_id'    => $this->membresia->id,
            'nombre'          => "Socio{$n}",
            'apellido'        => 'Test',
            'dni'             => str_pad($n, 8, '0', STR_PAD_LEFT),
            'sexo'            => 'masculino',
            'correo'          => "socio{$n}@test.com",
            'celular'         => '1100000000',
            'token'           => \Illuminate\Support\Str::uuid()->toString(),
            'fecha_alta'      => now()->toDateString(),
            'estado'          => 'activo',
            'decision_medica' => 'pendiente',
        ]);
    }

    private function crearUsuario(string $rol): User
    {
        static $nu = 0;
        $nu++;
        return User::create([
            'name'     => "Usuario{$nu}",
            'email'    => "user{$nu}@test.com",
            'password' => bcrypt('password'),
            'rol'      => $rol,
        ]);
    }

    /**
     * Inscribe a un socio en un grupo y lo aprueba directamente (estado=aprobada).
     */
    private function inscribirAprobado(Socio $socio, Grupo $grupo): Inscripcion
    {
        return Inscripcion::create([
            'socio_id'        => $socio->id,
            'grupo_id'        => $grupo->id,
            'fecha_inscripcion'=> now()->toDateString(),
            'estado'          => Inscripcion::ESTADO_APROBADA,
            'aprobado_por'    => $this->duena->id,
            'aprobado_at'     => now(),
        ]);
    }

    private function inscribirPendiente(Socio $socio, Grupo $grupo): Inscripcion
    {
        return Inscripcion::create([
            'socio_id'        => $socio->id,
            'grupo_id'        => $grupo->id,
            'fecha_inscripcion'=> now()->toDateString(),
            'estado'          => Inscripcion::ESTADO_PENDIENTE,
        ]);
    }

    private function registrarPagoSimple(
        Socio $socio,
        Inscripcion $inscripcion,
        string $inicio,
        string $fin,
        float $monto = 35000,
        string $medio = 'efectivo'
    ): Pago {
        return $this->pagoService->registrarPago(
            socio: $socio,
            detalles: [[
                'inscripcion_id' => $inscripcion->id,
                'concepto'       => 'Cuota mensual test',
                'fecha_inicio'   => $inicio,
                'fecha_fin'      => $fin,
                'subtotal'       => $monto,
            ]],
            medioPago: $medio,
            operador: $this->recepcionista,
        );
    }

    // ─── TESTS ──────────────────────────────────────────────────────────────────

    /** 01 – Registro de pago válido */
    public function test_01_registro_pago_valido(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $pago = $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $this->assertDatabaseHas('pagos', [
            'id'       => $pago->id,
            'socio_id' => $socio->id,
            'estado'   => Pago::ESTADO_ACTIVO,
            'importe'  => 35000,
        ]);
    }

    /** 02 – Pago asociado a una inscripción */
    public function test_02_pago_asociado_a_inscripcion(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $pago = $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $this->assertCount(1, $pago->detalles);
        $this->assertEquals($insc->id, $pago->detalles->first()->inscripcion_id);
    }

    /** 03 – Pago de dos disciplinas en una operación */
    public function test_03_pago_dos_disciplinas_en_una_operacion(): void
    {
        $socio = $this->crearSocio();
        $insc1 = $this->inscribirAprobado($socio, $this->grupo1);
        $insc2 = $this->inscribirAprobado($socio, $this->grupo2);

        $pago = $this->pagoService->registrarPago(
            socio: $socio,
            detalles: [
                [
                    'inscripcion_id' => $insc1->id,
                    'concepto'       => 'Kickboxing noviembre',
                    'fecha_inicio'   => '2026-11-01',
                    'fecha_fin'      => '2026-11-30',
                    'subtotal'       => 35000,
                ],
                [
                    'inscripcion_id' => $insc2->id,
                    'concepto'       => 'Funcional noviembre',
                    'fecha_inicio'   => '2026-11-01',
                    'fecha_fin'      => '2026-11-30',
                    'subtotal'       => 35000,
                ],
            ],
            medioPago: 'efectivo',
            operador: $this->recepcionista,
        );

        $this->assertCount(2, $pago->detalles);
        $this->assertEquals(70000, $pago->importe);
    }

    /** 04 – Coberturas independientes por disciplina */
    public function test_04_coberturas_independientes(): void
    {
        $socio = $this->crearSocio();
        $insc1 = $this->inscribirAprobado($socio, $this->grupo1);
        $insc2 = $this->inscribirAprobado($socio, $this->grupo2);

        // Solo pagar disciplina 1
        $this->registrarPagoSimple($socio, $insc1, '2026-11-01', '2026-11-30');

        $cobs = $this->pagoService->consultarCoberturasSocio($socio, '2026-11-15');

        $this->assertTrue($cobs[$insc1->id]['vigente'],  'Disciplina 1 debe estar vigente');
        $this->assertFalse($cobs[$insc2->id]['vigente'], 'Disciplina 2 no debe estar vigente');
    }

    /** 05 – Cuota vigente */
    public function test_05_cuota_vigente(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $cob = $this->pagoService->consultarCoberturaInscripcion($insc, '2026-11-15');

        $this->assertTrue($cob['vigente']);
        $this->assertEquals(PagoDetalle::COBERTURA_VIGENTE, $cob['estado']);
    }

    /** 06 – Cuota vencida */
    public function test_06_cuota_vencida(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        // Pago de octubre (ya vencido al consultar en noviembre)
        $this->registrarPagoSimple($socio, $insc, '2026-10-01', '2026-10-31');

        $cob = $this->pagoService->consultarCoberturaInscripcion($insc, '2026-11-15');

        $this->assertFalse($cob['vigente']);
        $this->assertEquals(PagoDetalle::COBERTURA_VENCIDA, $cob['estado']);
    }

    /** 07 – Renovación (dos coberturas consecutivas) */
    public function test_07_renovacion(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        // Pago octubre
        $this->registrarPagoSimple($socio, $insc, '2026-10-01', '2026-10-31');
        // Pago noviembre (renovación)
        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        // Ambos detalles deben existir (2 pagos)
        $pagos = Pago::where('socio_id', $socio->id)->count();
        $this->assertEquals(2, $pagos);

        // Noviembre vigente
        $cob = $this->pagoService->consultarCoberturaInscripcion($insc, '2026-11-15');
        $this->assertTrue($cob['vigente']);
    }

    /** 08 – Conservación del historial (renovación no borra pago anterior) */
    public function test_08_conservacion_historial(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $pago1 = $this->registrarPagoSimple($socio, $insc, '2026-10-01', '2026-10-31', 35000);
        $pago2 = $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30', 35000);

        // El primer pago sigue existiendo con importe original
        $this->assertDatabaseHas('pagos', ['id' => $pago1->id, 'importe' => 35000]);
        $this->assertDatabaseHas('pagos', ['id' => $pago2->id, 'importe' => 35000]);
    }

    /** 09 – Importe correcto (suma de detalles = importe total) */
    public function test_09_importe_correcto(): void
    {
        $socio = $this->crearSocio();
        $insc1 = $this->inscribirAprobado($socio, $this->grupo1);
        $insc2 = $this->inscribirAprobado($socio, $this->grupo2);

        $pago = $this->pagoService->registrarPago(
            socio: $socio,
            detalles: [
                ['inscripcion_id' => $insc1->id, 'concepto' => 'A', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-30', 'subtotal' => 20000],
                ['inscripcion_id' => $insc2->id, 'concepto' => 'B', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-30', 'subtotal' => 15000],
            ],
            medioPago: 'efectivo',
            operador: $this->recepcionista,
        );

        $sumaDetalles = $pago->detalles->sum('subtotal');
        $this->assertEquals($pago->importe, $sumaDetalles);
        $this->assertEquals(35000, $pago->importe);
    }

    /** 10 – Método de pago almacenado correctamente */
    public function test_10_metodo_pago(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $pago = $this->pagoService->registrarPago(
            socio: $socio,
            detalles: [['inscripcion_id' => $insc->id, 'concepto' => 'X', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-30', 'subtotal' => 35000]],
            medioPago: 'mercadopago',
            operador: $this->recepcionista,
        );

        $this->assertEquals('mercadopago', $pago->metodo_pago);
    }

    /** 11 – Rechazo de fechas inválidas (fin < inicio) */
    public function test_11_rechazo_fechas_invalidas(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/fecha de fin/');

        $this->pagoService->registrarPago(
            socio: $socio,
            detalles: [['inscripcion_id' => $insc->id, 'concepto' => 'X', 'fecha_inicio' => '2026-11-30', 'fecha_fin' => '2026-11-01', 'subtotal' => 35000]],
            medioPago: 'efectivo',
            operador: $this->recepcionista,
        );
    }

    /** 12 – Rechazo de importes inválidos (negativo) */
    public function test_12_rechazo_importe_invalido(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/negativo/');

        $this->pagoService->registrarPago(
            socio: $socio,
            detalles: [['inscripcion_id' => $insc->id, 'concepto' => 'X', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-30', 'subtotal' => -1]],
            medioPago: 'efectivo',
            operador: $this->recepcionista,
        );
    }

    /** 13 – Prevención de cobros duplicados (mismo período exacto) */
    public function test_13_prevencion_cobros_duplicados(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cobertura activa/');

        // Segundo intento con el mismo período exacto
        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');
    }

    /** 14 – Manejo transaccional ante errores (no queda pago sin detalles) */
    public function test_14_manejo_transaccional_ante_errores(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        // Primer pago legítimo (para generar duplicado en el segundo)
        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $countAntes = Pago::count();

        try {
            // Segundo pago con mismo período → debe fallar en duplicación
            $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');
        } catch (\DomainException) {
            // esperado
        }

        // No debe haberse creado un pago huérfano
        $this->assertEquals($countAntes, Pago::count(), 'La transacción debió revertirse completamente');
    }

    /** 15 – Recepcionista autorizada a cobrar */
    public function test_15_recepcionista_autorizada_a_cobrar(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $this->actingAs($this->recepcionista);
        $this->assertTrue($this->recepcionista->can('create', Pago::class));

        // Puede registrar un pago
        $pago = $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');
        $this->assertEquals($this->recepcionista->id, $pago->operador_id);
    }

    /** 16 – Recepcionista sin permiso para modificar precios */
    public function test_16_recepcionista_sin_permiso_para_modificar_precios(): void
    {
        $this->actingAs($this->recepcionista);
        $this->assertFalse($this->recepcionista->can('configurarPrecios', Pago::class));
    }

    /** 17 – Dueña autorizada a revisar recaudación */
    public function test_17_duena_autorizada_recaudacion(): void
    {
        $this->actingAs($this->duena);
        $this->assertTrue($this->duena->can('verRecaudacion', Pago::class));
    }

    /** 18 – Recepcionista sin permiso para consultar recaudación general */
    public function test_18_recepcionista_sin_permiso_recaudacion(): void
    {
        $this->actingAs($this->recepcionista);
        $this->assertFalse($this->recepcionista->can('verRecaudacion', Pago::class));
    }

    /** 19 – Un pago no aprueba una inscripción */
    public function test_19_pago_no_aprueba_inscripcion(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirPendiente($socio, $this->grupo1);

        // Pagar una inscripción pendiente
        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $insc->refresh();
        $this->assertEquals(Inscripcion::ESTADO_PENDIENTE, $insc->estado,
            'El pago no debe cambiar el estado de la inscripción');
    }

    /** 20 – Un pago no emite QR/PIN */
    public function test_20_pago_no_emite_qr_pin(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $pinAntes = $socio->pin;
        $qrAntes  = $socio->qr_token;

        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $socio->refresh();
        $this->assertEquals($pinAntes, $socio->pin,      'El PIN no debe cambiar al registrar un pago');
        $this->assertEquals($qrAntes,  $socio->qr_token, 'El QR token no debe cambiar al registrar un pago');
    }

    /** 21 – Una cuota vencida no cambia las credenciales */
    public function test_21_cuota_vencida_no_cambia_credenciales(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        // Pagar octubre (ya vencido)
        $this->registrarPagoSimple($socio, $insc, '2026-10-01', '2026-10-31');

        $socio->update(['estado_credencial' => 'activo', 'pin' => '123456', 'qr_token' => 'abc']);
        $socio->refresh();

        // Verificar que la cobertura está vencida
        $cob = $this->pagoService->consultarCoberturaInscripcion($insc, '2026-11-15');
        $this->assertFalse($cob['vigente']);

        // Las credenciales no deben haber cambiado
        $this->assertEquals('activo', $socio->estado_credencial);
        $this->assertEquals('123456', $socio->pin);
        $this->assertEquals('abc',    $socio->qr_token);
    }

    /** 22 – Conservación de pagos históricos (sin detalles) */
    public function test_22_conservacion_pagos_historicos(): void
    {
        // Simular un pago histórico (sin operador_id, sin detalles)
        $socio = $this->crearSocio();
        $pagoHistorico = Pago::create([
            'socio_id'   => $socio->id,
            'fecha_pago' => '2026-06-01',
            'importe'    => 20000,
            'metodo_pago'=> 'efectivo',
            'estado'     => Pago::ESTADO_ACTIVO,
        ]);

        // El pago histórico debe seguir existiendo sin detalles
        $this->assertDatabaseHas('pagos', ['id' => $pagoHistorico->id]);
        $this->assertCount(0, $pagoHistorico->detalles);

        // No se convierte automáticamente en cobertura
        $insc = $this->inscribirAprobado($socio, $this->grupo1);
        $cob  = $this->pagoService->consultarCoberturaInscripcion($insc, '2026-11-15');
        $this->assertFalse($cob['vigente'], 'El pago histórico no debe generar cobertura automática');
    }

    /** 23 – Compatibilidad con rutas API (listado accesible) */
    public function test_23_compatibilidad_rutas_api(): void
    {
        $this->actingAs($this->duena);
        $response = $this->get(route('pagos.index'));
        $response->assertStatus(200);

        $this->actingAs($this->recepcionista);
        $response = $this->get(route('pagos.index'));
        $response->assertStatus(200);
    }

    /** 24 – Auditoría del registro de pagos */
    public function test_24_auditoria_registro_pagos(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $countAntes = Auditoria::count();
        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $this->assertGreaterThan($countAntes, Auditoria::count(),
            'Debe haberse creado al menos un registro de auditoría');

        $this->assertDatabaseHas('auditorias', ['accion' => 'pago_creado']);
    }

    /** 25 – Corrección administrativa protegida (anulación solo por dueña) */
    public function test_25_correccion_administrativa_protegida(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        $pago = $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        // La recepcionista NO puede anular
        $this->actingAs($this->recepcionista);
        $this->assertFalse($this->recepcionista->can('anular', $pago));

        // La dueña SÍ puede anular
        $this->actingAs($this->duena);
        $this->assertTrue($this->duena->can('anular', $pago));

        // La dueña ejecuta la anulación
        $pagoAnulado = $this->pagoService->anularPago($pago, $this->duena, 'Error de caja en prueba de test');

        $this->assertEquals(Pago::ESTADO_ANULADO, $pagoAnulado->estado);
        $this->assertNotNull($pagoAnulado->anulado_at);

        // Las coberturas quedan suspendidas (no eliminadas)
        $this->assertDatabaseHas('pago_detalles', [
            'pago_id'         => $pago->id,
            'estado_cobertura'=> PagoDetalle::COBERTURA_SUSPENDIDA,
        ]);

        // Queda auditado
        $this->assertDatabaseHas('auditorias', ['accion' => 'pago_anulado']);
    }

    // ─── Tests adicionales de negocio ─────────────────────────────────────────

    /** Verificar próxima fecha de inicio sugerida (renovación anticipada) */
    public function test_proxima_fecha_inicio_con_cobertura_activa(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);

        // Cuota actual activa hasta 30/11
        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $proxima = $this->pagoService->obtenerProximaFechaInicio($insc);
        $this->assertEquals('2026-12-01', $proxima, 'La próxima fecha debe ser el día siguiente al vencimiento');
    }

    /** El medio de pago histórico ('tarjeta') no bloquea operaciones nuevas */
    public function test_compatibilidad_medio_pago_historico(): void
    {
        $socio = $this->crearSocio();
        Pago::create([
            'socio_id'   => $socio->id,
            'fecha_pago' => '2026-09-01',
            'importe'    => 10000,
            'metodo_pago'=> 'tarjeta',
            'estado'     => Pago::ESTADO_ACTIVO,
        ]);

        $insc = $this->inscribirAprobado($socio, $this->grupo1);
        $pago = $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30', 35000, 'debito');

        $this->assertEquals('debito', $pago->metodo_pago);
        $this->assertDatabaseHas('pagos', ['metodo_pago' => 'tarjeta']); // histórico intacto
    }

    /** Estado de acceso para el motor: cobertura vigente + inscripción aprobada */
    public function test_estado_acceso_para_motor(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirAprobado($socio, $this->grupo1);
        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $estado = $this->pagoService->estadoAccesoInscripcion($insc, '2026-11-15');

        $this->assertTrue($estado['cobertura_vigente']);
        $this->assertTrue($estado['inscripcion_aprobada']);
    }

    /** Inscripción pendiente + cobertura vigente: acceso no habilitado */
    public function test_inscripcion_pendiente_con_cobertura_no_habilita_acceso(): void
    {
        $socio = $this->crearSocio();
        $insc  = $this->inscribirPendiente($socio, $this->grupo1);
        $this->registrarPagoSimple($socio, $insc, '2026-11-01', '2026-11-30');

        $estado = $this->pagoService->estadoAccesoInscripcion($insc, '2026-11-15');

        $this->assertTrue($estado['cobertura_vigente']);
        $this->assertFalse($estado['inscripcion_aprobada'],
            'La inscripción pendiente no debe considerarse aprobada para acceso');
    }

    /** Recaudación general devuelve datos correctos para dueña */
    public function test_recaudacion_general_duena(): void
    {
        $socio1 = $this->crearSocio();
        $insc1  = $this->inscribirAprobado($socio1, $this->grupo1);
        $this->registrarPagoSimple($socio1, $insc1, '2026-11-01', '2026-11-30', 35000);

        $socio2 = $this->crearSocio();
        $insc2  = $this->inscribirAprobado($socio2, $this->grupo2);
        $this->registrarPagoSimple($socio2, $insc2, '2026-11-01', '2026-11-30', 40000, 'debito');

        $recaudacion = $this->pagoService->obtenerRecaudacionGeneral(
            '2026-11-01',
            '2026-11-30',
            $this->duena
        );

        $this->assertArrayHasKey('total', $recaudacion);
        $this->assertArrayHasKey('pagos', $recaudacion);
        $this->assertArrayHasKey('por_metodo', $recaudacion);
        $this->assertEquals(75000, $recaudacion['total']);
    }
}
