<?php

namespace App\Livewire\Grupos;

use App\Models\Disciplina;
use App\Models\Auditoria;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Services\HorarioGimnasioService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EditarGrupo extends Component
{
    public Grupo $grupo;
    public $disciplina_id = '';
    public $nombre = '';
    public $profesor = '';
    public $cupo_maximo = 15;
    public $estado = 'activo';

    public $horarios = [];

    public function mount(Grupo $grupo)
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }

        $this->grupo = $grupo->load('horarios');
        $this->disciplina_id = $grupo->disciplina_id;
        $this->nombre = $grupo->nombre;
        $this->profesor = $grupo->profesor;
        $this->cupo_maximo = $grupo->cupo_maximo;
        $this->estado = $grupo->estado;

        $this->horarios = $grupo->horarios->map(function ($h) {
            return [
                'dia_semana' => $h->dia_semana,
                'hora_inicio' => substr($h->hora_inicio, 0, 5),
                'hora_fin' => substr($h->hora_fin, 0, 5),
            ];
        })->toArray();

        if (empty($this->horarios)) {
            $this->horarios = [
                [
                    'dia_semana' => 'lunes',
                    'hora_inicio' => '18:00',
                    'hora_fin' => '19:00',
                ],
            ];
        }
    }

    public function agregarHorario()
    {
        $this->horarios[] = [
            'dia_semana' => 'lunes',
            'hora_inicio' => '18:00',
            'hora_fin' => '19:00',
        ];
    }

    public function removerHorario(int $index)
    {
        if (isset($this->horarios[$index])) {
            unset($this->horarios[$index]);
            $this->horarios = array_values($this->horarios);
        }
    }

    protected function rules()
    {
        return [
            'disciplina_id' => 'required|exists:disciplinas,id',
            'nombre' => 'required|string|min:2|max:100',
            'profesor' => 'required|string|max:100',
            'cupo_maximo' => 'required|integer|min:1|max:15',
            'estado' => 'required|in:activo,inactivo',
            'horarios' => 'required|array|min:1',
            'horarios.*.dia_semana' => 'required|string|in:lunes,martes,miercoles,jueves,viernes,sabado,domingo',
            'horarios.*.hora_inicio' => 'required|string',
            'horarios.*.hora_fin' => 'required|string',
        ];
    }

    protected $messages = [
        'disciplina_id.required' => 'Debe seleccionar una disciplina.',
        'disciplina_id.exists' => 'La disciplina seleccionada no existe.',
        'nombre.required' => 'El nombre del grupo es obligatorio.',
        'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
        'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
        'profesor.required' => 'El profesor o profesora a cargo es obligatorio.',
        'cupo_maximo.required' => 'El cupo máximo es obligatorio.',
        'cupo_maximo.integer' => 'El cupo debe ser un número entero.',
        'cupo_maximo.min' => 'El cupo mínimo es de 1 alumno.',
        'cupo_maximo.max' => 'El cupo máximo no puede superar los 15 alumnos por grupo.',
        'estado.required' => 'El estado es obligatorio.',
        'estado.in' => 'El estado seleccionado es inválido.',
        'horarios.required' => 'Debe configurar al menos un horario de clase.',
        'horarios.min' => 'Debe configurar al menos un horario de clase.',
    ];

    public function actualizar()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes permisos para modificar grupos.');
        }

        $this->validate();

        // 1. Proteger que no se reduzca la capacidad por debajo de las inscripciones aprobadas
        $inscriptos = $this->grupo->alumnosInscriptosCount();
        if ((int) $this->cupo_maximo < $inscriptos) {
            $this->addError(
                'cupo_maximo',
                "No es posible reducir el cupo a {$this->cupo_maximo} porque el grupo cuenta actualmente con {$inscriptos} alumnos con inscripción aprobada."
            );
            return;
        }

        // 2. Validar reglas de la colección de horarios
        $errorHorarios = HorarioGimnasioService::validarColeccionHorarios($this->horarios);
        if ($errorHorarios) {
            $this->addError('horarios', $errorHorarios);
            return;
        }

        // 3. Si el grupo está activo, validar superposición con otros grupos activos (ignorando este grupo)
        if ($this->estado === 'activo') {
            foreach ($this->horarios as $h) {
                $conflicto = HorarioGimnasioService::buscarSuperposicionProfesora(
                    $h['dia_semana'],
                    $h['hora_inicio'],
                    $h['hora_fin'],
                    $this->grupo->id
                );

                if ($conflicto) {
                    $diaNombre = ucfirst($h['dia_semana']);
                    $this->addError(
                        'horarios',
                        "Existe superposición horaria con el grupo '{$conflicto->nombre}' de la disciplina '{$conflicto->disciplina->nombre}' el día {$diaNombre} en el rango indicado. La profesora no puede dictar dos clases a la vez."
                    );
                    return;
                }
            }
        }

        // 4. Actualizar el grupo y recrear sus horarios
        $antes = [
            'disciplina_id' => $this->grupo->disciplina_id,
            'nombre' => $this->grupo->nombre,
            'profesor' => $this->grupo->profesor,
            'cupo_maximo' => $this->grupo->cupo_maximo,
            'estado' => $this->grupo->estado,
            'horarios' => $this->grupo->horarios->map(fn ($horario) => [
                'dia_semana' => $horario->dia_semana,
                'hora_inicio' => $horario->hora_inicio,
                'hora_fin' => $horario->hora_fin,
            ])->all(),
        ];

        DB::transaction(function () use ($antes) {
            $this->grupo->update([
                'disciplina_id' => $this->disciplina_id,
                'nombre' => trim($this->nombre),
                'profesor' => trim($this->profesor),
                'cupo_maximo' => (int) $this->cupo_maximo,
                'estado' => $this->estado,
            ]);

            $this->grupo->horarios()->delete();

            foreach ($this->horarios as $h) {
                GrupoHorario::create([
                    'grupo_id' => $this->grupo->id,
                    'dia_semana' => strtolower(trim($h['dia_semana'])),
                    'hora_inicio' => HorarioGimnasioService::normalizarHora($h['hora_inicio']),
                    'hora_fin' => HorarioGimnasioService::normalizarHora($h['hora_fin']),
                ]);
            }

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'grupo_actualizado',
                entidad: $this->grupo,
                datosAntes: $antes,
                datosDespues: [
                    'disciplina_id' => $this->grupo->disciplina_id,
                    'nombre' => $this->grupo->nombre,
                    'profesor' => $this->grupo->profesor,
                    'cupo_maximo' => $this->grupo->cupo_maximo,
                    'estado' => $this->grupo->estado,
                    'horarios' => $this->grupo->horarios()->get(['dia_semana', 'hora_inicio', 'hora_fin'])->toArray(),
                ],
                userId: auth()->id()
            );
        });

        session()->flash('message', "Grupo '{$this->grupo->nombre}' actualizado exitosamente.");
        return redirect()->route('grupos.index');
    }

    public function render()
    {
        $disciplinas = Disciplina::where('estado', 'activo')
            ->orWhere('id', $this->grupo->disciplina_id)
            ->orderBy('nombre')
            ->get();
        $diasSemana = HorarioGimnasioService::DIAS_SEMANA;

        return view('livewire.grupos.editar-grupo', [
            'disciplinas' => $disciplinas,
            'diasSemana' => $diasSemana,
        ])->layout('layouts.app');
    }
}
