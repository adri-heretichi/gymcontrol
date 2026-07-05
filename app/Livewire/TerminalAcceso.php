<?php

namespace App\Livewire;

use App\Models\Asistencia;
use App\Models\Socio;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class TerminalAcceso extends Component
{
    public $token = '';

    // Estados para la respuesta visual
    public $mensajeAcceso = null;
    public $estadoAcceso = null; // 'exito' (verde), 'salida' (azul), 'error' (rojo)
    public $socioInfo = null;
    public $motivoRechazo = '';

    // Guardamos la marca de tiempo del último escaneo móvil procesado
    public $lastProcessedScanTime = 0;

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
        $this->lastProcessedScanTime = now()->timestamp;
        $this->cargarClima();
    }

    /**
     * Consulta la API del clima de Open-Meteo para Formosa, Argentina.
     */
    public function cargarClima()
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get('https://api.open-meteo.com/v1/forecast?latitude=-26.18&longitude=-58.18&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=America%2FArgentina%2FSalta');

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
            }
        } catch (\Exception $e) {
            $this->climaCargado = false;
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
        $this->token = '';
        $this->mensajeAcceso = null;
        $this->estadoAcceso = null;
        $this->socioInfo = null;
        $this->motivoRechazo = '';
    }

    /**
     * Procesa la entrada manual o escáner USB del token.
     */
    public function procesar()
    {
        $term = trim($this->token);
        if (empty($term)) {
            return;
        }

        $this->realizarMarcado($term);
        $this->token = '';
    }

    /**
     * Revisa de forma periódica (polling) si hay un escaneo remoto de celular en Cache.
     */
    public function checkExternalScan()
    {
        $lastScan = Cache::get('last_terminal_scan');
        
        if ($lastScan && $lastScan['time'] > $this->lastProcessedScanTime) {
            $this->lastProcessedScanTime = $lastScan['time'];
            $this->estadoAcceso = $lastScan['estado'];
            $this->mensajeAcceso = $lastScan['mensaje'];
            $this->motivoRechazo = $lastScan['motivo'];
            $this->socioInfo = $lastScan['socio_id'] ? Socio::find($lastScan['socio_id']) : null;
        }
    }

    /**
     * Lógica central de marcado (Ingreso / Salida) y validación de reglas de negocio.
     */
    protected function realizarMarcado($tokenValue)
    {
        $socio = Socio::where('token', $tokenValue)->first();

        // 1. Validar existencia del socio
        if (!$socio) {
            $this->estadoAcceso = 'error';
            $this->mensajeAcceso = 'Acceso Denegado';
            $this->motivoRechazo = 'Token o Socio no encontrado.';
            $this->socioInfo = null;
            return;
        }

        // 2. Comprobar si ya tiene una asistencia abierta para hoy
        $asistenciaAbierta = Asistencia::where('socio_id', $socio->id)
            ->whereNull('hora_salida')
            ->first();

        if ($asistenciaAbierta) {
            // --- CASO DE SALIDA ---
            $fechaIngreso = Carbon::parse($asistenciaAbierta->fecha->format('Y-m-d') . ' ' . $asistenciaAbierta->hora_ingreso);
            $fechaSalida = Carbon::now();
            $tiempo_permanencia = max(0, (int) $fechaIngreso->diffInMinutes($fechaSalida));

            $asistenciaAbierta->update([
                'hora_salida' => $fechaSalida->format('H:i:s'),
                'tiempo_permanencia' => $tiempo_permanencia,
            ]);

            $this->estadoAcceso = 'salida';
            $this->socioInfo = $socio;
            $this->mensajeAcceso = "Salida registrada. Tiempo en sala: {$tiempo_permanencia} min.";
            $this->motivoRechazo = '';

            // Emitir evento para refrescar otros componentes
            $this->dispatch('asistenciaRegistrada');
        } else {
            // --- CASO DE INGRESO ---
            $motivos = [];

            // Validación A: Socio activo
            if ($socio->estado !== 'activo') {
                $motivos[] = 'El socio se encuentra INACTIVO.';
            }

            // Validación B: Membresía vigente
            if (!$socio->fecha_vencimiento || $socio->fecha_vencimiento->isPast() && !$socio->fecha_vencimiento->isToday()) {
                $vencimiento = $socio->fecha_vencimiento ? $socio->fecha_vencimiento->format('d/m/Y') : 'no registrada';
                $motivos[] = "Membresía vencida (Expiró el {$vencimiento}).";
            }

            // Validación C: Certificado médico / Apto físico vigente
            $tieneAptoVigente = $socio->aptosFisicos()
                ->where('estado', 'vigente')
                ->whereDate('fecha_vencimiento', '>=', Carbon::today())
                ->exists();

            if (!$tieneAptoVigente) {
                $motivos[] = 'Falta Certificado Médico (Apto Físico) vigente.';
            }

            if (count($motivos) > 0) {
                // Ingreso denegado por incumplimiento
                $this->estadoAcceso = 'error';
                $this->mensajeAcceso = 'Acceso Denegado';
                $this->motivoRechazo = implode(' | ', $motivos);
                $this->socioInfo = $socio;
            } else {
                // Ingreso autorizado
                Asistencia::create([
                    'socio_id' => $socio->id,
                    'fecha' => Carbon::today(),
                    'hora_ingreso' => Carbon::now()->format('H:i:s'),
                ]);

                $this->estadoAcceso = 'exito';
                $this->socioInfo = $socio;
                $this->mensajeAcceso = "Bienvenido, {$socio->nombre} {$socio->apellido}";
                $this->motivoRechazo = '';

                // Emitir evento para refrescar otros componentes
                $this->dispatch('asistenciaRegistrada');
            }
        }
    }

    public function render()
    {
        return view('livewire.terminal-acceso')
            ->layout('layouts.terminal');
    }
}
