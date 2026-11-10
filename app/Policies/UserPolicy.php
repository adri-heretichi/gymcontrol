<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * La administración de operadores es exclusiva de la Dueña.
     */
    public function viewAny(User $user): bool
    {
        return $user->esDuena();
    }

    public function view(User $user, User $model): bool
    {
        return $user->esDuena();
    }

    public function create(User $user): bool
    {
        return $user->esDuena();
    }

    public function update(User $user, User $model): bool
    {
        return $user->esDuena();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->esDuena();
    }
}
