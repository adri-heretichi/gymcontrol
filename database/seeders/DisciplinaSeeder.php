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
        $disciplinas = [
            [
                'nombre' => 'Krav Maga Kids',
                'descripcion' => 'Defensa personal y desarrollo psicomotriz para niños',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Kapap Krav Maga',
                'descripcion' => 'Sistema de combate y defensa personal táctica',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Kickboxing Muay Thai',
                'descripcion' => 'Entrenamiento técnico de deportes de contacto y acondicionamiento físico',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Funcional',
                'descripcion' => 'Entrenamiento funcional de fuerza, resistencia y movilidad',
                'estado' => 'activo',
            ],
        ];

        foreach ($disciplinas as $d) {
            Disciplina::updateOrCreate(
                ['nombre' => $d['nombre']],
                [
                    'descripcion' => $d['descripcion'],
                    'estado' => $d['estado'],
                ]
            );
        }
    }
}
