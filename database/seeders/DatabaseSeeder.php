<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Cargar datos iniciales en la base de datos.
     */
    public function run(): void
    {
        // Llamamos a cada uno de los seeders en orden de dependencia
        $this->call([
            UserSeeder::class,         // 1. Usuarios técnicos (admin/recepcionista)
            MembresiaSeeder::class,    // 2. Planes de membresía
            SocioSeeder::class,        // 3. Socios (requiere membresías)
            AptoFisicoSeeder::class,    // 4. Aptos médicos (requiere socios)
            PagoSeeder::class,          // 5. Historial de pagos (requiere socios)
            AsistenciaSeeder::class,    // 6. Historial de asistencias (requiere socios)
        ]);
    }
}
