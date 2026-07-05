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

        // 2. Determinar si ya está en sala (asistencia abierta hoy)
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
                $motivos[] = 'El socio no tiene una fecha de vencimiento registrada.';
            } elseif ($socio->fecha_vencimiento->isPast() && !$socio->fecha_vencimiento->isToday()) {
                $motivos[] = 'La membresía ha EXPIRADO el ' . $socio->fecha_vencimiento->format('d/m/Y') . '.';
            }

            // Validación C: Certificado Médico / Apto Físico vigente
            if (!$socio->aptoFisicoVigente()) {
                $motivos[] = 'Falta Certificado Médico (Apto Físico) vigente.';
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
        // Socios en sala (asistencias abiertas hoy)
        $sociosEnSala = Asistencia::whereNull('hora_salida')
            ->with(['socio.membresia'])
            ->orderBy('hora_ingreso', 'desc')
            ->get();

        return view('livewire.control-acceso', [
            'sociosEnSala' => $sociosEnSala
        ])->layout('layouts.app');
    }
}
