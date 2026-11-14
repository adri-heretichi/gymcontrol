<?php

namespace App\Livewire;

use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\Socio;
use App\Services\ControlAccesoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class ControlAcceso extends Component
{
    public string $identificador = '';
    public ?int $grupoSeleccionado = null;
    public bool $puedeConfirmarIngreso = false;
    public bool $requiereSeleccionGrupo = false;
    public array $opcionesActividad = [];
    public $mensajeAcceso = null;
    public $estadoAcceso = null;
    public $socioInfo = null;
    public $motivosDenegacion = [];
    public $actividadAcceso = null;
    public $horarioAcceso = null;

    public function mount()
    {
        Gate::authorize('controlar-acceso');
    }

    public function procesar()
    {
        $this->consultar();
    }

    public function consultar(): void
    {
        Gate::authorize('controlar-acceso');

        $this->resetErrorBag();
        $pin = trim($this->identificador);
        if (!preg_match('/^\d{6}$/', $pin)) {
            $this->addError('identificador', 'Ingresá un PIN válido de seis números.');
            $this->identificador = '';
            $this->limpiarEvaluacion();
            return;
        }

        $resultado = app(ControlAccesoService::class)->evaluarAcceso(
            identificador: $pin,
            metodo: ControlAccesoService::METODO_PIN,
            grupoId: $this->grupoSeleccionado
        );
        $this->identificador = '';

        $this->actualizarResultado($resultado);
        $this->puedeConfirmarIngreso = $resultado['autorizado'];
        $this->estadoAcceso = $resultado['autorizado'] ? 'preparado' : 'error';
        $this->requiereSeleccionGrupo = $resultado['codigo'] === 'actividad_ambigua';
        $this->opcionesActividad = $this->requiereSeleccionGrupo
            ? ($resultado['actividad']['opciones'] ?? [])
            : [];
        if ($resultado['autorizado']) {
            $this->grupoSeleccionado = $resultado['actividad']['grupo_id'] ?? $this->grupoSeleccionado;
        }
    }

    public function confirmarIngreso(): void
    {
        Gate::authorize('controlar-acceso');

        $pin = trim($this->identificador);
        if (!preg_match('/^\d{6}$/', $pin)) {
            $this->addError('identificador', 'Volvé a ingresar el PIN de seis números para confirmar.');
            $this->identificador = '';
            return;
        }

        $resultado = app(ControlAccesoService::class)->registrarIngreso(
            identificador: $pin,
            metodo: ControlAccesoService::METODO_PIN,
            grupoId: $this->grupoSeleccionado,
            operadorId: auth()->id(),
            origen: 'pin_recepcion'
        );

        $this->actualizarResultado($resultado);
        $this->identificador = '';
        $this->limpiarEvaluacion();
    }

    public function registrarSalidaManual($asistenciaId)
    {
        Gate::authorize('controlar-acceso');
        $asistencia = Asistencia::findOrFail($asistenciaId);
        $resultado = app(ControlAccesoService::class)->registrarSalida(
            $asistencia->socio,
            auth()->id(),
            null,
            'pin_recepcion'
        );

        $this->actualizarResultado($resultado);
        $this->estadoAcceso = $resultado['autorizado'] ? 'salida' : 'error';
        $this->limpiarEvaluacion();
    }

    #[On('gym-realtime-updated')]
    public function refreshRealtimeMovements(): void
    {
    }

    public function render()
    {
        $ultimosMovimientos = Asistencia::query()
            ->with('socio')
            ->orderByDesc('fecha')
            ->orderByDesc('hora_ingreso')
            ->limit(12)
            ->get();
        $ultimosEventosAcceso = Auditoria::query()
            ->where('tipo_evento', 'acceso')
            ->latest('created_at')
            ->limit(12)
            ->with(['auditable' => fn ($query) => $query->select(['id', 'nombre', 'apellido'])])
            ->get(['id', 'accion', 'auditable_type', 'auditable_id', 'datos_despues', 'created_at'])
            ->map(function (Auditoria $evento) {
                $datos = $evento->datos_despues ?? [];
                $codigo = (string) ($datos['resultado'] ?? '');
                $metodo = (string) ($datos['metodo'] ?? '');
                $operacion = $metodo === 'salida' ? 'Salida' : (
                    $evento->accion === 'acceso_autorizado' ? 'Autorizado' : 'Denegado'
                );

                return [
                    'socio' => $evento->auditable instanceof Socio
                        ? trim($evento->auditable->nombre . ' ' . $evento->auditable->apellido)
                        : 'Credencial no identificada',
                    'momento' => $evento->created_at,
                    'operacion' => $operacion,
                    'metodo' => match ($metodo) {
                        'pin' => 'PIN',
                        'qr' => 'QR',
                        'salida' => 'Salida',
                        default => 'Acceso',
                    },
                    'motivo' => $evento->accion === 'acceso_autorizado'
                        ? 'Acceso autorizado.'
                        : ControlAccesoService::mensajeOperativo($codigo),
                ];
            });

        return view('livewire.control-acceso', [
            'ultimosMovimientos' => $ultimosMovimientos,
            'ultimosEventosAcceso' => $ultimosEventosAcceso,
        ])->layout('layouts.app');
    }

    private function actualizarResultado(array $resultado): void
    {
        if (auth()->user()->esRecepcionista()) {
            $resultado = app(ControlAccesoService::class)->ocultarDetalleMedico($resultado);
        }

        $this->estadoAcceso = $resultado['autorizado'] ? 'exito' : 'error';
        $this->mensajeAcceso = $resultado['mensaje'];
        $this->socioInfo = $resultado['socio'];
        $this->motivosDenegacion = $resultado['autorizado'] ? [] : [$resultado['mensaje']];
        $this->actividadAcceso = $resultado['actividad'];
        $this->horarioAcceso = $resultado['horario'];
    }

    private function limpiarEvaluacion(): void
    {
        $this->puedeConfirmarIngreso = false;
        $this->requiereSeleccionGrupo = false;
        $this->opcionesActividad = [];
        $this->grupoSeleccionado = null;
    }
}