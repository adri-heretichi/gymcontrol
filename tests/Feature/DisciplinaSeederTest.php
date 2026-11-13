<?php

namespace Tests\Feature;

use App\Models\Disciplina;
use Database\Seeders\DisciplinaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisciplinaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_idempotent_and_preserves_existing_prices_descriptions_and_states(): void
    {
        $existente = Disciplina::create([
            'nombre' => 'Krav Maga Kids', 'descripcion' => 'Configuración propia',
            'precio' => 42000, 'estado' => 'inactivo',
        ]);
        $sinPrecio = Disciplina::create(['nombre' => 'Funcional', 'precio' => null, 'estado' => 'activo']);
        $antes = $existente->fresh()->getAttributes();
        $antesSinPrecio = $sinPrecio->fresh()->getAttributes();

        $this->seed(DisciplinaSeeder::class);
        $this->seed(DisciplinaSeeder::class);

        $this->assertDatabaseCount('disciplinas', 4);
        $this->assertSame($antes, $existente->fresh()->getAttributes());
        $this->assertSame($antesSinPrecio, $sinPrecio->fresh()->getAttributes());
        $this->assertDatabaseHas('disciplinas', ['nombre' => 'Kapap Krav Maga', 'precio' => 35000]);
        foreach (['socios', 'users', 'grupos', 'grupo_horarios', 'inscripciones', 'pagos', 'pago_detalles', 'aptos_fisicos', 'asistencias'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
    }
}
