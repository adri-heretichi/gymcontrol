<?php

namespace App\Livewire\Disciplinas;

use App\Models\Disciplina;
use App\Models\Auditoria;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class EditarDisciplina extends Component
{
    public Disciplina $disciplina;
    public $nombre = '';
    public $descripcion = '';
    public $estado = '';

    public function mount(Disciplina $disciplina)
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }

        $this->disciplina = $disciplina;
        $this->nombre = $disciplina->nombre;
        $this->descripcion = $disciplina->descripcion ?? '';
        $this->estado = $disciplina->estado;
    }

    protected function rules()
    {
        return [
            'nombre' => 'required|string|min:2|max:100|unique:disciplinas,nombre,' . $this->disciplina->id,
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'required|in:activo,inactivo',
        ];
    }

    protected $messages = [
        'nombre.required' => 'El nombre de la disciplina es obligatorio.',
        'nombre.unique' => 'Ya existe otra disciplina con ese nombre.',
        'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
        'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
        'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
        'estado.required' => 'El estado es obligatorio.',
        'estado.in' => 'El estado seleccionado es inválido.',
    ];

    public function actualizar()
    {
        if (!auth()->check() || auth()->user()->rol !== 'admin') {
            abort(403, 'No tienes permisos para modificar disciplinas.');
        }

        $this->validate();

        $antes = [
            'nombre' => $this->disciplina->nombre,
            'descripcion' => $this->disciplina->descripcion,
            'estado' => $this->disciplina->estado,
        ];
        DB::transaction(function () use ($antes) {
            $this->disciplina->update([
                'nombre' => trim($this->nombre),
                'descripcion' => $this->descripcion ? trim($this->descripcion) : null,
                'estado' => $this->estado,
            ]);
            Auditoria::registrar(
                tipoEvento: 'administrativo',
                accion: 'disciplina_actualizada',
                entidad: $this->disciplina,
                datosAntes: $antes,
                datosDespues: [
                    'nombre' => $this->disciplina->nombre,
                    'descripcion' => $this->disciplina->descripcion,
                    'estado' => $this->disciplina->estado,
                ],
                userId: auth()->id()
            );
        }, 3);

        session()->flash('message', "Disciplina '{$this->disciplina->nombre}' actualizada correctamente.");
        return redirect()->route('disciplinas.index');
    }

    public function render()
    {
        return view('livewire.disciplinas.editar-disciplina')
            ->layout('layouts.app');
    }
}
