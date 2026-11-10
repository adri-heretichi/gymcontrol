<?php

namespace App\Policies;

use App\Models\Solicitud;
use App\Models\User;

class SolicitudPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function view(User $user, Solicitud $solicitud): bool
    {
        if ($user->esDuena()) {
            return true;
        }

        return $user->esRecepcionista() && $solicitud->solicitante_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function resolver(User $user, Solicitud $solicitud): bool
    {
        return $user->esDuena();
    }

    public function delete(User $user, Solicitud $solicitud): bool
    {
        return false;
    }
}
