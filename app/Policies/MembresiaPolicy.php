<?php

namespace App\Policies;

use App\Models\Membresia;
use App\Models\User;

class MembresiaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function view(User $user, Membresia $membresia): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function create(User $user): bool
    {
        return $user->esDuena();
    }

    public function update(User $user, Membresia $membresia): bool
    {
        return $user->esDuena();
    }

    public function delete(User $user, Membresia $membresia): bool
    {
        return $user->esDuena();
    }
}
