<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Membresia;
use App\Models\Socio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU6 - Registrar ingreso y egreso.
 * Criterio: el socio puede SALIR aunque tenga la cuota vencida o no tenga apto físico.
 * (El sistema solo valida cuota y apto al INGRESAR.)
 */
class SalidaConCuotaVencidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_salida_con_cuota_vencida_y_sin_apto_fisico(): void
    {
        $recepcionista = User::factory()->create(['rol' => 'recepcionista']);

        $membresia = Membresia::create([
            'nombre' => 'Pase Libre',
            'precio' => 8000,
            'estado' => 'activo',
        ]);

        // Socio con cuota vencida hace 3 días y SIN apto físico cargado
        $socio = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'Carla',
            'apellido' => 'Ríos',
            'dni' => '55555555',
            'sexo' => 'F',
            'correo' => 'carla@example.com',
            'celular' => '1122334459',
            'token' => 'TOKEN555',
            'fecha_alta' => Carbon::today()->subMonths(2),
            'fecha_vencimiento' => Carbon::today()->subDays(3),
            'estado' => 'activo',
        ]);

        // Está dentro del gimnasio desde hace 30 minutos
        $asistencia = Asistencia::create([
            'socio_id' => $socio->id,
            'fecha' => Carbon::today(),
            'hora_ingreso' => Carbon::now()->subMinutes(30)->format('H:i:s'),
        ]);

        $this->actingAs($recepcionista);

        Livewire::test(\App\Livewire\ControlAcceso::class)
            ->set('identificador', '55555555')
            ->call('procesar')
            ->assertSet('estadoAcceso', 'salida')
            ->assertSet('mensajeAcceso', 'Salida registrada con éxito. ¡Hasta luego!');

        $asistencia->refresh();
        $this->assertNotNull($asistencia->hora_salida);
        $this->assertEquals(30, $asistencia->tiempo_permanencia);
    }
}
