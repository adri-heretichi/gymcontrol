<?php

namespace App\Services;

use App\Models\Grupo;
use Carbon\Carbon;

class HorarioGimnasioService
{
    /**
     * Períodos oficiales de funcionamiento del gimnasio:
     * Turno Mañana: 08:00 a 12:00
     * Turno Tarde/Noche: 15:00 a 22:00
     */
    public const TURNOS = [
        [
            'nombre' => 'Turno Mañana',
            'inicio' => '08:00:00',
            'fin' => '12:00:00',
        ],
        [
            'nombre' => 'Turno Tarde/Noche',
            'inicio' => '15:00:00',
            'fin' => '22:00:00',
        ],
    ];

    public const DIAS_SEMANA = [
        'lunes' => 'Lunes',
        'martes' => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves' => 'Jueves',
        'viernes' => 'Viernes',
        'sabado' => 'Sábado',
        'domingo' => 'Domingo',
    ];

    /**
     * Verifica si un intervalo de clase (inicio y fin) está estrictamente
     * dentro de alguno de los turnos de apertura del gimnasio.
     */
    public static function estaDentroDelHorarioApertura(string $horaInicio, string $horaFin): bool
    {
        $inicio = self::normalizarHora($horaInicio);
        $fin = self::normalizarHora($horaFin);

        if ($inicio >= $fin) {
            return false;
        }

        foreach (self::TURNOS as $turno) {
            // El horario de clase debe comenzar dentro del turno y terminar dentro del mismo turno
            if ($inicio >= $turno['inicio'] && $fin <= $turno['fin']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comprueba si la profesora (única en el gimnasio) ya tiene otro grupo activo
     * con un horario superpuesto en el mismo día de la semana.
     * Retorna el grupo en conflicto si existe, o null si está libre.
     */
    public static function buscarSuperposicionProfesora(
        string $diaSemana,
        string $horaInicio,
        string $horaFin,
        ?int $grupoIdIgnorar = null
    ): ?Grupo {
        $dia = strtolower(trim($diaSemana));
        $inicio = self::normalizarHora($horaInicio);
        $fin = self::normalizarHora($horaFin);

        return Grupo::where('estado', 'activo')
            ->when($grupoIdIgnorar, fn($q) => $q->where('id', '!=', $grupoIdIgnorar))
            ->whereHas('horarios', function ($q) use ($dia, $inicio, $fin) {
                $q->where('dia_semana', $dia)
                  ->where('hora_inicio', '<', $fin)
                  ->where('hora_fin', '>', $inicio);
            })
            ->with(['disciplina', 'horarios'])
            ->first();
    }

    /**
     * Valida una colección de horarios para un mismo grupo:
     * - Al menos un horario
     * - hora_inicio < hora_fin
     * - Dentro del horario de apertura
     * - Sin superposiciones internas en el mismo día
     * Retorna mensaje de error o null si es válido.
     */
    public static function validarColeccionHorarios(array $horarios): ?string
    {
        if (empty($horarios)) {
            return 'Debe asignar al menos un día y horario de clase.';
        }

        $normalizados = [];
        foreach ($horarios as $idx => $h) {
            $dia = strtolower(trim($h['dia_semana'] ?? ''));
            $inicio = trim($h['hora_inicio'] ?? '');
            $fin = trim($h['hora_fin'] ?? '');

            if (!$dia || !array_key_exists($dia, self::DIAS_SEMANA)) {
                return 'El día indicado en el horario #' . ($idx + 1) . ' es inválido.';
            }

            if (!$inicio || !$fin) {
                return 'Debe especificar la hora de inicio y fin en el horario #' . ($idx + 1) . '.';
            }

            $inicioNorm = self::normalizarHora($inicio);
            $finNorm = self::normalizarHora($fin);

            if ($inicioNorm >= $finNorm) {
                return "La hora de inicio ({$inicio}) debe ser anterior a la hora de fin ({$fin}).";
            }

            if (!self::estaDentroDelHorarioApertura($inicioNorm, $finNorm)) {
                return "El horario {$inicio} a {$fin} está fuera de los turnos de apertura del gimnasio (08:00 a 12:00 o 15:00 a 22:00).";
            }

            // Verificar contra los horarios ya procesados en la misma colección
            foreach ($normalizados as $prev) {
                if ($prev['dia'] === $dia) {
                    if ($prev['inicio'] < $finNorm && $prev['fin'] > $inicioNorm) {
                        return 'Hay dos horarios superpuestos el día ' . ucfirst($dia) . ' para este mismo grupo.';
                    }
                }
            }

            $normalizados[] = [
                'dia' => $dia,
                'inicio' => $inicioNorm,
                'fin' => $finNorm,
            ];
        }

        return null;
    }

    /**
     * Normaliza cadenas de hora a formato HH:MM:SS.
     */
    public static function normalizarHora(string $hora): string
    {
        $h = trim($hora);
        if (strlen($h) === 5) { // HH:MM
            return $h . ':00';
        }
        return $h;
    }
}
