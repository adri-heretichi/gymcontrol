<?php

namespace Database\Seeders;

use App\Models\Socio;
use Illuminate\Database\Seeder;

class SocioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Estos fixtures representan registros administrativos; no crean inscripciones
        // ni implican habilitación para ingresar.

        // Socio 1: Juan Pérez (administrativamente activo)
        Socio::updateOrCreate(
            ['dni' => '35123456'], // Condición de búsqueda única
            [
                'membresia_id' => null,
                'apellido' => 'Pérez',
                'nombre' => 'Juan',
                'sexo' => 'M',
                'correo' => 'juan.perez@example.com',
                'celular' => '1122334455',
                'foto' => null,
                'token' => 'TOKEN001',
                'qr' => null,
                'fecha_alta' => '2026-05-01',
                'fecha_vencimiento' => null,
                'estado' => 'activo',
            ]
        );

        // Socio 2: María Gómez (administrativamente activo)
        Socio::updateOrCreate(
            ['dni' => '38987654'], // Condición de búsqueda única
            [
                'membresia_id' => null,
                'apellido' => 'Gómez',
                'nombre' => 'María',
                'sexo' => 'F',
                'correo' => 'maria.gomez@example.com',
                'celular' => '1199887766',
                'foto' => null,
                'token' => 'TOKEN002',
                'qr' => null,
                'fecha_alta' => '2026-04-10',
                'fecha_vencimiento' => null,
                'estado' => 'activo',
            ]
        );

        // Socio 3: Carlos Rodríguez (administrativamente inactivo)
        Socio::updateOrCreate(
            ['dni' => '32112233'], // Condición de búsqueda única
            [
                'membresia_id' => null,
                'apellido' => 'Rodríguez',
                'nombre' => 'Carlos',
                'sexo' => 'M',
                'correo' => 'carlos.rod@example.com',
                'celular' => '1155667788',
                'foto' => null,
                'token' => 'TOKEN003',
                'qr' => null,
                'fecha_alta' => '2026-01-15',
                'fecha_vencimiento' => null,
                'estado' => 'inactivo',
            ]
        );

        // Socio 4: Ana Fernández (administrativamente activa)
        Socio::updateOrCreate(
            ['dni' => '41223344'], // Condición de búsqueda única
            [
                'membresia_id' => null,
                'apellido' => 'Fernández',
                'nombre' => 'Ana',
                'sexo' => 'F',
                'correo' => 'ana.fer@example.com',
                'celular' => '1133445566',
                'foto' => null,
                'token' => 'TOKEN004',
                'qr' => null,
                'fecha_alta' => '2026-05-15',
                'fecha_vencimiento' => null,
                'estado' => 'activo',
            ]
        );

        // Socio 5: Diego López (administrativamente activo)
        Socio::updateOrCreate(
            ['dni' => '36445566'], // Condición de búsqueda única
            [
                'membresia_id' => null,
                'apellido' => 'López',
                'nombre' => 'Diego',
                'sexo' => 'M',
                'correo' => 'diego.lopez@example.com',
                'celular' => '1188776655',
                'foto' => null,
                'token' => 'TOKEN005',
                'qr' => null,
                'fecha_alta' => '2026-03-01',
                'fecha_vencimiento' => null,
                'estado' => 'activo',
            ]
        );
    }
}
