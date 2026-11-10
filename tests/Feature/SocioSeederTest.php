<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use App\Models\Socio;
use Database\Seeders\SocioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_socios_are_administrative_records_without_general_membership_or_access(): void
    {
        app(SocioSeeder::class)->run();

        $this->assertDatabaseCount('socios', 5);
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertSame(4, Socio::where('estado', 'activo')->count());
        $this->assertSame(1, Socio::where('estado', 'inactivo')->count());
        $this->assertSame(0, Socio::whereNotNull('membresia_id')->count());
        $this->assertSame(0, Socio::whereNotNull('fecha_vencimiento')->count());

        $socioActivo = Socio::where('estado', 'activo')->firstOrFail();
        $this->assertSame(
            'sin_inscripcion_aprobada',
            app(\App\Services\CredencialService::class)
                ->evaluarRequisitosEmision($socioActivo)['codigo']
        );

        $this->assertSame(0, Inscripcion::count());
    }
}
