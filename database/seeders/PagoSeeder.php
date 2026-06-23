<?php

namespace Database\Seeders;

use App\Models\Pago;
use App\Models\Socio;
use Illuminate\Database\Seeder;

class PagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $juanId = Socio::where('dni', '35123456')->first()->id;
        $mariaId = Socio::where('dni', '38987654')->first()->id;
        $anaId = Socio::where('dni', '41223344')->first()->id;

        // Pagos de Juan Pérez (Pase Libre - $15000)
        Pago::updateOrCreate(
            [
                'socio_id' => $juanId,
                'fecha_pago' => '2026-05-01',
                'importe' => 15000.00
            ],
            [
                'metodo_pago' => 'efectivo',
            ]
        );

        Pago::updateOrCreate(
            [
                'socio_id' => $juanId,
                'fecha_pago' => '2026-06-01',
                'importe' => 15000.00
            ],
            [
                'metodo_pago' => 'transferencia',
            ]
        );

        // Pagos de María Gómez (Pase 3 Veces - $10000)
        Pago::updateOrCreate(
            [
                'socio_id' => $mariaId,
                'fecha_pago' => '2026-04-10',
                'importe' => 10000.00
            ],
            [
                'metodo_pago' => 'tarjeta',
            ]
        );

        // Pagos de Ana Fernández (Pase Libre - $15000)
        Pago::updateOrCreate(
            [
                'socio_id' => $anaId,
                'fecha_pago' => '2026-05-15',
                'importe' => 15000.00
            ],
            [
                'metodo_pago' => 'transferencia',
            ]
        );
    }
}
