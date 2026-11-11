<?php

namespace App\Policies;

use App\Models\AptoFisico;
use App\Models\User;

class AptoFisicoPolicy
{
    /**
     * Determina si el usuario puede listar certificados.
     */
    public function viewAny(User $user): bool
    {
        return $user->esDuena();
    }

    /**
     * Determina si el usuario puede ver o descargar un certificado.
     */
    public function view(User $user, AptoFisico $aptoFisico): bool
    {
        return $user->esDuena();
    }

    /**
     * Determina si el usuario puede registrar documentación médica.
     */
    public function create(User $user): bool
    {
        return $user->esDuena();
    }

    /**
     * Determina si el usuario puede editar datos de un certificado existente.
     * Exclusivo de la Dueña.
     */
    public function update(User $user, AptoFisico $aptoFisico): bool
    {
        return $user->esDuena();
    }

    /**
     * Determina si el usuario puede eliminar un certificado.
     * Restringido para preservar la documentación histórica.
     */
    public function delete(User $user, AptoFisico $aptoFisico): bool
    {
        return false;
    }
}
