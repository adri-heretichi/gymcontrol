<?php

namespace App\Policies;

use App\Models\Disciplina;
use App\Models\User;

class DisciplinaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function view(User $user, Disciplina $disciplina): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    public function create(User $user): bool
    {
        return $user->esDuena();
    }

    public function update(User $user, Disciplina $disciplina): bool
    {
        return $user->esDuena();
    }

    public function delete(User $user, Disciplina $disciplina): bool
    {
        return $user->esDuena();
    }
}
