<?php

namespace App\Policies;

use App\Models\Inscripcion;
use App\Models\User;

class InscripcionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function view(User $user, Inscripcion $inscripcion): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function create(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function update(User $user, Inscripcion $inscripcion): bool
    {
        return $user->esDuena();
    }

    public function aprobar(User $user, Inscripcion $inscripcion): bool
    {
        return $user->esDuena();
    }

    public function rechazar(User $user, Inscripcion $inscripcion): bool
    {
        return $user->esDuena();
    }

    public function baja(User $user, Inscripcion $inscripcion): bool
    {
        return $user->esDuena();
    }

    public function cambiar(User $user, Inscripcion $inscripcion): bool
    {
        return $user->esDuena();
    }

    public function delete(User $user, Inscripcion $inscripcion): bool
    {
        return $user->esDuena();
    }
}
