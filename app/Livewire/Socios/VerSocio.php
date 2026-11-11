<?php

namespace App\Livewire\Socios;

use App\Models\Socio;
use App\Services\CredencialService;
use App\Services\HabilitacionSocioService;
use App\Services\PagoService;
use Carbon\Carbon;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class VerSocio extends Component
{
    use WithPagination;

    // Instancia del socio inyectada automáticamente por Route Model Binding
    public Socio $socio;

    // Propiedades calculadas en base de datos
    public bool $estaPresente = false;
    public int $permanenciaPromedio = 0;
    public $ultimoApto = null;
    public $historialAptos;
    public string $iniciales = '';

    // Colecciones de Eloquent (Eloquent Collections) limitadas a 5 registros desde SQL
    public $ultimosPagos;

    /**
     * El método mount inicializa el componente con el socio inyectado.
     */
    public function mount(Socio $socio)
    {
        $this->authorize('view', $socio);

        // Los pagos, asistencias y certificados se consultan con queries optimizados.
        $this->socio = $socio;

        // 2. Generación ultra defensiva de iniciales para el avatar temporal
        $nombre = trim($this->socio->nombre ?? '');
        $apellido = trim($this->socio->apellido ?? '');
        
        $iniNombre = $nombre !== '' ? mb_substr($nombre, 0, 1) : '';
        $iniApellido = $apellido !== '' ? mb_substr($apellido, 0, 1) : '';
        
        $this->iniciales = mb_strtoupper($iniNombre . $iniApellido);
        
        if ($this->iniciales === '') {
            $this->iniciales = 'GYM';
        }

        // 3. Consulta de presencia: Ejecutada directamente en la base de datos (SQL EXISTS)
        $this->estaPresente = $this->socio->asistencias()
            ->whereDate('fecha', Carbon::today())
            ->whereNull('hora_salida')
            ->exists();

        // 4. Permanencia promedio: Calculada directamente en la base de datos omitiendo registros nulos
        $this->permanenciaPromedio = (int) $this->socio->asistencias()
            ->whereNotNull('tiempo_permanencia')
            ->avg('tiempo_permanencia');

        $this->historialAptos = collect();
        if (auth()->user()->esDuena()) {
            $this->ultimoApto = $this->socio->aptosFisicos()
                ->latest('fecha_emision')
                ->first();
            $this->historialAptos = $this->socio->aptosFisicos()
                ->orderByDesc('fecha_emision')
                ->get();
        }

        // 6. Obtención de los últimos 5 pagos: SQL query con LIMIT 5 ordenado por fecha_pago e id descendente
        // Retorna una colección de Eloquent de forma nativa
        $this->ultimosPagos = $this->socio->pagos()
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        if (auth()->user()->esDuena()) {
            $this->decision_medica_seleccionada = $this->socio->decision_medica ?? 'pendiente';
            $this->decision_medica_observacion = $this->socio->decision_medica_obs ?? '';
            $this->evaluacionMedica = app(\App\Services\CertificadoMedicoService::class)->evaluarRequisitoMedico($this->socio);
            $this->evaluacionCredenciales = app(CredencialService::class)->evaluarRequisitosEmision($this->socio);
        }
    }

    // Gestión médica individual (Fase 6)
    public string $decision_medica_seleccionada = '';
    public string $decision_medica_observacion = '';
    public array $evaluacionMedica = [];
    public string $motivoCredenciales = '';
    public string $pinEmitido = '';
    public array $evaluacionCredenciales = [];

    /**
     * Define o modifica la decisión médica del socio (Exclusivo de la Dueña).
     */
    public function actualizarDecisionMedica(): void
    {
        $this->authorize('gestionarDecisionMedica', $this->socio);

        $service = app(\App\Services\CertificadoMedicoService::class);
        $this->socio = $service->definirDecision(
            socio: $this->socio,
            nuevaDecision: $this->decision_medica_seleccionada,
            duena: auth()->user(),
            observacion: $this->decision_medica_observacion ?: null
        );

        $this->evaluacionMedica = $service->evaluarRequisitoMedico($this->socio);
        $this->decision_medica_observacion = $this->socio->decision_medica_obs ?? '';

        session()->flash('mensaje_medico', 'Decisión médica actualizada correctamente.');
    }

    /**
     * Permite a la Recepcionista informar a la Dueña para revisión médica.
     */
    public function enviarSolicitudRevisionMedica(): void
    {
        abort_unless(auth()->user()->esRecepcionista(), 403);

        $service = app(\App\Services\CertificadoMedicoService::class);
        $service->informarRevisionADuena(
            socio: $this->socio,
            recepcionista: auth()->user()
        );

        session()->flash('mensaje_medico', 'Notificación enviada a la dueña para revisión.');
    }

    public function emitirCredenciales(): void
    {
        $this->authorize('emitirCredenciales', $this->socio);

        try {
            $credenciales = app(CredencialService::class)->emitirPrimeraVez($this->socio, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('credenciales', $exception->getMessage());
            return;
        }

        $this->pinEmitido = $credenciales['pin'];
        $this->socio->refresh();
        $this->evaluacionCredenciales = app(CredencialService::class)->evaluarRequisitosEmision($this->socio);
    }

    public function reponerCredenciales(): void
    {
        $this->authorize('emitirCredenciales', $this->socio);
        $this->validate([
            'motivoCredenciales' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $credenciales = app(CredencialService::class)->reponerCredenciales(
                $this->socio,
                auth()->user(),
                $this->motivoCredenciales
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('credenciales', $exception->getMessage());
            return;
        }

        $this->motivoCredenciales = '';
        $this->pinEmitido = $credenciales['pin'];
        $this->socio->refresh();
        $this->evaluacionCredenciales = app(CredencialService::class)->evaluarRequisitosEmision($this->socio);
    }

    public function revocarCredenciales(): void
    {
        $this->authorize('emitirCredenciales', $this->socio);
        $this->validate([
            'motivoCredenciales' => ['required', 'string', 'max:1000'],
        ]);

        try {
            app(CredencialService::class)->revocarCredenciales(
                $this->socio,
                auth()->user(),
                $this->motivoCredenciales
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('credenciales', $exception->getMessage());
            return;
        }

        $this->motivoCredenciales = '';
        $this->pinEmitido = '';
        $this->socio->refresh();
        $this->evaluacionCredenciales = app(CredencialService::class)->evaluarRequisitosEmision($this->socio);
    }

    public function render()
    {
        $this->authorize('view', $this->socio);
        if (!auth()->user()->esDuena()) {
            $this->ultimoApto = null;
            $this->historialAptos = collect();
            $this->decision_medica_seleccionada = '';
            $this->decision_medica_observacion = '';
            $this->evaluacionMedica = [];
            $this->evaluacionCredenciales = [];
        }

        // 7. Obtención paginada de todas las asistencias del socio
        $asistenciasPaginadas = $this->socio->asistencias()
            ->orderByDesc('fecha')
            ->orderByDesc('hora_ingreso')
            ->paginate(10);

        $habilitacionSocio = app(HabilitacionSocioService::class)->evaluar($this->socio);
        if (!auth()->user()->esDuena()) {
            $habilitacionSocio['etiqueta'] = $habilitacionSocio['habilitado'] ? 'Habilitado' : 'No habilitado';
            $habilitacionSocio['detalle'] = $habilitacionSocio['habilitado']
                ? 'Requisitos operativos completos.'
                : 'Consultar con la dueña para continuar.';
            $habilitacionSocio['por_inscripcion'] = array_map(
                fn (array $inscripcion) => [
                    'disciplina' => $inscripcion['disciplina'],
                    'grupo' => $inscripcion['grupo'],
                    'habilitado' => $inscripcion['habilitado'],
                    'detalle' => $inscripcion['habilitado'] ? 'Habilitado' : 'Consultar con la dueña',
                ],
                $habilitacionSocio['por_inscripcion']
            );
        }

        return view('livewire.socios.ver-socio', [
            'asistenciasPaginadas' => $asistenciasPaginadas,
            'habilitacionSocio' => $habilitacionSocio,
            'coberturasPorInscripcion' => app(PagoService::class)
                ->consultarCoberturasSocio($this->socio),
        ])->layout('layouts.app');
    }
}
