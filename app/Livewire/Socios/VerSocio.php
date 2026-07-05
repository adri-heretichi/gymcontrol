<?php

namespace App\Livewire\Socios;

use App\Models\Socio;
use Carbon\Carbon;
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
        // 1. Carga optimizada de relaciones: Solo cargamos la membresía mediante Eager Loading, 
        // ya que los pagos, asistencias y certificados médicos se consultan con queries directos optimizados.
        $this->socio = $socio->load(['membresia']);

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

        // 5. Último apto físico: SQL query directo para obtener el registro más reciente por emisión
        $this->ultimoApto = $this->socio->aptosFisicos()
            ->latest('fecha_emision')
            ->first();

        // 5b. Historial completo de aptos físicos
        $this->historialAptos = $this->socio->aptosFisicos()
            ->orderByDesc('fecha_emision')
            ->get();

        // 6. Obtención de los últimos 5 pagos: SQL query con LIMIT 5 ordenado por fecha_pago e id descendente
        // Retorna una colección de Eloquent de forma nativa
        $this->ultimosPagos = $this->socio->pagos()
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        // Verificar si existe el archivo QR en formato SVG (Problema 5)
        $this->existeQr = file_exists(storage_path('app/private/qrs/qr_' . $this->socio->id . '.svg'));
    }

    // Propiedad para saber si existe el QR en disco
    public bool $existeQr = false;

    /**
     * Regenera el código QR del socio en formato SVG al hacer clic en el botón (Problema 5).
     */
    public function regenerarQr()
    {
        $qrDir = storage_path('app/private/qrs');
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        $qrPath = $qrDir . '/qr_' . $this->socio->id . '.svg';

        \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size(200)
            ->generate(config('app.url') . '/terminal/scan/' . $this->socio->token, $qrPath);

        $this->existeQr = true;
        session()->flash('message', 'Código QR regenerado con éxito.');
    }

    public function render()
    {
        // 7. Obtención paginada de todas las asistencias del socio
        $asistenciasPaginadas = $this->socio->asistencias()
            ->orderByDesc('fecha')
            ->orderByDesc('hora_ingreso')
            ->paginate(10);

        return view('livewire.socios.ver-socio', [
            'asistenciasPaginadas' => $asistenciasPaginadas
        ])->layout('layouts.app');
    }
}
