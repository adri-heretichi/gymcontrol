<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\AptoFisico;
use App\Models\Asistencia;
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
        $filePath = $response->json('data.archivo');
        
        Storage::disk('local')->assertExists($filePath);

        // Descarga
        $responseDownload = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.aptos-fisicos.download', $aptoId));
        $responseDownload->assertStatus(200);
    }

    /**
     * Test de cobro y extensión automática de membresía.
     */
    public function test_pagos_store_extends_vencimiento()
    {
        $token = $this->admin->createToken('test_token')->plainTextToken;

        // Vencimiento inicial
        $vencimientoInicial = Carbon::parse($this->socio->fecha_vencimiento);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.pagos.store'), [
                'socio_id' => $this->socio->id,
                'fecha_pago' => now()->toDateString(),
                'importe' => 12000.00,
                'metodo_pago' => 'transferencia'
            ]);

        $response->assertStatus(201);

        $this->socio->refresh();
        $nuevoVencimiento = Carbon::parse($this->socio->fecha_vencimiento);

        // Debe ser exactamente 1 mes después del vencimiento inicial
        $this->assertEquals($vencimientoInicial->addMonth()->toDateString(), $nuevoVencimiento->toDateString());
    }

    /**
     * Test de control de acceso scan: Entrada, Salida y Bloqueos de ingreso.
     */
    public function test_control_acceso_scan_flow()
    {
        $token = $this->recepcionista->createToken('test_token')->plainTextToken;

        // 1. Ingreso Exitoso (Socio activo con apto físico vigente)
        $responseIngreso = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.control-acceso.scan'), [
                'identificador' => $this->socio->dni
            ]);
        
        $responseIngreso->assertStatus(200);
        $responseIngreso->assertJsonPath('data.tipo_marca', 'ingreso');
        $this->assertDatabaseHas('asistencias', [
            'socio_id' => $this->socio->id,
            'hora_salida' => null
        ]);

        // 2. Salida Exitosa (Escaneo del mismo socio registra egreso y tiempo de permanencia)
        $responseSalida = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.control-acceso.scan'), [
                'identificador' => $this->socio->dni
            ]);

        $responseSalida->assertStatus(200);
        $responseSalida->assertJsonPath('data.tipo_marca', 'salida');
        $this->assertDatabaseMissing('asistencias', [
            'socio_id' => $this->socio->id,
            'hora_salida' => null
        ]);

        // 3. Ingreso Denegado - Membresía expirada
        $this->socio->update(['fecha_vencimiento' => now()->subDays(10)->toDateString()]);
        
        $responseDenegado = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.control-acceso.scan'), [
                'identificador' => $this->socio->dni
            ]);

        $responseDenegado->assertStatus(400);
        $responseDenegado->assertJsonPath('success', false);
        $responseDenegado->assertJsonFragment(['La membresía ha EXPIRADO el ' . now()->subDays(10)->format('d/m/Y') . '.']);
    }
}
