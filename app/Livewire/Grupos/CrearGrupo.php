<?php

namespace App\Livewire\Grupos;

use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Auditoria;
use App\Services\HorarioGimnasioService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CrearGrupo extends Component
{
    public $disciplina_id = '';
    public $nombre = '';
    public $profesor = '';
    public $cupo_maximo = 15;
    public $estado = 'activo';

    public $horarios = [];

    public function mount()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }

        $this->profesor = \App\Models\User::nombreProfesora();

        // Fila inicial por defecto
        $this->horarios = [
            [
                'dia_semana' => 'lunes',
                'hora_inicio' => '18:00',
                'hora_fin' => '19:00',
            ],
        ];
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

    public function guardar()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes permisos para crear grupos.');
        }

        $this->validate();

        // 1. Validar reglas de la colección de horarios (horarios dentro de turnos, inicio < fin, sin superposición interna)
        $errorHorarios = HorarioGimnasioService::validarColeccionHorarios($this->horarios);
        if ($errorHorarios) {
            $this->addError('horarios', $errorHorarios);
            return;
        }

        // 2. Si el grupo se crea como activo, validar superposición con otros grupos activos de la profesora
        if ($this->estado === 'activo') {
            foreach ($this->horarios as $h) {
                $conflicto = HorarioGimnasioService::buscarSuperposicionProfesora(
                    $h['dia_semana'],
                    $h['hora_inicio'],
                    $h['hora_fin']
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

        // 3. Crear el grupo y sus horarios en una transacción
        DB::transaction(function () {
            $grupo = Grupo::create([
                'disciplina_id' => $this->disciplina_id,
                'nombre' => trim($this->nombre),
                'profesor' => trim($this->profesor),
                'cupo_maximo' => (int) $this->cupo_maximo,
                'estado' => $this->estado,
            ]);

            foreach ($this->horarios as $h) {
                GrupoHorario::create([
                    'grupo_id' => $grupo->id,
                    'dia_semana' => strtolower(trim($h['dia_semana'])),
                    'hora_inicio' => HorarioGimnasioService::normalizarHora($h['hora_inicio']),
                    'hora_fin' => HorarioGimnasioService::normalizarHora($h['hora_fin']),
                ]);
            }

            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'grupo_creado',
                entidad: $grupo,
                datosDespues: [
                    'disciplina_id' => $grupo->disciplina_id,
                    'nombre' => $grupo->nombre,
                    'profesor' => $grupo->profesor,
                    'cupo_maximo' => $grupo->cupo_maximo,
                    'estado' => $grupo->estado,
                    'horarios' => $grupo->horarios()->get(['dia_semana', 'hora_inicio', 'hora_fin'])->toArray(),
                ],
                userId: auth()->id()
            );
        });

        session()->flash('message', "Grupo '{$this->nombre}' creado exitosamente con cupo de {$this->cupo_maximo} alumnos.");
        return redirect()->route('grupos.index');
    }

    public function render()
    {
        $disciplinas = Disciplina::where('estado', 'activo')->orderBy('nombre')->get();
        $diasSemana = HorarioGimnasioService::DIAS_SEMANA;

        return view('livewire.grupos.crear-grupo', [
            'disciplinas' => $disciplinas,
            'diasSemana' => $diasSemana,
        ])->layout('layouts.app');
    }
}
