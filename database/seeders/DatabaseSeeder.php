<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Instalación inicial: operadores originales y catálogo, sin socios precargados.
        $this->call([
            UserSeeder::class,
            DisciplinaSeeder::class,
        ]);
    }
}
