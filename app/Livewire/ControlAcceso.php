<?php

namespace App\Livewire;

use App\Models\Asistencia;
use App\Models\Socio;
use Carbon\Carbon;
use Livewire\Component;

class ControlAcceso extends Component
{
    // Entrada del input de la interfaz
    public $identificador = '';

    // Estados para la respuesta visual del acceso
    public $mensajeAcceso = null;
    public $estadoAcceso = null; // 'exito', 'salida', 'error'
    public $socioInfo = null;
    public $motivosDenegacion = [];

    protected function rules()
    {
        return [
            'identificador' => 'required|string',
        ];
    }

    public function mount()
    {
        // El operador debe estar autenticado
        if (!auth()->check()) {
            return redirect()->route('login');
        }
    }

    public function procesar()
    {
        $this->validate();

        $term = trim($this->identificador);

        // 1. Búsqueda del Socio por DNI o Token
        $socio = Socio::where('dni', $term)
            ->orWhere('token', $term)
            ->first();

        if (!$socio) {
            $this->mensajeAcceso = 'Socio no encontrado';
            $this->estadoAcceso = 'error';
            $this->socioInfo = null;
            $this->motivosDenegacion = ['El DNI o Token ingresado no pertenece a ningún socio registrado.'];
            $this->identificador = '';
            return;
        }

        // 2. Determinar si ya está en sala (asistencia abierta)
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

            // Verificar si superó el límite de horas diarias
            $membresia = $socio->membresia;
            $limiteHoras = null;

            if ($membresia && in_array($membresia->horas_mensuales, [2, 40])) {
                $limiteHoras = 2; // Límite diario de 2 horas
            }

            if ($limiteHoras) {
                // Calcular total de minutos usados hoy
                $minutosHoy = Asistencia::where('socio_id', $socio->id)
                    ->whereDate('fecha', Carbon::today())
                    ->whereNotNull('hora_salida')
                    ->sum('tiempo_permanencia');

                if ($minutosHoy > ($limiteHoras * 60)) {
                    $this->mensajeAcceso = "Salida registrada. ¡Atención! Superaste el límite de {$limiteHoras} horas diarias. Debés abonar un cargo adicional.";
                    $this->estadoAcceso = 'salida';
                    $this->socioInfo = $socio;
                    $this->motivosDenegacion = ['⚠️ Tiempo diario superado — debe abonar cargo adicional'];
                    $this->identificador = '';
                    return;
                }
            }

            $this->mensajeAcceso = "Salida registrada con éxito. ¡Hasta luego!";
            $this->estadoAcceso = 'salida';
            $this->socioInfo = $socio;
            $this->motivosDenegacion = [];

        } else {
            // --- CASO DE INGRESO ---
            $motivos = [];

            // Validación A: Socio activo
            if ($socio->estado !== 'activo') {
                $motivos[] = 'El socio se encuentra INACTIVO.';
            }

            // Validación B: Membresía vigente
            if (!$socio->membresia_id) {
                $motivos[] = 'El socio no tiene ninguna membresía contratada.';
            } elseif (!$socio->fecha_vencimiento) {
                $motivos[] = 'El socio no tiene una fecha de vencimiento registrada. Debe abonar la cuota.';
            } elseif ($socio->fecha_vencimiento->lt(Carbon::today())) {
                $motivos[] = 'La membresía ha EXPIRADO el ' . $socio->fecha_vencimiento->format('d/m/Y') . '.';
            }

            // Validación C: Certificado Médico / Apto Físico vigente
            if (!$socio->aptoFisicoVigente()) {
                $motivos[] = 'Falta Certificado Médico (Apto Físico) vigente.';
            }

            // Validación D: Límite de horas diarias
            $membresia = $socio->membresia;
            if ($membresia && in_array($membresia->horas_mensuales, [2, 40])) {
                $minutosHoy = Asistencia::where('socio_id', $socio->id)
                    ->whereDate('fecha', Carbon::today())
                    ->whereNotNull('hora_salida')
                    ->sum('tiempo_permanencia');

                if ($minutosHoy >= 120) { // 2 horas = 120 minutos
                    $motivos[] = 'Tiempo diario agotado (2 hs). Debés abonar para continuar.';
                }
            }

            if (count($motivos) > 0) {
                // ACCESO DENEGADO
                $this->mensajeAcceso = 'Ingreso Denegado';
                $this->estadoAcceso = 'error';
                $this->socioInfo = $socio;
                $this->motivosDenegacion = $motivos;
            } else {
                // ACCESO PERMITIDO
                Asistencia::create([
                    'socio_id' => $socio->id,
                    'fecha' => Carbon::today(),
                    'hora_ingreso' => Carbon::now()->format('H:i:s'),
                ]);

                $this->mensajeAcceso = "¡Ingreso Autorizado! Bienvenido/a.";
                $this->estadoAcceso = 'exito';
                $this->socioInfo = $socio;
                $this->motivosDenegacion = [];
            }
        }

        // Limpiar el input para el próximo escaneo
        $this->identificador = '';
    }

    public function registrarSalidaManual($asistenciaId)
    {
        $asistencia = Asistencia::findOrFail($asistenciaId);

        if ($asistencia->hora_salida) {
            return;
        }

        $fechaIngreso = Carbon::parse($asistencia->fecha->format('Y-m-d') . ' ' . $asistencia->hora_ingreso);
        $fechaSalida = Carbon::now();
        $tiempo_permanencia = max(0, (int) $fechaIngreso->diffInMinutes($fechaSalida));

        $asistencia->update([
            'hora_salida' => $fechaSalida->format('H:i:s'),
            'tiempo_permanencia' => $tiempo_permanencia,
        ]);

        $this->mensajeAcceso = "Salida manual registrada.";
        $this->estadoAcceso = 'salida';
        $this->socioInfo = $asistencia->socio;
        $this->motivosDenegacion = [];

        session()->flash('message', "Salida registrada de forma manual para {$asistencia->socio->nombre} {$asistencia->socio->apellido}.");
    }

    public function render()
    {
        // Socios en sala con cálculo de minutos acumulados hoy
        $sociosEnSala = Asistencia::whereNull('hora_salida')
            ->with(['socio.membresia'])
            ->orderBy('hora_ingreso', 'desc')
            ->get()
            ->map(function ($asistencia) {
                $socio = $asistencia->socio;
                $membresia = $socio->membresia;
                $asistencia->minutos_hoy = 0;
                $asistencia->tiene_limite = false;

                if ($membresia && in_array($membresia->horas_mensuales, [2, 40])) {
                    $asistencia->tiene_limite = true;

                    // Minutos de asistencias cerradas hoy
                    $minutosCerrados = Asistencia::where('socio_id', $socio->id)
                        ->whereDate('fecha', Carbon::today())
                        ->whereNotNull('hora_salida')
                        ->sum('tiempo_permanencia');

                    // Minutos de la asistencia abierta actual
                    $minutosAbiertos = Carbon::parse(
                        $asistencia->fecha->format('Y-m-d') . ' ' . $asistencia->hora_ingreso
                    )->diffInMinutes(Carbon::now());

                    $asistencia->minutos_hoy = $minutosCerrados + $minutosAbiertos;
                }

                return $asistencia;
            });

        return view('livewire.control-acceso', [
            'sociosEnSala' => $sociosEnSala
        ])->layout('layouts.app');
    }
}