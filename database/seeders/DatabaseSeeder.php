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
            MembresiaSeeder::class,    // 3. Planes de membresía
            SocioSeeder::class,        // 4. Socios (requiere membresías)
            AptoFisicoSeeder::class,    // 5. Aptos médicos (requiere socios)
            PagoSeeder::class,          // 6. Historial de pagos (requiere socios)
            AsistenciaSeeder::class,    // 7. Historial de asistencias (requiere socios)
        ]);
    }
}
