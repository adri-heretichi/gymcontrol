<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DatabaseSeeder contains development fixtures and cannot run in production.');
        }

        // Llamamos a cada uno de los seeders en orden de dependencia
        $this->call([
            UserSeeder::class,         // 1. Usuarios técnicos (admin/recepcionista)
            DisciplinaSeeder::class,   // 2. Disciplinas del gimnasio
            SocioSeeder::class,        // 3. Socios preliminares sin inscripción ni membresía general
            AptoFisicoSeeder::class,   // 4. Documentación médica histórica
            PagoSeeder::class,         // 5. Historial de pagos
            AsistenciaSeeder::class,   // 6. Historial de asistencias
        ]);
    }
}
