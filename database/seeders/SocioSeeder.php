<?php

namespace Database\Seeders;

use App\Models\Membresia;
use App\Models\Socio;
use Illuminate\Database\Seeder;

class SocioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtenemos los IDs de las membresías creadas en el seeder anterior
        $paseLibreId = Membresia::where('nombre', 'Pase Libre Mensual')->first()->id;
        $tresVecesId = Membresia::where('nombre', 'Pase 3 Veces por Semana')->first()->id;

        // Socio 1: Juan Pérez (Activo y Vigente)
        Socio::updateOrCreate(
            ['dni' => '35123456'], // Condición de búsqueda única
            [
                'membresia_id' => $paseLibreId,
                'apellido' => 'Pérez',
                'nombre' => 'Juan',
                'sexo' => 'M',
                'correo' => 'juan.perez@example.com',
                'celular' => '1122334455',
                'foto' => null,
                'token' => 'TOKEN001',
                'qr' => null,
                'fecha_alta' => '2026-05-01',
                'fecha_vencimiento' => '2026-06-30', // Vigente (Fecha actual simulada: 7-Junio-2026)
                'estado' => 'activo',
            ]
        );

        // Socio 2: María Gómez (Socio con Membresía Vencida)
        Socio::updateOrCreate(
            ['dni' => '38987654'], // Condición de búsqueda única
            [
                'membresia_id' => $tresVecesId,
                'apellido' => 'Gómez',
                'nombre' => 'María',
                'sexo' => 'F',
                'correo' => 'maria.gomez@example.com',
                'celular' => '1199887766',
                'foto' => null,
                'token' => 'TOKEN002',
                'qr' => null,
                'fecha_alta' => '2026-04-10',
                'fecha_vencimiento' => '2026-05-10', // Vencida (en el pasado)
                'estado' => 'activo',
            ]
        );

        // Socio 3: Carlos Rodríguez (Socio Inactivo)
        Socio::updateOrCreate(
            ['dni' => '32112233'], // Condición de búsqueda única
            [
                'membresia_id' => $paseLibreId,
                'apellido' => 'Rodríguez',
                'nombre' => 'Carlos',
                'sexo' => 'M',
                'correo' => 'carlos.rod@example.com',
                'celular' => '1155667788',
                'foto' => null,
                'token' => 'TOKEN003',
                'qr' => null,
                'fecha_alta' => '2026-01-15',
                'fecha_vencimiento' => '2026-06-15', // Fecha al día, pero su estado general es inactivo
                'estado' => 'inactivo',
            ]
        );

        // Socio 4: Ana Fernández (Socio Activo y Vigente)
        Socio::updateOrCreate(
            ['dni' => '41223344'], // Condición de búsqueda única
            [
                'membresia_id' => $paseLibreId,
                'apellido' => 'Fernández',
                'nombre' => 'Ana',
                'sexo' => 'F',
                'correo' => 'ana.fer@example.com',
                'celular' => '1133445566',
                'foto' => null,
                'token' => 'TOKEN004',
                'qr' => null,
                'fecha_alta' => '2026-05-15',
                'fecha_vencimiento' => '2026-06-15', // Vigente
                'estado' => 'activo',
            ]
        );

        // Socio 5: Diego López (Socio con Apto Vencido)
        Socio::updateOrCreate(
            ['dni' => '36445566'], // Condición de búsqueda única
            [
                'membresia_id' => $paseLibreId,
                'apellido' => 'López',
                'nombre' => 'Diego',
                'sexo' => 'M',
                'correo' => 'diego.lopez@example.com',
                'celular' => '1188776655',
                'foto' => null,
                'token' => 'TOKEN005',
                'qr' => null,
                'fecha_alta' => '2026-03-01',
                'fecha_vencimiento' => '2026-06-30', // Membresía al día, pero su apto físico estará vencido
                'estado' => 'activo',
            ]
        );
    }
}
