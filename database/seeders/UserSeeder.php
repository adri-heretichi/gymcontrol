<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Administrador del sistema
        User::updateOrCreate(
            ['email' => 'admin@gymcontrol.com'], // Condición de búsqueda
            [
                'name' => 'Administrador General',
                'password' => Hash::make('password'), // Clave encriptada
                'rol' => 'admin',
            ]
        );

        // Recepcionista del sistema
        User::updateOrCreate(
            ['email' => 'recep@gymcontrol.com'], // Condición de búsqueda
            [
                'name' => 'Recepcionista de Turno',
                'password' => Hash::make('password'), // Clave encriptada
                'rol' => 'recepcionista',
            ]
        );
    }
}
