<?php

namespace App\Livewire\Socios;

use App\Events\GymRealtimeUpdate;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Socio;
use App\Services\CertificadoMedicoService;
use App\Services\CredencialService;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CrearSocio extends Component
{
    // Propiedades vinculadas al formulario
    public $nombre = '';
    public $apellido = '';
    public $dni = '';
    public $sexo = 'M'; // Por defecto Masculino
    public $correo = '';
    public $celular = '';
    public $fecha_alta = '';
    public $estado = 'activo'; // Activo por defecto al registrarse
    
    // Propiedad foto preparada para futura carga de archivos (temporalmente nula)
    public $foto = null;

    public array $inscripciones = [];
    public string $decision_medica = 'pendiente';
    public string $metodo_pago = 'efectivo';

    /**
     * Inicializa la fecha de alta por defecto.
     */
    public function mount()
    {
        if (!auth()->check() || (!auth()->user()->esDuena() && !auth()->user()->esRecepcionista())) {
            abort(403, 'No tienes los permisos necesarios para acceder a esta sección.');
        }
        $this->fecha_alta = Carbon::today()->format('Y-m-d');
        $this->agregarInscripcion();
    }

    public function agregarInscripcion(): void
    {
        $today = Carbon::today(config('app.timezone'));
        $this->inscripciones[] = ['disciplina_id' => '', 'grupo_id' => '', 'confirmar_cobro' => false,
            'fecha_inicio' => $today->toDateString(), 'fecha_fin' => $today->endOfMonth()->toDateString()];
    }

    public function quitarInscripcion(int $index): void
    {
        if (array_key_exists($index, $this->inscripciones)) {
            array_splice($this->inscripciones, $index, 1);
            $this->resetValidation();
        }
    }

    public function updatedInscripciones($value, ?string $key = null): void
    {
        if ($key && preg_match('/^(\d+)\.disciplina_id$/', $key, $matches)) {
            $this->inscripciones[(int) $matches[1]]['grupo_id'] = '';
            $this->inscripciones[(int) $matches[1]]['confirmar_cobro'] = false;
        } elseif ($key && preg_match('/^(\d+)\.grupo_id$/', $key, $matches)) {
            $this->inscripciones[(int) $matches[1]]['confirmar_cobro'] = false;
        }
    }

    /**
     * Reglas de validación aplicadas al formulario.
     */
    protected function rules()
    {
        return [
            'nombre' => 'required|string|min:2|max:255',
            'apellido' => 'required|string|min:2|max:255',
            'dni' => 'required|digits_between:7,10|unique:socios,dni',
            'sexo' => 'required|in:M,F',
            'correo' => 'nullable|email|unique:socios,correo|max:255',
            'celular' => 'nullable|string|max:50',
            'fecha_alta' => 'required|date',
            'foto' => 'nullable', // Regla preparada para futuras integraciones de uploads
            'estado' => 'required|in:activo,inactivo',
            'decision_medica' => 'required|in:pendiente,requerido,no_requerido',
            'metodo_pago' => ['required', Rule::in(array_keys(Pago::MEDIOS_PAGO))],
            'inscripciones' => 'array|list',
            'inscripciones.*' => 'array:disciplina_id,grupo_id,confirmar_cobro,fecha_inicio,fecha_fin',
            'inscripciones.*.disciplina_id' => 'nullable|required_with:inscripciones.*.grupo_id|integer|exists:disciplinas,id',
            'inscripciones.*.grupo_id' => 'nullable|required_with:inscripciones.*.disciplina_id|integer|distinct|exists:grupos,id',
            'inscripciones.*.confirmar_cobro' => 'boolean',
            'inscripciones.*.fecha_inicio' => 'exclude_unless:inscripciones.*.confirmar_cobro,true|required|date',
            'inscripciones.*.fecha_fin' => 'exclude_unless:inscripciones.*.confirmar_cobro,true|required|date|after_or_equal:inscripciones.*.fecha_inicio',
        ];
    }

    /**
     * Mensajes de error en español para feedback del usuario.
     */
    protected $messages = [
        'nombre.required' => 'El nombre es obligatorio.',
        'apellido.required' => 'El apellido es obligatorio.',
        'dni.required' => 'El DNI es obligatorio.',
        'dni.digits_between' => 'El DNI debe contener entre 7 y 10 números.',
        'dni.unique' => 'Este DNI ya se encuentra registrado.',
        'sexo.required' => 'Debe seleccionar el sexo.',
        'correo.email' => 'El formato del correo electrónico es inválido.',
        'correo.unique' => 'Este correo electrónico ya está registrado.',
        'fecha_alta.required' => 'La fecha de alta es obligatoria.',
        'inscripciones.*.grupo_id.distinct' => 'No seleccione el mismo grupo más de una vez.',
        'inscripciones.*.grupo_id.required_with' => 'Seleccione un grupo de la disciplina.',
        'inscripciones.*.disciplina_id.required_with' => 'Seleccione la disciplina del grupo.',
        'inscripciones.*.fecha_fin.after_or_equal' => 'El vencimiento debe ser igual o posterior al inicio de esa cobertura.',
    ];

    /**
     * Procesa el guardado del nuevo socio y normaliza sus campos con trim().
     */
    public function guardar()
    {
        $this->authorize('create', Socio::class);
        $data = $this->validate();
        if (!auth()->user()->esDuena() && $this->decision_medica !== 'pendiente') {
            throw ValidationException::withMessages(['decision_medica' => 'Solo la dueña puede resolver el requisito médico.']);
        }
        $selected = array_filter($data['inscripciones'], fn ($row) => filled($row['disciplina_id'] ?? null) || filled($row['grupo_id'] ?? null));
        foreach ($data['inscripciones'] as $index => $row) {
            if (!empty($row['confirmar_cobro']) && empty($row['grupo_id'])) {
                throw ValidationException::withMessages(["inscripciones.{$index}.grupo_id" => 'Seleccione una inscripción antes de confirmar su cobro.']);
            }
        }

        $created = DB::transaction(function () use ($selected): Socio {
            // Lock all selected groups in a fixed order before creating the member.
            $groups = Grupo::with(['disciplina', 'horarios'])->whereIn('id', array_column($selected, 'grupo_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($selected as $index => $row) {
                $group = $groups->get((int) $row['grupo_id']);
                if (!$group || (int) $group->disciplina_id !== (int) $row['disciplina_id']
                    || $group->estado !== 'activo' || $group->disciplina?->estado !== 'activo' || $group->horarios->isEmpty()) {
                    throw ValidationException::withMessages(["inscripciones.{$index}.grupo_id" => 'El grupo debe pertenecer a la disciplina elegida, estar activo y tener horarios.']);
                }
                if (!empty($row['confirmar_cobro']) && ($group->disciplina->precio === null || (float) $group->disciplina->precio <= 0)) {
                    throw ValidationException::withMessages(["inscripciones.{$index}.confirmar_cobro" => 'Defina un precio válido para esta disciplina antes de cobrar.']);
                }
            }

            $member = Socio::create([
                'membresia_id' => null,
                'nombre' => trim($this->nombre), 'apellido' => trim($this->apellido),
                'dni' => $this->dni, 'sexo' => $this->sexo,
                'correo' => $this->correo ? trim($this->correo) : null,
                'celular' => $this->celular ? trim($this->celular) : null,
                'foto' => null, 'token' => Str::random(40),
                'qr' => null, 'pin' => null, 'qr_token' => null,
                'estado_credencial' => 'sin_emitir', 'decision_medica' => 'pendiente',
                'fecha_alta' => $this->fecha_alta, 'fecha_vencimiento' => null, 'estado' => $this->estado,
            ]);
            \App\Models\Auditoria::registrar(
                tipoEvento: 'administrativo', accion: 'alta_socio', entidad: $member,
                datosDespues: ['id' => $member->id, 'nombre' => $member->nombre, 'apellido' => $member->apellido,
                    'dni' => $member->dni, 'estado' => $member->estado]
            );

            foreach ($selected as $index => $row) {
                $group = $groups->get((int) $row['grupo_id']);
                try {
                    $result = app(InscripcionService::class)->solicitarInscripcion($member, $group, auth()->user());
                    if (!empty($row['confirmar_cobro'])) {
                        app(PagoService::class)->registrarPago(
                            socio: $member,
                            detalles: [[
                                'inscripcion_id' => $result['modelo']->id,
                                'concepto' => 'Cuota - ' . $group->disciplina->nombre,
                                'fecha_inicio' => $row['fecha_inicio'], 'fecha_fin' => $row['fecha_fin'],
                                'subtotal' => $group->disciplina->precio,
                            ]],
                            medioPago: $this->metodo_pago, operador: auth()->user(),
                            fechaPago: Carbon::today(config('app.timezone'))->toDateString(),
                        );
                    }
                } catch (DomainException | \InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(["inscripciones.{$index}.grupo_id" => $exception->getMessage()]);
                }
            }
            if (auth()->user()->esDuena() && $this->decision_medica !== 'pendiente') {
                app(CertificadoMedicoService::class)->definirDecision($member, $this->decision_medica, auth()->user());
            }
            $member->refresh();
            $credentials = app(CredencialService::class);
            if (auth()->user()->esDuena() && $credentials->evaluarRequisitosEmision($member)['habilitado']) {
                $credentials->emitirPrimeraVez($member, auth()->user());
            }
            event(GymRealtimeUpdate::operationalChange('member-created'));

            return $member;
        });

        session()->flash('message', 'Datos del socio registrados. Las inscripciones, pagos y requisitos de acceso se consultan por separado en su ficha.');
        return redirect()->route('socios.show', $created->id);
    }

    public function render()
    {
        return view('livewire.socios.crear-socio', [
            'disciplinas' => Disciplina::query()->where('estado', 'activo')
                ->with(['grupos' => fn ($query) => $query->where('estado', 'activo')
                    ->whereHas('horarios')->with('horarios')->orderBy('nombre')])
                ->orderBy('nombre')->get(),
            'mediosPago' => Pago::MEDIOS_PAGO,
        ])->layout('layouts.app');
    }
}
