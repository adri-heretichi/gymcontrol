<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Socio;
use App\Models\Membresia;
use App\Models\AptoFisico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AptoFisicoPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recepcionista;
    private Socio $socio;
    private AptoFisico $aptoFisico;

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
            'nombre' => 'Pase Libre',
            'precio' => 6000,
            'estado' => 'activo',
        ]);

        $this->socio = Socio::create([
            'membresia_id' => $membresia->id,
            'nombre' => 'María',
            'apellido' => 'Gómez',
            'dni' => '87654321',
            'sexo' => 'F',
            'correo' => 'maria@example.com',
            'celular' => '1122334455',
            'token' => '87654321',
            'fecha_alta' => today(),
            'fecha_vencimiento' => today()->addMonth(),
            'estado' => 'activo',
        ]);

        $this->aptoFisico = AptoFisico::create([
            'socio_id' => $this->socio->id,
            'archivo' => 'secure/aptos_fisicos/test.pdf',
            'fecha_emision' => today()->subMonth(),
            'fecha_vencimiento' => today()->addMonths(11),
            'estado' => 'vigente',
        ]);

        Storage::disk('local')->put($this->aptoFisico->archivo, 'medical document');
    }

    /** Reception cannot access any medical record or document workflow. */
    public function test_recepcionista_permisos_apto_fisico(): void
    {
        $this->actingAs($this->recepcionista);

        $this->get('/aptos-fisicos')->assertForbidden();
        $this->get('/socios/' . $this->socio->id . '/aptos-fisicos/crear')->assertForbidden();
        $this->get('/aptos-fisicos/' . $this->aptoFisico->id . '/editar')->assertForbidden();
        $this->get('/aptos-fisicos/' . $this->aptoFisico->id . '/descargar')->assertForbidden();

        Livewire::test(\App\Livewire\AptosFisicos\ListarAptosFisicos::class)->assertStatus(403);
        Livewire::test(\App\Livewire\AptosFisicos\CrearAptoFisico::class, ['socio' => $this->socio])->assertStatus(403);
        Livewire::test(\App\Livewire\AptosFisicos\EditarAptoFisico::class, ['aptoFisico' => $this->aptoFisico])->assertStatus(403);
    }

    /**
     * Test de que el Administrador tiene control total.
     */
    public function test_administrador_permisos_apto_fisico(): void
    {
        $this->actingAs($this->admin);

        // Puede ver, crear y editar
        $this->get('/aptos-fisicos')->assertStatus(200);
        $this->get('/socios/' . $this->socio->id . '/aptos-fisicos/crear')->assertStatus(200);
        $this->get('/aptos-fisicos/' . $this->aptoFisico->id . '/editar')->assertStatus(200);
        $this->get('/aptos-fisicos/' . $this->aptoFisico->id . '/descargar')->assertStatus(200);
    }

    public function test_recepcion_ficha_socio_no_expone_observaciones_ni_historial_medico(): void
    {
        $this->socio->update([
            'decision_medica' => 'requerido',
            'decision_medica_obs' => 'Observación clínica privada de prueba',
        ]);

        $this->actingAs($this->recepcionista)
            ->get(route('socios.show', $this->socio))
            ->assertOk()
            ->assertDontSee('Observación clínica privada de prueba')
            ->assertDontSee('Ver Certificado')
            ->assertDontSee($this->aptoFisico->fecha_vencimiento->format('d/m/Y'));
    }
}
