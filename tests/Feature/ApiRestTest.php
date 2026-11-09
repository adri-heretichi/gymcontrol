<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\AptoFisico;
use App\Models\Asistencia;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\PagoDetalle;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Tests\TestCase;

class ApiRestTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $recepcionista;
    protected $membresia;
    protected $socio;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuarios con roles
        $this->admin = User::factory()->create(['rol' => 'admin', 'password' => bcrypt('password')]);
        $this->recepcionista = User::factory()->create(['rol' => 'recepcionista', 'password' => bcrypt('password')]);

        // Crear membresía activa
        $this->membresia = Membresia::create([
            'nombre' => 'Plan Anual',
            'precio' => 12000.00,
            'horas_mensuales' => 30,
            'estado' => 'activo'
        ]);

        // Crear socio activo con vencimiento futuro
        $this->socio = Socio::create([
            'nombre' => 'Luis',
            'apellido' => 'Gomez',
            'dni' => '40123456',
            'sexo' => 'M',
            'membresia_id' => $this->membresia->id,
            'token' => 'PIN888',
            'estado' => 'activo',
            'fecha_alta' => now()->subDays(5)->toDateString(),
            'fecha_vencimiento' => now()->addDays(25)->toDateString()
        ]);

        // Registrar apto físico vigente para el socio
        AptoFisico::create([
            'socio_id' => $this->socio->id,
            'archivo' => 'secure/aptos_fisicos/test.pdf',
            'fecha_emision' => now()->subDays(10)->toDateString(),
            'fecha_vencimiento' => now()->addDays(350)->toDateString(),
            'estado' => 'vigente'
        ]);
    }

    /**
     * Test de autenticación: credenciales correctas obtienen token, incorrectas fallan.
     */
    public function test_auth_login_endpoint()
    {
        // Fallido
        $response = $this->postJson(route('api.login'), [
            'email' => $this->admin->email,
            'password' => 'wrong-password'
        ]);
        $response->assertStatus(401);
        $response->assertJson(['success' => false]);

        // Exitoso
        $response = $this->postJson(route('api.login'), [
            'email' => $this->admin->email,
            'password' => 'password'
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['token', 'token_type', 'rol', 'user']
        ]);
    }

    /**
     * Test que deniega acceso a rutas protegidas sin token.
     */
    public function test_protected_routes_deny_guest()
    {
        $response = $this->getJson(route('api.socios.index'));
        $response->assertStatus(401);
    }

    /**
     * Test de permisos: Recepcionista puede ver pero NO escribir ni borrar Socios.
     */
    public function test_recepcionista_cannot_write_or_delete_socios()
    {
        $token = $this->recepcionista->createToken('test_token')->plainTextToken;

        // GET socios: Autorizado (200)
        $responseGet = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.socios.index'));
        $responseGet->assertStatus(200);

        // POST crear socio: Prohibido (403)
        $responsePost = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.socios.store'), [
                'nombre' => 'Socio',
                'apellido' => 'Prueba',
                'dni' => '99999999',
                'sexo' => 'M',
                'membresia_id' => $this->membresia->id,
                'fecha_alta' => now()->toDateString(),
                'fecha_vencimiento' => now()->addMonth()->toDateString(),
            ]);
        $responsePost->assertStatus(403);

        // DELETE socio: Prohibido (403)
        $responseDelete = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson(route('api.socios.destroy', $this->socio->id));
        $responseDelete->assertStatus(403);
    }

    /**
     * Test de permisos: Administrador tiene acceso total a CRUD de Socios.
     */
    public function test_admin_has_full_crud_socios()
    {
        $token = $this->admin->createToken('test_token')->plainTextToken;

        // Crear socio (POST)
        $responsePost = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.socios.store'), [
                'nombre' => 'Socio',
                'apellido' => 'Prueba',
                'dni' => '99999999',
                'sexo' => 'M',
                'membresia_id' => $this->membresia->id,
                'fecha_alta' => now()->toDateString(),
                'fecha_vencimiento' => now()->addMonth()->toDateString(),
            ]);
        $responsePost->assertStatus(201);
        $this->assertDatabaseHas('socios', ['dni' => '99999999']);
    }

    /**
     * Test de Baja Lógica en Socios y Membresías.
     */
    public function test_logical_delete_for_socios_and_membresias()
    {
        $token = $this->admin->createToken('test_token')->plainTextToken;

        // DELETE Socio
        $responseSocio = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson(route('api.socios.destroy', $this->socio->id));
        
        $responseSocio->assertStatus(200);
        $this->socio->refresh();
        $this->assertEquals('inactivo', $this->socio->estado);
        // Verificar que sigue existiendo en BD
        $this->assertDatabaseHas('socios', ['id' => $this->socio->id]);

        // DELETE Membresía
        $responseMemb = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson(route('api.membresias.destroy', $this->membresia->id));

        $responseMemb->assertStatus(200);
        $this->membresia->refresh();
        $this->assertEquals('inactivo', $this->membresia->estado);
        $this->assertDatabaseHas('membresias', ['id' => $this->membresia->id]);
    }

    /**
     * Test de carga y descarga de apto físico en formato binario.
     */
    public function test_apto_fisico_upload_and_download()
    {
        Storage::fake('local');
        $token = $this->admin->createToken('test_token')->plainTextToken;
        $file = UploadedFile::fake()->create('certificado.pdf', 500, 'application/pdf');

        // Carga
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.aptos-fisicos.store'), [
                'socio_id' => $this->socio->id,
                'fecha_emision' => now()->toDateString(),
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'archivo' => $file
            ]);
        
        $response->assertStatus(201);
        $aptoId = $response->json('data.id');
        $this->assertTrue($response->json('data.tiene_archivo'));
        $this->assertArrayNotHasKey('archivo', $response->json('data'));
        $filePath = AptoFisico::query()->findOrFail($aptoId)->archivo;
        
        Storage::disk('local')->assertExists($filePath);

        // Descarga
        $responseDownload = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.aptos-fisicos.download', $aptoId));
        $responseDownload->assertStatus(200);

        $responseDetail = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.aptos-fisicos.show', $aptoId))
            ->assertOk();
        $responseDetail->assertJsonPath('data.tiene_archivo', true);
        $this->assertArrayNotHasKey('archivo', $responseDetail->json('data'));
    }

    public function test_reception_cannot_read_or_download_medical_documents_but_can_register_them()
    {
        Storage::fake('local');
        $token = $this->recepcionista->createToken('test_token')->plainTextToken;
        $apto = AptoFisico::create([
            'socio_id' => $this->socio->id,
            'archivo' => 'secure/aptos_fisicos/private.pdf',
            'fecha_emision' => now()->subDay()->toDateString(),
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado' => 'vigente',
        ]);
        Storage::disk('local')->put($apto->archivo, 'private');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.aptos-fisicos.index'))
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.aptos-fisicos.download', $apto->id))
            ->assertForbidden();

        $upload = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.aptos-fisicos.store'), [
                'socio_id' => $this->socio->id,
                'fecha_emision' => now()->toDateString(),
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'archivo' => UploadedFile::fake()->create('medical.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        $upload->assertJsonPath('data.tiene_archivo', true);
        $this->assertArrayNotHasKey('archivo', $upload->json('data'));
    }

    public function test_socio_api_never_exposes_legacy_or_active_credentials_or_medical_details()
    {
        $token = $this->admin->createToken('test_token')->plainTextToken;
        $this->socio->update([
            'pin' => '123456',
            'qr_token' => str_repeat('a', 64),
            'estado_credencial' => 'activo',
            'decision_medica_obs' => 'Sensitive medical note',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.socios.show', $this->socio->id))
            ->assertOk();

        $response->assertJsonMissingPath('data.token');
        $response->assertJsonMissingPath('data.pin');
        $response->assertJsonMissingPath('data.qr_token');
        $response->assertJsonMissingPath('data.qr');
        $response->assertJsonMissingPath('data.aptos_fisicos');
        $this->assertStringNotContainsString('Sensitive medical note', $response->getContent());
        $this->assertStringNotContainsString(str_repeat('a', 64), $response->getContent());
    }

    public function test_attendance_api_returns_only_safe_member_fields()
    {
        $token = $this->admin->createToken('test_token')->plainTextToken;
        $this->socio->update([
            'token' => 'INTERNAL-ACCESS-TOKEN',
            'pin' => '765432',
            'qr_token' => str_repeat('c', 64),
        ]);
        $asistencia = Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => today()->toDateString(),
            'hora_ingreso' => '10:00:00',
            'metodo_acceso' => 'pin_recepcion',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.asistencias.show', $asistencia->id))
            ->assertOk();

        $response->assertJsonMissingPath('data.socio.token');
        $response->assertJsonMissingPath('data.socio.pin');
        $response->assertJsonMissingPath('data.socio.qr_token');
        $this->assertStringNotContainsString('INTERNAL-ACCESS-TOKEN', $response->getContent());
        $this->assertStringNotContainsString(str_repeat('c', 64), $response->getContent());
    }

    public function test_reception_cannot_collect_revenue_through_the_api()
    {
        $token = $this->recepcionista->createToken('test_token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.pagos.recaudacion'))
            ->assertForbidden();
    }

    public function test_pagos_api_uses_discipline_coverage_and_never_extends_legacy_member_expiry()
    {
        $token = $this->admin->createToken('test_token')->plainTextToken;
        $disciplina = Disciplina::create(['nombre' => 'Funcional', 'estado' => 'activa']);
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => 'Funcional tarde',
            'profesor' => 'Entrenadora',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
        $inscripcion = Inscripcion::create([
            'socio_id' => $this->socio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => today()->toDateString(),
            'estado' => Inscripcion::ESTADO_APROBADA,
            'aprobado_por' => $this->admin->id,
            'aprobado_at' => now(),
        ]);
        $vencimientoInicial = Carbon::parse($this->socio->fecha_vencimiento)->toDateString();

        $detalle = [
            'inscripcion_id' => $inscripcion->id,
            'concepto' => 'Cuota Funcional',
            'fecha_inicio' => today()->toDateString(),
            'fecha_fin' => today()->addMonth()->toDateString(),
            'subtotal' => 12000.00,
        ];
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.pagos.store'), [
                'socio_id' => $this->socio->id,
                'metodo_pago' => 'transferencia',
                'detalles' => [$detalle, $detalle],
            ])
            ->assertUnprocessable();
        $this->assertDatabaseCount('pagos', 0);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.pagos.store'), [
                'socio_id' => $this->socio->id,
                'fecha_pago' => now()->toDateString(),
                'metodo_pago' => 'transferencia',
                'detalles' => [$detalle],
            ])
            ->assertCreated();

        $this->assertSame(12000.0, (float) $response->json('data.importe'));
        $this->assertTrue(PagoDetalle::query()
            ->where('inscripcion_id', $inscripcion->id)
            ->whereDate('fecha_inicio_cobertura', today()->toDateString())
            ->exists());

        $this->socio->refresh();
        $this->assertSame($vencimientoInicial, $this->socio->fecha_vencimiento?->toDateString());
        $this->assertSame('sin_emitir', $this->socio->estado_credencial);
    }

    public function test_pagos_api_rejects_an_inscription_owned_by_another_member()
    {
        $token = $this->admin->createToken('test_token')->plainTextToken;
        $otroSocio = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Otro',
            'apellido' => 'Socio',
            'dni' => '40987654',
            'sexo' => 'M',
            'token' => 'TOKEN-OTRO',
            'fecha_alta' => today()->toDateString(),
            'estado' => 'activo',
        ]);
        $disciplina = Disciplina::create(['nombre' => 'Kickboxing', 'estado' => 'activa']);
        $grupo = Grupo::create([
            'disciplina_id' => $disciplina->id,
            'nombre' => 'Kickboxing noche',
            'profesor' => 'Entrenadora',
            'cupo_maximo' => 15,
            'estado' => 'activo',
        ]);
        $inscripcion = Inscripcion::create([
            'socio_id' => $otroSocio->id,
            'grupo_id' => $grupo->id,
            'fecha_inscripcion' => today()->toDateString(),
            'estado' => Inscripcion::ESTADO_APROBADA,
            'aprobado_por' => $this->admin->id,
            'aprobado_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.pagos.store'), [
                'socio_id' => $this->socio->id,
                'metodo_pago' => 'efectivo',
                'detalles' => [[
                    'inscripcion_id' => $inscripcion->id,
                    'concepto' => 'Cuota ajena',
                    'fecha_inicio' => today()->toDateString(),
                    'fecha_fin' => today()->addMonth()->toDateString(),
                    'subtotal' => 1000,
                ]],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_revenue_api_excludes_voided_payments()
    {
        $token = $this->admin->createToken('test_token')->plainTextToken;
        Pago::create([
            'socio_id' => $this->socio->id,
            'operador_id' => $this->admin->id,
            'fecha_pago' => today()->toDateString(),
            'importe' => 1000,
            'metodo_pago' => 'efectivo',
            'estado' => Pago::ESTADO_ACTIVO,
        ]);
        Pago::create([
            'socio_id' => $this->socio->id,
            'operador_id' => $this->admin->id,
            'fecha_pago' => today()->toDateString(),
            'importe' => 2500,
            'metodo_pago' => 'efectivo',
            'estado' => Pago::ESTADO_ANULADO,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.pagos.recaudacion', [
                'fecha_desde' => today()->toDateString(),
                'fecha_hasta' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.total_recaudado', 1000)
            ->assertJsonPath('data.cantidad_pagos', 1);
    }

    /**
     * La API de acceso ya no acepta DNI ni alterna entre entrada y salida.
     */
    public function test_control_acceso_api_rejects_legacy_dni_toggle()
    {
        $token = $this->recepcionista->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.control-acceso.scan'), [
                'identificador' => $this->socio->dni
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['metodo', 'accion']);
        $this->assertDatabaseMissing('asistencias', ['socio_id' => $this->socio->id]);
    }
}
