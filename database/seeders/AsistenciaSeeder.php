<?php

namespace Database\Seeders;

use App\Models\Asistencia;
use App\Models\Socio;
use Illuminate\Database\Seeder;

class AsistenciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $juanId = Socio::where('dni', '35123456')->first()->id;
        $anaId = Socio::where('dni', '41223344')->first()->id;

        // Historial Asistencia 1: Juan Pérez (5 de Junio)
        Asistencia::firstOrCreate(
            [
                'socio_id' => $juanId,
                'fecha' => '2026-06-05',
                'hora_ingreso' => '18:00:00'
            ],
            [
                'hora_salida' => '19:30:00',
                'tiempo_permanencia' => 90, // 90 minutos
            ]
        );

        // Historial Asistencia 2: Juan Pérez (6 de Junio)
        Asistencia::firstOrCreate(
            [
                'socio_id' => $juanId,
                'fecha' => '2026-06-06',
                'hora_ingreso' => '17:15:00'
            ],
            [
                'hora_salida' => '18:45:00',
                'tiempo_permanencia' => 90, // 90 minutos
            ]
        );

        // Asistencia Activa (Dentro del Gym): Juan Pérez (7 de Junio - Hoy)
        // hora_salida y tiempo_permanencia quedan en NULL para simular que está en el local
        Asistencia::firstOrCreate(
            [
                'socio_id' => $juanId,
                'fecha' => '2026-06-07',
                'hora_ingreso' => '15:00:00'
            ],
            [
                'hora_salida' => null,
                'tiempo_permanencia' => null,
            ]
        );

        // Historial Asistencia 3: Ana Fernández (6 de Junio)
        Asistencia::firstOrCreate(
            [
                'socio_id' => $anaId,
                'fecha' => '2026-06-06',
                'hora_ingreso' => '09:00:00'
            ],
            [
                'hora_salida' => '10:15:00',
                'tiempo_permanencia' => 75, // 75 minutos
            ]
        );
    }
}
