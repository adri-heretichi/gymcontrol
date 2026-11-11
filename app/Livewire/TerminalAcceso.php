<?php

namespace App\Livewire;

use App\Models\Asistencia;
use App\Services\ControlAccesoService;
use Carbon\Carbon;
use Livewire\Component;

class TerminalAcceso extends Component
{
    public $pin = '';

    // Estados para la respuesta visual
    public $mensajeAcceso = null;
    public $estadoAcceso = null; // 'exito' (verde), 'salida' (azul), 'error' (rojo)
    public $socioInfo = null;
    public $motivoRechazo = '';
    public $actividadAcceso = null;
    public $horarioAcceso = null;
    public array $opcionesActividad = [];
    public $grupoSeleccionado = null;

    // Propiedades para la consulta del clima
    public $temperatura;
    public $humedad;
    public $viento;
    public $iconoClima;
    public $descripcionClima;
    public $climaCargado = false;

    /**
     * Inicialización del componente.
     */
    public function mount()
    {
        $this->cargarClima();
    }

    /**
     * Consulta la API del clima de Open-Meteo para Formosa, Argentina.
     */
    public function cargarClima()
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get('https://api.open-meteo.com/v1/forecast?latitude=-26.18&longitude=-58.18&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=America%2FArgentina%2FSalta');

            if ($response->successful()) {
                $data = $response->json();
                $current = $data['current'] ?? null;

                if ($current) {
                    $this->temperatura = $current['temperature_2m'] ?? null;
                    $this->humedad = $current['relative_humidity_2m'] ?? null;
                    $this->viento = $current['wind_speed_10m'] ?? null;
                    $code = $current['weather_code'] ?? null;

                    $this->mapearClima($code);
                    $this->climaCargado = true;
                } else {
                    $this->climaCargado = false;
                }
            } else {
                $this->climaCargado = false;
                \Illuminate\Support\Facades\Log::warning('Clima: respuesta no exitosa. Status: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->climaCargado = false;
             \Illuminate\Support\Facades\Log::error('Error clima terminal: ' . $e->getMessage());
        }
    }

    /**
     * Mapea el código del clima (weather_code) a su respectivo ícono y descripción.
     */
    private function mapearClima($code)
    {
        if ($code === 0) {
            $this->iconoClima = '☀️';
            $this->descripcionClima = 'Despejado';
        } elseif (in_array($code, [1, 2, 3])) {
            $this->iconoClima = '🌤️';
            $this->descripcionClima = 'Parcialmente nublado';
        } elseif (in_array($code, [45, 48])) {
            $this->iconoClima = '🌫️';
            $this->descripcionClima = 'Neblina';
        } elseif (in_array($code, [51, 53, 55, 61, 63, 65])) {
            $this->iconoClima = '🌧️';
            $this->descripcionClima = 'Lluvia';
        } elseif (in_array($code, [71, 73, 75])) {
            $this->iconoClima = '🌨️';
            $this->descripcionClima = 'Nieve';
        } elseif (in_array($code, [80, 81, 82])) {
            $this->iconoClima = '🌦️';
            $this->descripcionClima = 'Lluvias dispersas';
        } elseif (in_array($code, [95, 96, 99])) {
            $this->iconoClima = '⛈️';
            $this->descripcionClima = 'Tormenta';
        } else {
            $this->iconoClima = '🌡️';
            $this->descripcionClima = 'Variable';
        }
    }

    /**
     * Limpia los estados y variables para el próximo socio.
     */
    public function resetState()
    {
        $this->pin = '';
        $this->mensajeAcceso = null;
        $this->estadoAcceso = null;
        $this->socioInfo = null;
        $this->motivoRechazo = '';
        $this->actividadAcceso = null;
        $this->horarioAcceso = null;
        $this->opcionesActividad = [];
        $this->grupoSeleccionado = null;
    }

    /**
     * Procesa el PIN ingresado o leído por el escáner USB.
     */
    public function procesar()
    {
        $term = trim($this->pin);
        $this->pin = '';
        $this->resetErrorBag('pin');

        if (empty($term)) {
            return;
        }

        if (!preg_match('/^\d{6}$/', $term)) {
            $this->addError('pin', 'Ingresá un PIN válido de seis números.');
            return;
        }

        $this->realizarMarcado($term);
    }

    /**
     * El terminal sólo registra ingresos; la salida es una acción explícita.
     */
    protected function realizarMarcado(string $pin)
    {
        $resultado = app(ControlAccesoService::class)->registrarIngreso(
            identificador: $pin,
            metodo: ControlAccesoService::METODO_PIN,
            grupoId: $this->grupoSeleccionado ? (int) $this->grupoSeleccionado : null
        );
        $resultado = app(ControlAccesoService::class)->ocultarDetalleMedico($resultado);

        $this->estadoAcceso = $resultado['autorizado'] ? 'exito' : 'error';
        $this->socioInfo = $resultado['socio'];
        $this->mensajeAcceso = $resultado['mensaje'];
        $this->motivoRechazo = $resultado['autorizado'] ? '' : $resultado['mensaje'];
        $this->actividadAcceso = $resultado['actividad'];
        $this->horarioAcceso = $resultado['horario'];
        $this->opcionesActividad = $resultado['codigo'] === 'actividad_ambigua'
            ? ($resultado['actividad']['opciones'] ?? [])
            : [];
        $this->grupoSeleccionado = null;

        if ($resultado['autorizado']) {
            $this->dispatch('asistenciaRegistrada');
        }
    }

    public function render()
    {
        return view('livewire.terminal-acceso')
            ->layout('layouts.terminal');
    }
}
