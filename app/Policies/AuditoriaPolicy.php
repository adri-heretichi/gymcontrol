<?php

namespace App\Policies;

use App\Models\Auditoria;
use App\Models\User;

class AuditoriaPolicy
{
    /**
     * Solo la Dueña puede consultar los registros completos de auditoría.
     */
    public function viewAny(User $user): bool
    {
        return $user->esDuena();
    }

    public function view(User $user, Auditoria $auditoria): bool
    {
        return $user->esDuena();
    }
}
