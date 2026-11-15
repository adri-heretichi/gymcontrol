<?php

namespace App\Livewire\Concerns;

use App\Services\RealtimeChanges;

trait SyncsGymState
{
    protected array $gymSyncIncomingResources = [];

    public function syncGymState(array $resources = ['*']): void
    {
        abort_if(count($resources) > 20 || array_filter($resources, fn ($r) => ! is_string($r)), 422);
        if (! ($this->gymSyncPublic ?? false)) {
            $currentUser = auth()->user()?->fresh();
            abort_unless($currentUser, 403);
            auth()->setUser($currentUser);
            abort_unless(auth()->user()?->esDuena() || auth()->user()?->esRecepcionista(), 403);
            if ($this->gymSyncOwnerOnly ?? false) {
                abort_unless(auth()->user()->esDuena(), 403);
            }
        }
        $allowed = array_merge(RealtimeChanges::RESOURCES, ['*', 'clock']);
        abort_if(array_diff($resources, $allowed), 422);
        $dependencies = array_merge($this->gymSyncResources, ['permissions']);
        if (! in_array('*', $resources, true) && ! array_intersect($resources, $dependencies)) {
            $this->skipRender();

            return;
        }
        $this->gymSyncIncomingResources = $resources;
        $this->refreshGymReadState();
    }

    protected function refreshGymReadState(): void {}
}
