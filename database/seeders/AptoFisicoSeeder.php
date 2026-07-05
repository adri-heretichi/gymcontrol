<?php

namespace Database\Seeders;

use App\Models\AptoFisico;
use App\Models\Socio;
use Illuminate\Database\Seeder;

class AptoFisicoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtenemos los socios específicos por DNI para vincular sus aptos médicos
        $juanId = Socio::where('dni', '35123456')->first()->id;
        $mariaId = Socio::where('dni', '38987654')->first()->id;
        $diegoId = Socio::where('dni', '36445566')->first()->id;

        // Apto Físico de Juan Pérez (Vigente)
        AptoFisico::updateOrCreate(
            [
                'socio_id' => $juanId,
                'archivo' => 'socios/aptos/apto_juan.pdf'
            ],
            [
                'fecha_emision' => '2026-01-01',
                'fecha_vencimiento' => '2027-01-01', // Vigente
                'estado' => 'vigente',
            ]
        );

        // Apto Físico de María Gómez (Vigente)
        AptoFisico::updateOrCreate(
            [
                'socio_id' => $mariaId,
                'archivo' => 'socios/aptos/apto_maria.pdf'
            ],
            [
                'fecha_emision' => '2026-01-01',
                'fecha_vencimiento' => '2027-01-01', // Vigente
                'estado' => 'vigente',
            ]
        );

        // Apto Físico de Diego López (VENCIDO)
        AptoFisico::updateOrCreate(
            [
                'socio_id' => $diegoId,
                'archivo' => 'socios/aptos/apto_diego_vencido.pdf'
            ],
            [
                'fecha_emision' => '2025-05-01',
                'fecha_vencimiento' => '2026-05-01', // Vencido (Fecha actual simulada: 7-Junio-2026)
                'estado' => 'vencido',
            ]
        );
    }
}
