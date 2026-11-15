<?php

namespace App\Observers;

use App\Models\AptoFisico;
use App\Models\Asistencia;
use App\Models\AsistenciaClase;
use App\Models\Auditoria;
use App\Models\ConfiguracionGimnasio;
use App\Models\Disciplina;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\ListaEspera;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Socio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\RealtimeChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class GymStateObserver
{
    public function created(Model $model): void
    {
        $this->changed($model);
    }

    public function updated(Model $model): void
    {
        if (! $model->wasChanged()) {
            return;
        }
        if ($model instanceof User && ! $model->wasChanged(['name', 'email', 'rol', 'password'])) {
            return;
        }
        $this->changed($model);
    }

    public function deleted(Model $model): void
    {
        $this->changed($model);
    }

    private function changed(Model $model): void
    {
        $changes = app(RealtimeChanges::class);
        if ($model instanceof Auditoria) {
            $changes->record(['audit'], 'owner', $model);
            if ($model->tipo_evento === 'acceso') {
                $changes->record(['access'], 'staff', $model);

                return;
            }
            // Cubre también escrituras masivas auditadas sin eventos Eloquent.
            $class = Relation::getMorphedModel($model->auditable_type ?? '') ?? $model->auditable_type;
            $resources = match ($class) {
                Socio::class => ['members', 'access'],
                Grupo::class, GrupoHorario::class, Disciplina::class, Membresia::class, ListaEspera::class => ['groups'],
                Inscripcion::class => ['members', 'groups', 'payments'],
                Pago::class, PagoDetalle::class => ['payments', 'members'],
                Asistencia::class, AsistenciaClase::class, ConfiguracionGimnasio::class => ['access'],
                AptoFisico::class => ['members', 'access'], default => [],
            };
            $changes->record($resources, 'staff', $model);

            return;
        }
        if ($model instanceof Solicitud) {
            $changes->record(['requests'], 'requester:'.(int) $model->solicitante_id, $model);

            return;
        }
        if ($model instanceof User) {
            $changes->record(['operators', 'audit'], 'owner', $model);
            $changes->record(['permissions'], 'staff', $model);

            return;
        }
        if ($model instanceof AptoFisico) {
            $changes->record(['medical'], 'owner', $model);
            $changes->record(['members', 'access'], 'staff', $model);

            return;
        }
        $resources = match (true) {
            $model instanceof Socio => ['members', 'access'],
            $model instanceof Disciplina, $model instanceof Grupo, $model instanceof GrupoHorario, $model instanceof Membresia, $model instanceof ListaEspera => ['groups'],
            $model instanceof Inscripcion => ['members', 'groups', 'payments'],
            $model instanceof Pago, $model instanceof PagoDetalle => ['payments', 'members', 'access'],
            $model instanceof Asistencia, $model instanceof AsistenciaClase, $model instanceof ConfiguracionGimnasio => ['access'], default => [],
        };
        $changes->record($resources, 'staff', $model);
        if ($model instanceof Socio && ($model->wasRecentlyCreated || $model->wasChanged(['decision_medica', 'decision_medica_obs']))) {
            $changes->record(['medical'], 'owner', $model);
        }
        if ($model instanceof Inscripcion) {
            foreach (Solicitud::where('inscripcion_id', $model->id)->distinct()->pluck('solicitante_id') as $id) {
                $changes->record(['requests'], 'requester:'.(int) $id, $model);
            }
        }
    }
}
