<?php

namespace App\Livewire\Inscripciones;

use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Socio;
use App\Services\InscripcionService;
use DomainException;
use Livewire\Component;

class CrearInscripcion extends Component
{
    public $socio_id = '';
    public $buscarSocio = '';
    public $disciplina_id = '';
    public $grupo_id = '';
    public $observaciones = '';

    public function mount()
    {
        if (!auth()->check()) {
            abort(403, 'Sesión requerida.');
        }

        // Si viene un socio preseleccionado en la URL
        $preId = request()->query('socio_id');
        if ($preId) {
            $socio = Socio::find($preId);
            if ($socio) {
                $this->socio_id = $socio->id;
                $this->buscarSocio = "{$socio->nombre_completo} (DNI {$socio->dni})";
            }
        }
    }

    public function seleccionarSocio(int $id)
    {
        $socio = Socio::findOrFail($id);
        $this->socio_id = $socio->id;
        $this->buscarSocio = "{$socio->nombre_completo} (DNI {$socio->dni})";
    }

    public function limpiarSocio()
    {
        $this->socio_id = '';
        $this->buscarSocio = '';
    }

    public function updatedDisciplinaId()
    {
        $this->grupo_id = '';
    }

    protected function rules()
    {
        return [
            'socio_id' => 'required|exists:socios,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'grupo_id' => 'required|exists:grupos,id',
            'observaciones' => 'nullable|string|max:500',
        ];
    }

    protected $messages = [
        'socio_id.required' => 'Debe seleccionar un socio.',
        'socio_id.exists' => 'El socio seleccionado no existe.',
        'disciplina_id.required' => 'Debe seleccionar una disciplina.',
        'disciplina_id.exists' => 'La disciplina seleccionada no existe.',
        'grupo_id.required' => 'Debe seleccionar un grupo de disciplina.',
        'grupo_id.exists' => 'El grupo seleccionado no existe.',
        'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',
    ];

    public function guardar()
    {
        $this->validate();

        $socio = Socio::findOrFail($this->socio_id);
        $grupo = Grupo::with('disciplina', 'horarios')
            ->whereKey($this->grupo_id)
            ->where('disciplina_id', $this->disciplina_id)
            ->where('estado', 'activo')
            ->whereHas('disciplina', fn ($query) => $query->where('estado', 'activo'))
            ->whereHas('horarios')
            ->first();

        if (!$grupo) {
            $this->addError('grupo_id', 'El grupo debe pertenecer a la disciplina seleccionada, estar activo y tener horarios configurados.');
            return;
        }

        $service = app(InscripcionService::class);

        try {
            $resultado = $service->solicitarInscripcion(
                $socio,
                $grupo,
                auth()->user(),
                $this->observaciones
            );

            session()->flash('message', $resultado['mensaje']);
            return redirect()->route('inscripciones.index');
        } catch (DomainException $e) {
            $this->addError('grupo_id', $e->getMessage());
        } catch (\Exception $e) {
            $this->addError('grupo_id', 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $sociosSugeridos = [];
        if (!$this->socio_id && strlen(trim($this->buscarSocio)) >= 2) {
            $sociosSugeridos = Socio::where('estado', 'activo')
                ->where(function ($q) {
                    $q->where('nombre', 'like', '%' . $this->buscarSocio . '%')
                      ->orWhere('apellido', 'like', '%' . $this->buscarSocio . '%')
                      ->orWhere('dni', 'like', '%' . $this->buscarSocio . '%');
                })
                ->limit(6)
                ->get();
        }

        $disciplinas = Disciplina::where('estado', 'activo')->orderBy('nombre')->get();

        $grupos = [];
        if ($this->disciplina_id) {
            $grupos = Grupo::where('disciplina_id', $this->disciplina_id)
                ->where('estado', 'activo')
                ->whereHas('disciplina', fn ($query) => $query->where('estado', 'activo'))
                ->whereHas('horarios')
                ->with('horarios')
                ->orderBy('nombre')
                ->get();
        }

        $grupoSeleccionado = $this->grupo_id && $this->disciplina_id
            ? Grupo::with(['horarios', 'disciplina'])
                ->whereKey($this->grupo_id)
                ->where('disciplina_id', $this->disciplina_id)
                ->where('estado', 'activo')
                ->whereHas('horarios')
                ->whereHas('disciplina', fn ($query) => $query->where('estado', 'activo'))
                ->first()
            : null;

        return view('livewire.inscripciones.crear-inscripcion', [
            'sociosSugeridos' => $sociosSugeridos,
            'disciplinas' => $disciplinas,
            'grupos' => $grupos,
            'grupoSeleccionado' => $grupoSeleccionado,
        ])->layout('layouts.app');
    }
}
