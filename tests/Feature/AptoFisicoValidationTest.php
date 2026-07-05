<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\AptoFisico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Livewire\Livewire;

class AptoFisicoValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;
    private Socio $socio;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create([
            'rol' => 'admin',
        ]);

        $this->recepcionista = User::factory()->create([
            'rol' => 'recepcionista',
        ]);

        $membresia = Membresia::create([
            'nombre' => 'Plan Anual',
            'precio' => 45000,
            'estado' => 'activo',
        ]);

        $this->socio = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'Ana',
            'apellido' => 'Martínez',
            'dni' => '11223344',
            'sexo' => 'F',
            'correo' => 'ana@example.com',
            'celular' => '1122334455',
            'token' => '11223344',
            'fecha_alta' => today(),
            'fecha_vencimiento' => today()->addMonth(),
            'estado' => 'activo',
        ]);
    }

    /**
     * Test de cálculo automático de fecha de vencimiento (+1 año).
     */
    public function test_autocompletado_fecha_vencimiento(): void
    {
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\AptosFisicos\CrearAptoFisico::class, ['socio' => $this->socio])
            ->set('fecha_emision', '2026-06-08')
            ->assertSet('fecha_vencimiento', '2027-06-08');
    }

    /**
     * Test de validación de archivo por tamaño y formato.
     */
    public function test_validacion_archivo_apto_fisico(): void
    {
        $this->actingAs($this->recepcionista);

        // 1. Archivo PDF válido (dentro del límite)
        $archivoValido = UploadedFile::fake()->create('certificado.pdf', 1024, 'application/pdf');

        Livewire::test(\App\Livewire\AptosFisicos\CrearAptoFisico::class, ['socio' => $this->socio])
            ->set('fecha_emision', '2026-06-08')
            ->set('fecha_vencimiento', '2027-06-08')
            ->set('archivo_cargado', $archivoValido)
            ->call('guardar')
            ->assertHasNoErrors();

        // Verificamos que se guardó en la base de datos
        $this->assertDatabaseHas('aptos_fisicos', [
            'socio_id' => $this->socio->id,
            'estado' => 'vigente',
        ]);

        $apto = AptoFisico::first();
        $this->assertEquals('2026-06-08', \Carbon\Carbon::parse($apto->fecha_emision)->format('Y-m-d'));
        $this->assertEquals('2027-06-08', \Carbon\Carbon::parse($apto->fecha_vencimiento)->format('Y-m-d'));

        // Verificamos que el archivo se guardó de forma privada
        Storage::disk('local')->assertExists($apto->archivo);

        // 2. Archivo inválido (tipo exe)
        $archivoInvalido = UploadedFile::fake()->create('hacker.exe', 500, 'application/x-msdownload');

        Livewire::test(\App\Livewire\AptosFisicos\CrearAptoFisico::class, ['socio' => $this->socio])
            ->set('archivo_cargado', $archivoInvalido)
            ->call('guardar')
            ->assertHasErrors(['archivo_cargado' => 'mimes']);

        // 3. Archivo muy grande (excede 4MB)
        $archivoPesado = UploadedFile::fake()->create('grande.pdf', 5000, 'application/pdf'); // 5MB

        Livewire::test(\App\Livewire\AptosFisicos\CrearAptoFisico::class, ['socio' => $this->socio])
            ->set('archivo_cargado', $archivoPesado)
            ->call('guardar')
            ->assertHasErrors(['archivo_cargado' => 'max']);
    }

    /**
     * Test de validación de fecha de vencimiento anterior a emisión.
     */
    public function test_validacion_fechas_apto_fisico(): void
    {
        $this->actingAs($this->recepcionista);

        Livewire::test(\App\Livewire\AptosFisicos\CrearAptoFisico::class, ['socio' => $this->socio])
            ->set('fecha_emision', '2026-06-08')
            ->set('fecha_vencimiento', '2026-06-07') // Anterior a emisión
            ->call('guardar')
            ->assertHasErrors(['fecha_vencimiento' => 'after_or_equal']);
    }
}
