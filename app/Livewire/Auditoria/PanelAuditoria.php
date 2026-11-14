<?php

namespace App\Livewire\Auditoria;

use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\AptoFisico;
use App\Models\Auditoria;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\ListaEspera;
use App\Models\Pago;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PanelAuditoria extends Component
{
    use WithPagination;

    public string $fechaDesde = '';
    public string $fechaHasta = '';
    public string $tipoEvento = '';
    public string $accion = '';
    public string $usuarioId = '';
    public string $dniSocio = '';
    public string $resultado = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Auditoria::class);
    }

    public function updatingFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatingFechaHasta(): void
    {
        $this->resetPage();
    }

    public function updatingTipoEvento(): void
    {
        $this->accion = '';
        $this->resetPage();
    }

    public function updatingAccion(): void
    {
        $this->resetPage();
    }

    public function updatingUsuarioId(): void
    {
        $this->resetPage();
    }

    public function updatingDniSocio(): void
    {
        $this->resetPage();
    }

    public function updatingResultado(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'fechaDesde',
            'fechaHasta',
            'tipoEvento',
            'accion',
            'usuarioId',
            'dniSocio',
            'resultado',
        ]);
        $this->resetPage();
    }

    #[On('gym-realtime-updated')]
    public function refreshRealtimeAudit(): void
    {
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['fechaDesde', 'fechaHasta'], true)) {
            $value = $this->{$property};
            if ($value !== '' && !$this->esFechaValida($value)) {
                $this->addError($property, 'Ingresá una fecha válida.');
            } else {
                $this->resetValidation($property);
            }
        }
    }

    public function render()
    {
        Gate::authorize('viewAny', Auditoria::class);

        $socioId = trim($this->dniSocio) !== ''
            ? Socio::query()->where('dni', trim($this->dniSocio))->value('id')
            : null;

        $query = Auditoria::query()
            ->with('usuario:id,name')
            ->when($this->fechaDesde && $this->esFechaValida($this->fechaDesde), fn ($q) => $q->whereDate('created_at', '>=', $this->fechaDesde))
            ->when($this->fechaHasta && $this->esFechaValida($this->fechaHasta), fn ($q) => $q->whereDate('created_at', '<=', $this->fechaHasta))
            ->when($this->tipoEvento !== '', fn ($q) => $q->where('tipo_evento', $this->tipoEvento))
            ->when($this->accion !== '', fn ($q) => $q->where('accion', $this->accion))
            ->when($this->usuarioId !== '', fn ($q) => $q->where('user_id', $this->usuarioId))
            ->when($this->resultado === 'autorizado', fn ($q) => $q->where('accion', 'acceso_autorizado'))
            ->when($this->resultado === 'denegado', fn ($q) => $q->where('accion', 'acceso_denegado'));

        if ($this->dniSocio !== '') {
            $socioId
                ? $this->filtrarPorSocio($query, (int) $socioId)
                : $query->whereRaw('1 = 0');
        }

        $eventos = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25);
        $this->agregarSociosRelacionados($eventos->getCollection(), $socioId ? (int) $socioId : null);
        $acciones = Auditoria::query()
            ->when($this->tipoEvento !== '', fn ($q) => $q->where('tipo_evento', $this->tipoEvento))
            ->distinct()
            ->orderBy('accion')
            ->pluck('accion');
        $usuarios = User::query()
            ->whereIn('id', Auditoria::query()->whereNotNull('user_id')->select('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.auditoria.panel-auditoria', [
            'eventos' => $eventos,
            'acciones' => $acciones,
            'usuarios' => $usuarios,
            'tipos' => Auditoria::query()->distinct()->orderBy('tipo_evento')->pluck('tipo_evento'),
        ])->layout('layouts.app');
    }

    private function esFechaValida(string $fecha): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            return false;
        }

        $fechaAnalizada = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $fechaAnalizada instanceof \DateTimeImmutable
            && $fechaAnalizada->format('Y-m-d') === $fecha;
    }

    private function filtrarPorSocio(Builder $query, int $socioId): void
    {
        $query->where(function (Builder $related) use ($socioId) {
            $related->where(function (Builder $q) use ($socioId) {
                $q->where('auditable_type', Socio::class)->where('auditable_id', $socioId);
            });

            foreach ([
                Inscripcion::class => Inscripcion::query()->where('socio_id', $socioId)->select('id'),
                Asistencia::class => Asistencia::query()->where('socio_id', $socioId)->select('id'),
                AsistenciaClase::class => AsistenciaClase::query()->where('socio_id', $socioId)->select('id'),
                Grupo::class => Grupo::query()->whereHas('inscripciones', fn ($q) => $q->where('socio_id', $socioId))->select('id'),
                Disciplina::class => Disciplina::query()->whereHas('grupos.inscripciones', fn ($q) => $q->where('socio_id', $socioId))->select('id'),
                AptoFisico::class => AptoFisico::query()->where('socio_id', $socioId)->select('id'),
                Solicitud::class => Solicitud::query()->where('socio_id', $socioId)->select('id'),
                Pago::class => Pago::query()->where('socio_id', $socioId)->select('id'),
                ListaEspera::class => ListaEspera::query()->where('socio_id', $socioId)->select('id'),
            ] as $type => $ids) {
                $related->orWhere(function (Builder $q) use ($type, $ids) {
                    $q->where('auditable_type', $type)->whereIn('auditable_id', $ids);
                });
            }
        });
    }

    private function agregarSociosRelacionados(\Illuminate\Support\Collection $eventos, ?int $socioFiltrado): void
    {
        $tablasRelacionadas = [
            Inscripcion::class => 'inscripciones',
            Asistencia::class => 'asistencias',
            AsistenciaClase::class => 'asistencias_clases',
            AptoFisico::class => 'aptos_fisicos',
            Solicitud::class => 'solicitudes',
            Pago::class => 'pagos',
            ListaEspera::class => 'listas_espera',
        ];
        $socioIds = [];
        $socioPorAuditable = [];

        foreach ($eventos->groupBy('auditable_type') as $tipo => $registros) {
            $ids = $registros->pluck('auditable_id')->filter()->unique()->values();
            if ($tipo === Socio::class) {
                $socioIds = array_merge($socioIds, $ids->all());
                foreach ($ids as $id) {
                    $socioPorAuditable[$tipo][$id] = $id;
                }
                continue;
            }
            if (in_array($tipo, [Grupo::class, Disciplina::class], true)) {
                if ($socioFiltrado) {
                    $socioIds[] = $socioFiltrado;
                    foreach ($ids as $id) {
                        $socioPorAuditable[$tipo][$id] = $socioFiltrado;
                    }
                }
                continue;
            }
            if (!isset($tablasRelacionadas[$tipo])) {
                continue;
            }
            $relaciones = DB::table($tablasRelacionadas[$tipo])
                ->whereIn('id', $ids)
                ->pluck('socio_id', 'id');
            $socioPorAuditable[$tipo] = $relaciones->all();
            $socioIds = array_merge($socioIds, $relaciones->values()->all());
        }

        $nombres = Socio::query()
            ->whereIn('id', array_unique($socioIds))
            ->get(['id', 'nombre', 'apellido'])
            ->mapWithKeys(fn (Socio $socio) => [$socio->id => trim($socio->nombre . ' ' . $socio->apellido)]);

        foreach ($eventos as $evento) {
            $socioId = $socioPorAuditable[$evento->auditable_type][$evento->auditable_id] ?? null;
            $evento->setAttribute('socio_relacionado', $socioId ? ($nombres[$socioId] ?? null) : null);
        }
    }
}
