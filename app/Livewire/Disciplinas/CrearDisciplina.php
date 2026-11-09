<?php

namespace App\Livewire\Disciplinas;

use App\Models\Disciplina;
use App\Models\Auditoria;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class CrearDisciplina extends Component
{
    public $nombre = '';
    public $descripcion = '';
    public $estado = 'activo';

    public function mount()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }
    }

    protected function rules()
    {
        return [
            'nombre' => 'required|string|min:2|max:100|unique:disciplinas,nombre',
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'required|in:activo,inactivo',
        ];
    }

    protected $messages = [
        'nombre.required' => 'El nombre de la disciplina es obligatorio.',
        'nombre.unique' => 'Ya existe una disciplina con ese nombre en el catálogo.',
        'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
        'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
        'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
        'estado.required' => 'El estado es obligatorio.',
        'estado.in' => 'El estado seleccionado es inválido.',
    ];

    public function guardar()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes permisos para registrar disciplinas.');
        }

        $this->validate();

        $disciplina = DB::transaction(function () {
            $disciplina = Disciplina::create([
                'nombre' => trim($this->nombre),
                'descripcion' => $this->descripcion ? trim($this->descripcion) : null,
                'estado' => $this->estado,
            ]);
            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'disciplina_creada',
                entidad: $disciplina,
                datosDespues: [
                    'nombre' => $disciplina->nombre,
                    'descripcion' => $disciplina->descripcion,
                    'estado' => $disciplina->estado,
                ],
                userId: auth()->id()
            );
            return $disciplina;
        }, 3);

        session()->flash('message', "Disciplina '{$this->nombre}' creada correctamente.");
        return redirect()->route('disciplinas.index');
    }

    public function render()
    {
        return view('livewire.disciplinas.crear-disciplina')
            ->layout('layouts.app');
    }
}
