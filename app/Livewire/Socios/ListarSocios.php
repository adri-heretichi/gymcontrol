<?php

namespace App\Livewire\Socios;

use App\Models\Socio;
use App\Models\Inscripcion;
use App\Services\CredencialService;
use Livewire\Component;
use Livewire\WithPagination;

class ListarSocios extends Component
{
    use WithPagination;

    // Propiedad enlazada al input del buscador
    public $buscar = '';

    // Propiedades preparadas para el futuro ordenamiento
    public $ordenarPor = 'apellido';
    public $ordenarDireccion = 'asc';

    // Fuerza a Livewire a usar el tema de paginación de Tailwind CSS
    protected $paginationTheme = 'tailwind';

    // Mantiene el filtro de búsqueda en la URL del navegador
    protected $queryString = [
        'buscar' => ['except' => ''],
        'ordenarPor' => ['except' => 'apellido'],
        'ordenarDireccion' => ['except' => 'asc'],
    ];

    // Reinicia a la página 1 al escribir una nueva búsqueda
    public function updatingBuscar()
    {
        $this->resetPage();
    }

    /**
     * Inactiva o activa lógicamente a un socio.
     * Acción restringida únicamente para Administradores.
     */
    public function alternarEstado($socioId)
    {
        // 1. Validación de sesión activa (Seguridad)
        if (!auth()->check()) {
            abort(403, 'Sesión no activa.');
        }

        // 2. Validación estricta de rol en backend
        if (auth()->user()->rol !== 'admin') {
            session()->flash('error', 'No tienes permisos para modificar el estado del socio.');
            return;
        }

        // 3. Modificación del registro
        $socio = Socio::findOrFail($socioId);
        $socio->estado = ($socio->estado === 'activo') ? 'inactivo' : 'activo';
        $socio->save();

        session()->flash('message', "El estado del socio {$socio->nombre} {$socio->apellido} ha sido actualizado a: " . strtoupper($socio->estado));
    }

    public function render()
    {
        // Consulta optimizada con buscador y conteo de inscripciones vigentes.
        $socios = Socio::withCount('inscripciones')
            ->with('inscripciones:id,socio_id,estado')
            ->where(function ($query) {
                $query->where('nombre', 'like', '%' . $this->buscar . '%')
                      ->orWhere('apellido', 'like', '%' . $this->buscar . '%')
                      ->orWhere('dni', 'like', '%' . $this->buscar . '%')
                      ->orWhere('correo', 'like', '%' . $this->buscar . '%')
                      ->orWhere('celular', 'like', '%' . $this->buscar . '%')
                      ->orWhere('token', 'like', '%' . $this->buscar . '%');
            })
            // Estructura de ordenación dinámica (lista para el futuro)
            ->orderBy($this->ordenarPor, $this->ordenarDireccion)
            ->paginate(10);

        $estadosAcceso = $socios->getCollection()->mapWithKeys(
            fn (Socio $socio) => [$socio->id => $this->estadoAccesoParaListado($socio)]
        );

        return view('livewire.socios.listar-socios', [
            'socios' => $socios,
            'estadosAcceso' => $estadosAcceso,
        ])->layout('layouts.app');
    }

    /**
     * Resume requisitos previos sin afirmar que el ingreso esté autorizado ahora:
     * el control de acceso también valida credencial, actividad y horario.
     *
     * @return array{etiqueta: string, detalle: string, tipo: string}
     */
    private function estadoAccesoParaListado(Socio $socio): array
    {
        if ($socio->estado !== 'activo') {
            return [
                'etiqueta' => 'Administrativamente inactivo',
                'detalle' => 'No puede ingresar mientras se mantenga inactivo.',
                'tipo' => 'bloqueado',
            ];
        }

        $inscripciones = $socio->inscripciones;
        if ($inscripciones->isEmpty()) {
            return [
                'etiqueta' => 'Registro preliminar',
                'detalle' => 'Sin inscripción a disciplina y grupo; todavía no puede ingresar.',
                'tipo' => 'pendiente',
            ];
        }

        $inscripcionesAprobadas = $inscripciones->where('estado', Inscripcion::ESTADO_APROBADA);
        if ($inscripcionesAprobadas->isEmpty()) {
            if ($inscripciones->contains('estado', Inscripcion::ESTADO_PENDIENTE)) {
                return [
                    'etiqueta' => 'Inscripción pendiente',
                    'detalle' => 'La inscripción requiere aprobación de la dueña.',
                    'tipo' => 'pendiente',
                ];
            }

            return [
                'etiqueta' => 'Sin inscripción aprobada',
                'detalle' => 'No puede ingresar sin una inscripción aprobada.',
                'tipo' => 'pendiente',
            ];
        }

        $evaluacion = app(CredencialService::class)->evaluarRequisitosEmision($socio);

        return match ($evaluacion['codigo']) {
            'cobertura_pendiente' => [
                'etiqueta' => 'Pendiente de pago/cobertura',
                'detalle' => 'La inscripción aprobada necesita una cobertura pagada vigente.',
                'tipo' => 'pendiente',
            ],
            'decision_medica_pendiente' => [
                'etiqueta' => 'Pendiente de decisión médica',
                'detalle' => 'La dueña debe definir si requiere certificado.',
                'tipo' => 'pendiente',
            ],
            'documentacion_medica_pendiente' => [
                'etiqueta' => 'Documentación médica pendiente',
                'detalle' => 'Falta un certificado vigente o una excepción autorizada por la dueña.',
                'tipo' => 'pendiente',
            ],
            'requisitos_completos' => [
                'etiqueta' => 'Requisitos previos completos',
                'detalle' => 'El ingreso aún valida credencial activa, grupo y horario.',
                'tipo' => 'completo',
            ],
            default => [
                'etiqueta' => 'Sin inscripción aprobada',
                'detalle' => $evaluacion['motivo'] ?? 'No puede ingresar.',
                'tipo' => 'pendiente',
            ],
        };
    }
}
