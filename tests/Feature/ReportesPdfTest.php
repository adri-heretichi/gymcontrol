<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\Asistencia;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class ReportesPdfTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $recepcionista;
    protected $socio;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuarios con roles
        $this->admin = User::factory()->create(['rol' => 'admin']);
        $this->recepcionista = User::factory()->create(['rol' => 'recepcionista']);

        // Crear una membresía y socio de prueba
        $membresia = Membresia::create([
            'nombre' => 'Plan Mensual Test',
            'precio' => 5000,
            'limite_horas' => 24,
            'activo' => true
        ]);

        $this->socio = Socio::create([
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'dni' => '12345678',
            'sexo' => 'M',
            'membresia_id' => $membresia->id,
            'token' => 'PIN001',
            'estado' => 'activo',
            'fecha_alta' => now()->subDays(10),
            'fecha_vencimiento' => now()->addDays(20)
        ]);

        // Asignar cobro
        Pago::create([
            'socio_id' => $this->socio->id,
            'importe' => 5000,
            'fecha_pago' => now()->toDateString(),
            'metodo_pago' => 'efectivo'
        ]);

        // Asignar asistencia
        Asistencia::create([
            'socio_id' => $this->socio->id,
            'fecha' => now()->toDateString(),
            'hora_ingreso' => '09:00:00',
            'hora_salida' => '10:00:00',
            'tiempo_permanencia' => 60
        ]);
    }

    /**
     * Test de invitado redirigido al login.
     */
    public function test_guest_is_redirected_to_login()
    {
        $response1 = $this->get(route('reportes.ficha-socio', $this->socio->id));
        $response1->assertRedirect(route('login'));

        $response2 = $this->get(route('reportes.asistencias'));
        $response2->assertRedirect(route('login'));

        $response3 = $this->get(route('reportes.pagos'));
        $response3->assertRedirect(route('login'));
    }

    /**
     * Test recepcionista permisos correctos en ficha y asistencias, pero 403 en pagos.
     */
    public function test_recepcionista_permissions_for_pdfs()
    {
        $this->actingAs($this->recepcionista);

        // Ficha de Socio: 200 OK y tipo PDF
        $response = $this->get(route('reportes.ficha-socio', $this->socio->id));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('%PDF-', $response->getContent());

        // Asistencias: 200 OK y tipo PDF
        $responseAsistencias = $this->get(route('reportes.asistencias'));
        $responseAsistencias->assertStatus(200);
        $responseAsistencias->assertHeader('Content-Type', 'application/pdf');

        // Pagos: 403 Prohibido
        $responsePagos = $this->get(route('reportes.pagos'));
        $responsePagos->assertStatus(403);
    }

    /**
     * Test administrador tiene acceso completo a todos los reportes PDF.
     */
    public function test_administrador_has_access_to_all_pdfs()
    {
        $this->actingAs($this->admin);

        // Ficha de Socio
        $response1 = $this->get(route('reportes.ficha-socio', $this->socio->id));
        $response1->assertStatus(200);
        $response1->assertHeader('Content-Type', 'application/pdf');

        // Asistencias
        $response2 = $this->get(route('reportes.asistencias'));
        $response2->assertStatus(200);
        $response2->assertHeader('Content-Type', 'application/pdf');

        // Pagos
        $response3 = $this->get(route('reportes.pagos'));
        $response3->assertStatus(200);
        $response3->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('%PDF-', $response3->getContent());
    }

    /**
     * Test de validación de rango de fechas máximo a 1 año.
     */
    public function test_date_range_cannot_exceed_one_year()
    {
        $this->actingAs($this->admin);

        // Rango mayor a 1 año (15 meses)
        $response = $this->get(route('reportes.asistencias', [
            'fecha_desde' => now()->subMonths(15)->format('Y-m-d'),
            'fecha_hasta' => now()->format('Y-m-d')
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'El rango máximo de búsqueda para reportes no puede superar 1 año.');
    }

    /**
     * Test de validación de fechas invertidas.
     */
    public function test_date_desde_cannot_be_after_date_hasta()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reportes.asistencias', [
            'fecha_desde' => now()->format('Y-m-d'),
            'fecha_hasta' => now()->subDays(5)->format('Y-m-d')
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'La fecha desde no puede ser posterior a la fecha hasta.');
    }

    /**
     * Test de rango de fecha por defecto de los últimos 30 días si no se proveen fechas.
     */
    public function test_default_date_range_is_30_days()
    {
        $this->actingAs($this->admin);

        // Creamos una asistencia hace 45 días
        $socio2 = Socio::create([
            'nombre' => 'Maria',
            'apellido' => 'Gomez',
            'dni' => '87654321',
            'sexo' => 'F',
            'membresia_id' => $this->socio->membresia_id,
            'token' => 'PIN002',
            'estado' => 'activo',
            'fecha_alta' => now()->subDays(50)
        ]);

        Asistencia::create([
            'socio_id' => $socio2->id,
            'fecha' => now()->subDays(45)->toDateString(),
            'hora_ingreso' => '10:00:00',
            'hora_salida' => '11:00:00',
            'tiempo_permanencia' => 60
        ]);

        // Asistencia de hoy
        Asistencia::create([
            'socio_id' => $socio2->id,
            'fecha' => now()->toDateString(),
            'hora_ingreso' => '12:00:00',
            'hora_salida' => '13:00:00',
            'tiempo_permanencia' => 60
        ]);

        // Mock de la generación de PDF para validar los datos pasados a la vista
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')
            ->once()
            ->with('reportes.asistencias', \Mockery::on(function ($data) use ($socio2) {
                $asistencias = $data['asistencias'];
                $socioIds = $asistencias->pluck('socio_id')->toArray();
                
                // Deben listarse solo las asistencias dentro de los últimos 30 días (Juan y Maria, hoy)
                $this->assertCount(2, $asistencias);
                $this->assertContains($this->socio->id, $socioIds);
                $this->assertContains($socio2->id, $socioIds);
                
                // La asistencia de hace 45 días no debe estar
                foreach ($asistencias as $asistencia) {
                    $this->assertNotEquals(
                        now()->subDays(45)->format('Y-m-d'), 
                        \Carbon\Carbon::parse($asistencia->fecha)->format('Y-m-d')
                    );
                }
                
                return true;
            }))
            ->andReturnSelf();

        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('stream')
            ->once()
            ->andReturn(response('PDF_STREAM_OK', 200, ['Content-Type' => 'application/pdf']));

        // Sin parámetros de fecha, se asume los últimos 30 días
        $response = $this->get(route('reportes.asistencias'));
        
        $response->assertStatus(200);
        $this->assertEquals('PDF_STREAM_OK', $response->getContent());
    }
}
