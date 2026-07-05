<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\AptoFisico;
use App\Models\Asistencia;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class AsistenciaHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;
    private Socio $socioJuan;
    private Socio $socioAna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['rol' => 'admin']);
        $this->recepcionista = User::factory()->create(['rol' => 'recepcionista']);

        $membresia = Membresia::create([
            'nombre' => 'Pase Libre',
            'precio' => 10000,
            'estado' => 'activo',
        ]);

        $this->socioJuan = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'dni' => '12345678',
            'sexo' => 'M',
            'correo' => 'juan@example.com',
            'celular' => '1122334455',
            'token' => 'TOKEN111',
            'fecha_alta' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addDays(30),
            'estado' => 'activo',
        ]);

        $this->socioAna = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'Ana',
            'apellido' => 'Gómez',
            'dni' => '87654321',
            'sexo' => 'F',
            'correo' => 'ana@example.com',
            'celular' => '1122334466',
            'token' => 'TOKEN222',
            'fecha_alta' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addDays(30),
            'estado' => 'activo',
        ]);
    }

    public function test_guest_cannot_access_asistencias_route(): void
    {
        $response = $this->get('/asistencias');
        $response->assertRedirect('/login');
    }

    public function test_recepcionista_and_admin_can_access_asistencias_route(): void
    {
        $this->actingAs($this->recepcionista);
        $response = $this->get('/asistencias');
        $response->assertStatus(200);

        $this->actingAs($this->admin);
        $response = $this->get('/asistencias');
        $response->assertStatus(200);
    }

    public function test_asistencias_filters_and_search(): void
    {
        $this->actingAs($this->recepcionista);

        // Crear asistencias de prueba
        // Juan Perez - Ayer - Completada (45 min)
        Asistencia::create([
            'socio_id' => $this->socioJuan->id,
            'fecha' => Carbon::yesterday(),
            'hora_ingreso' => '10:00:00',
            'hora_salida' => '10:45:00',
            'tiempo_permanencia' => 45,
        ]);

        // Ana Gomez - Hoy - En sala (Activa)
        Asistencia::create([
            'socio_id' => $this->socioAna->id,
            'fecha' => Carbon::today(),
            'hora_ingreso' => '14:00:00',
            'hora_salida' => null,
            'tiempo_permanencia' => null,
        ]);

        // 1. Probar buscador por nombre de Juan
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->set('search', 'Juan')
            ->assertViewHas('asistencias', function ($asistencias) {
                return $asistencias->count() === 1 && $asistencias->first()->socio_id === $this->socioJuan->id;
            });

        // 2. Probar buscador por DNI de Ana
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->set('search', '87654321')
            ->assertViewHas('asistencias', function ($asistencias) {
                return $asistencias->count() === 1 && $asistencias->first()->socio_id === $this->socioAna->id;
            });

        // 3. Probar filtro de fechaDesde (Hoy)
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->set('fechaDesde', Carbon::today()->format('Y-m-d'))
            ->assertViewHas('asistencias', function ($asistencias) {
                return $asistencias->count() === 1 && $asistencias->first()->socio_id === $this->socioAna->id;
            });

        // 4. Probar filtro de estado 'en_sala'
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->set('estado', 'en_sala')
            ->assertViewHas('asistencias', function ($asistencias) {
                return $asistencias->count() === 1 && $asistencias->first()->hora_salida === null;
            });

        // 5. Probar filtro de estado 'finalizados'
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->set('estado', 'finalizados')
            ->assertViewHas('asistencias', function ($asistencias) {
                return $asistencias->count() === 1 && $asistencias->first()->hora_salida !== null;
            });
    }

    public function test_socio_details_asistencias_pagination(): void
    {
        $this->actingAs($this->recepcionista);

        // Crear 12 asistencias para Juan Pérez
        for ($i = 0; $i < 12; $i++) {
            Asistencia::create([
                'socio_id' => $this->socioJuan->id,
                'fecha' => Carbon::today()->subDays($i),
                'hora_ingreso' => '10:00:00',
                'hora_salida' => '11:00:00',
                'tiempo_permanencia' => 60,
            ]);
        }

        Livewire::test(\App\Livewire\Socios\VerSocio::class, ['socio' => $this->socioJuan])
            ->assertViewHas('asistenciasPaginadas', function ($asistencias) {
                return $asistencias->count() === 10; // La página 1 tiene 10 ítems por paginación
            });
    }

    public function test_admin_can_edit_assistance_and_recalculate_permanence(): void
    {
        $this->actingAs($this->admin);

        // Crear una asistencia activa
        $asistencia = Asistencia::create([
            'socio_id' => $this->socioJuan->id,
            'fecha' => Carbon::today(),
            'hora_ingreso' => '08:00:00',
            'hora_salida' => null,
            'tiempo_permanencia' => null,
        ]);

        // Abrir edición, modificar hora de salida y guardar
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->call('edit', $asistencia)
            ->assertSet('editingId', $asistencia->id)
            ->assertSet('fecha', Carbon::today()->format('Y-m-d'))
            ->assertSet('hora_ingreso', '08:00:00')
            ->assertSet('hora_salida', null)
            ->set('hora_salida', '09:30:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $asistencia->refresh();
        $this->assertEquals('09:30:00', $asistencia->hora_salida);
        $this->assertEquals(90, $asistencia->tiempo_permanencia); // 1h 30m = 90 minutos
    }

    public function test_admin_edit_validations(): void
    {
        $this->actingAs($this->admin);

        $asistencia = Asistencia::create([
            'socio_id' => $this->socioJuan->id,
            'fecha' => Carbon::today(),
            'hora_ingreso' => '10:00:00',
            'hora_salida' => null,
            'tiempo_permanencia' => null,
        ]);

        // Intentar guardar una hora de salida anterior a la hora de ingreso
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->call('edit', $asistencia)
            ->set('hora_salida', '09:00:00')
            ->call('save')
            ->assertHasErrors(['hora_salida'])
            ->assertSet('showEditModal', true);

        // Intentar guardar una fecha futura
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->call('edit', $asistencia)
            ->set('fecha', Carbon::tomorrow()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['fecha'])
            ->assertSet('showEditModal', true);
    }

    public function test_recepcionista_cannot_edit_or_save_assistance(): void
    {
        $this->actingAs($this->recepcionista);

        $asistencia = Asistencia::create([
            'socio_id' => $this->socioJuan->id,
            'fecha' => Carbon::today(),
            'hora_ingreso' => '10:00:00',
            'hora_salida' => null,
            'tiempo_permanencia' => null,
        ]);

        // Clic en editar arroja 403
        Livewire::test(\App\Livewire\Asistencias\ListarAsistencias::class)
            ->assertDontSee('Editar') // Botón de edición oculto
            ->call('edit', $asistencia)
            ->assertStatus(403);
    }

    public function test_no_deletion_functionality_exists(): void
    {
        // Verificar que no existen los métodos de eliminación en la clase
        $reflection = new \ReflectionClass(\App\Livewire\Asistencias\ListarAsistencias::class);
        
        $this->assertFalse($reflection->hasMethod('delete'));
        $this->assertFalse($reflection->hasMethod('destroy'));
        $this->assertFalse($reflection->hasMethod('eliminar'));
    }
}
