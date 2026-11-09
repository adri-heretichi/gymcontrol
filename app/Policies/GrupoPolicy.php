<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;

class GrupoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function view(User $user, Grupo $grupo): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function create(User $user): bool
    {
        return $user->esDuena();
    }

    public function update(User $user, Grupo $grupo): bool
    {
        return $user->esDuena();
    }

    public function delete(User $user, Grupo $grupo): bool
    {
        return $user->esDuena();
    }
}
