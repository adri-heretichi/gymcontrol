<?php

namespace Database\Seeders;

use App\Models\Disciplina;
use Illuminate\Database\Seeder;

class DisciplinaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $descripciones = [
            'Krav Maga Kids' => 'Defensa personal y desarrollo psicomotriz para niños',
            'Kapap Krav Maga' => 'Sistema de combate y defensa personal táctica',
            'Kickboxing Muay Thai' => 'Entrenamiento técnico de deportes de contacto y acondicionamiento físico',
            'Funcional' => 'Entrenamiento funcional de fuerza, resistencia y movilidad',
        ];

        foreach (Disciplina::PLANES_FENIX as $nombre) {
            Disciplina::updateOrCreate(
                ['nombre' => $nombre],
                [
                    'descripcion' => $descripciones[$nombre],
                    'estado' => 'activo',
                ]
            );
        }
    }
}
