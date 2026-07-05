<?php

namespace Database\Seeders;

use App\Models\Membresia;
use Illuminate\Database\Seeder;

class MembresiaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Membresía Pase Libre
        Membresia::updateOrCreate(
            ['nombre' => 'Pase Libre Mensual'], // Condición de búsqueda
            [
                'precio' => 15000.00,
                'horas_mensuales' => null, // null representa horas ilimitadas
                'estado' => 'activo',
            ]
        );

        // Membresía Tres Veces por Semana
        Membresia::updateOrCreate(
            ['nombre' => 'Pase 3 Veces por Semana'], // Condición de búsqueda
            [
                'precio' => 10000.00,
                'horas_mensuales' => 40, // 40 horas al mes
                'estado' => 'activo',
            ]
        );

        // Pase Diario
        Membresia::updateOrCreate(
            ['nombre' => 'Pase Diario'], // Condición de búsqueda
            [
                'precio' => 1500.00,
                'horas_mensuales' => 2, // Límite de 2 horas en el día
                'estado' => 'activo',
            ]
        );
    }
}
