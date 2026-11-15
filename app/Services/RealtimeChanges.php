<?php

namespace App\Services;

use App\Events\GymStateChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Agrupa invalidaciones por petición y audiencia, únicamente tras commit. */
class RealtimeChanges
{
    public const RESOURCES = ['members', 'groups', 'payments', 'medical', 'access', 'requests', 'operators', 'audit', 'permissions', 'availability'];

    private array $pending = [];

    public function record(array $resources, string $audience = 'staff', ?Model $model = null): void
    {
        $resources = array_values(array_intersect(self::RESOURCES, $resources));
        if (! $resources) {
            return;
        }
        ($model?->getConnection() ?? DB::connection())->afterCommit(function () use ($resources, $audience) {
            $this->pending[$audience] = array_values(array_unique(array_merge($this->pending[$audience] ?? [], $resources)));
        });
    }

    public function flush(): void
    {
        if (DB::transactionLevel() > 0 || ! $this->pending) {
            return;
        }
        $pending = $this->pending;
        $this->pending = [];
        $id = (string) Str::uuid();
        $owner = array_values(array_unique(array_merge(...array_values($pending))));
        event(new GymStateChanged($id, $owner, 'owner'));
        $staff = $pending['staff'] ?? [];
        if ($staff) {
            event(new GymStateChanged($id, $staff, 'staff'));
        }
        foreach ($pending as $audience => $resources) {
            if (! str_starts_with($audience, 'requester:')) {
                continue;
            }
            $specific = array_values(array_diff($resources, $staff));
            if ($specific) {
                event(new GymStateChanged($id, $specific, 'requester', (int) substr($audience, 10)));
            }
        }
        if (array_intersect($owner, ['members', 'groups', 'payments', 'medical', 'permissions'])) {
            event(new GymStateChanged($id, ['availability'], 'terminal'));
        }
    }
}
