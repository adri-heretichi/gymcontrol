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

class ControlAccesoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;
    private Membresia $membresia;
    private Socio $socioValido;
    private Socio $socioVencido;
    private Socio $socioSinApto;
    private Socio $socioInactivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['rol' => 'admin']);
        $this->recepcionista = User::factory()->create(['rol' => 'recepcionista']);

        $this->membresia = Membresia::create([
            'nombre' => 'Pase Libre',
            'precio' => 8000,
            'estado' => 'activo',
        ]);

        // 1. Socio válido
        $this->socioValido = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'dni' => '11111111',
            'sexo' => 'M',
            'correo' => 'juan@example.com',
            'celular' => '1122334455',
            'token' => 'TOKEN111',
            'fecha_alta' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addDays(15),
            'estado' => 'activo',
        ]);
        AptoFisico::create([
            'socio_id' => $this->socioValido->id,
            'archivo' => 'secure/aptos/juan.pdf',
            'fecha_emision' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'estado' => 'vigente',
        ]);

        // 2. Socio con membresía vencida
        $this->socioVencido = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'María',
            'apellido' => 'Gómez',
            'dni' => '22222222',
            'sexo' => 'F',
            'correo' => 'maria@example.com',
            'celular' => '1122334456',
            'token' => 'TOKEN222',
            'fecha_alta' => Carbon::today()->subMonths(2),
            'fecha_vencimiento' => Carbon::today()->subDays(5),
            'estado' => 'activo',
        ]);
        AptoFisico::create([
            'socio_id' => $this->socioVencido->id,
            'archivo' => 'secure/aptos/maria.pdf',
            'fecha_emision' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'estado' => 'vigente',
        ]);

        // 3. Socio sin Apto Físico
        $this->socioSinApto = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Pedro',
            'apellido' => 'Alfonso',
            'dni' => '33333333',
            'sexo' => 'M',
            'correo' => 'pedro@example.com',
            'celular' => '1122334457',
            'token' => 'TOKEN333',
            'fecha_alta' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addMonth(),
            'estado' => 'activo',
        ]);

        // 4. Socio inactivo
        $this->socioInactivo = Socio::create([
            'membresia_id' => $this->membresia->id,
            'nombre' => 'Laura',
            'apellido' => 'Díaz',
            'dni' => '44444444',
            'sexo' => 'F',
            'correo' => 'laura@example.com',
            'celular' => '1122334458',
            'token' => 'TOKEN444',
            'fecha_alta' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addMonth(),
            'estado' => 'inactivo',
        ]);
        AptoFisico::create([
            'socio_id' => $this->socioInactivo->id,
            'archivo' => 'secure/aptos/laura.pdf',
            'fecha_emision' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'estado' => 'vigente',
        ]);
    }

    /**
     * Test de ingreso exitoso para un socio válido.
     */
    public function test_ingreso_exitoso(): void
    {
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\ControlAcceso::class)
            ->set('identificador', 'TOKEN111')
            ->call('procesar')
            ->assertSet('estadoAcceso', 'exito')
            ->assertSet('mensajeAcceso', '¡Ingreso Autorizado! Bienvenido/a.')
            ->assertSet('identificador', '');

        $asistencia = Asistencia::where('socio_id', $this->socioValido->id)->first();
        $this->assertNotNull($asistencia);
        $this->assertEquals(Carbon::today()->format('Y-m-d'), $asistencia->fecha->format('Y-m-d'));
        $this->assertNull($asistencia->hora_salida);
    }

    /**
     * Test de salida exitosa para un socio que ya está en sala.
     */
    public function test_salida_exitosa(): void
    {
        $this->actingAs($this->recepcionista);

        // Crear una asistencia abierta
        $asistencia = Asistencia::create([
            'socio_id' => $this->socioValido->id,
            'fecha' => Carbon::today(),
            'hora_ingreso' => Carbon::now()->subMinutes(45)->format('H:i:s'),
        ]);

        Livewire::test(\App\Livewire\ControlAcceso::class)
            ->set('identificador', '11111111') // Usamos DNI en vez de Token
            ->call('procesar')
            ->assertSet('estadoAcceso', 'salida')
            ->assertSet('mensajeAcceso', 'Salida registrada con éxito. ¡Hasta luego!')
            ->assertSet('identificador', '');

        $asistencia->refresh();
        $this->assertNotNull($asistencia->hora_salida);
        $this->assertEquals(45, $asistencia->tiempo_permanencia);
    }

    /**
     * Test de bloqueo por membresía vencida.
     */
    public function test_bloqueo_membresia_vencida(): void
    {
        $this->actingAs($this->recepcionista);

        $test = Livewire::test(\App\Livewire\ControlAcceso::class)
            ->set('identificador', 'TOKEN222')
            ->call('procesar')
            ->assertSet('estadoAcceso', 'error')
            ->assertSet('mensajeAcceso', 'Ingreso Denegado')
            ->assertSet('identificador', '');

        $this->assertContains('La membresía ha EXPIRADO el ' . $this->socioVencido->fecha_vencimiento->format('d/m/Y') . '.', $test->get('motivosDenegacion'));

        // No debe haber registros en asistencias
        $this->assertDatabaseMissing('asistencias', [
            'socio_id' => $this->socioVencido->id,
        ]);
    }

    /**
     * Test de bloqueo por apto físico vencido o inexistente.
     */
    public function test_bloqueo_apto_fisico_inexistente(): void
    {
        $this->actingAs($this->recepcionista);

        $test = Livewire::test(\App\Livewire\ControlAcceso::class)
            ->set('identificador', 'TOKEN333')
            ->call('procesar')
            ->assertSet('estadoAcceso', 'error')
            ->assertSet('mensajeAcceso', 'Ingreso Denegado')
            ->assertSet('identificador', '');

        $this->assertContains('Falta Certificado Médico (Apto Físico) vigente.', $test->get('motivosDenegacion'));

        $this->assertDatabaseMissing('asistencias', [
            'socio_id' => $this->socioSinApto->id,
        ]);
    }

    /**
     * Test de bloqueo por socio inactivo.
     */
    public function test_bloqueo_socio_inactivo(): void
    {
        $this->actingAs($this->recepcionista);

        $test = Livewire::test(\App\Livewire\ControlAcceso::class)
            ->set('identificador', 'TOKEN444')
            ->call('procesar')
            ->assertSet('estadoAcceso', 'error')
            ->assertSet('mensajeAcceso', 'Ingreso Denegado')
            ->assertSet('identificador', '');

        $this->assertContains('El socio se encuentra INACTIVO.', $test->get('motivosDenegacion'));

        $this->assertDatabaseMissing('asistencias', [
            'socio_id' => $this->socioInactivo->id,
        ]);
    }

    /**
     * Test de salida manual por parte del operador.
     */
    public function test_salida_manual_operador(): void
    {
        $this->actingAs($this->recepcionista);

        // Crear una asistencia abierta
        $asistencia = Asistencia::create([
            'socio_id' => $this->socioValido->id,
            'fecha' => Carbon::now()->subHours(2)->toDateString(),
            'hora_ingreso' => Carbon::now()->subHours(2)->format('H:i:s'),
        ]);

        Livewire::test(\App\Livewire\ControlAcceso::class)
            ->call('registrarSalidaManual', $asistencia->id)
            ->assertSet('estadoAcceso', 'salida')
            ->assertSet('mensajeAcceso', 'Salida manual registrada.');

        $asistencia->refresh();
        $this->assertNotNull($asistencia->hora_salida);
        $this->assertEquals(120, $asistencia->tiempo_permanencia);
    }
}
