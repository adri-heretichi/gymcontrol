<?php

namespace App\Policies;

use App\Models\Socio;
use App\Models\User;

class SocioPolicy
{
    /**
     * Determina si el usuario puede listar socios.
     */
    public function viewAny(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    /**
     * Determina si el usuario puede ver la ficha de un socio.
     */
    public function view(User $user, Socio $socio): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    /**
     * Determina si el usuario puede registrar nuevos socios.
     * Tanto la dueña como la recepcionista están autorizadas.
     */
    public function create(User $user): bool
    {
        return $user->esDuena() || $user->esRecepcionista();
    }

    /**
     * Determina si el usuario puede actualizar datos generales y de membresía del socio.
     * Exclusivo de la dueña.
     */
    public function update(User $user, Socio $socio): bool
    {
        return $user->esDuena();
    }

    /**
     * Determina si el usuario puede dar de baja lógica o eliminar socios.
     * Exclusivo de la dueña.
     */
    public function delete(User $user, Socio $socio): bool
    {
        return $user->esDuena();
    }

    /**
     * Determina si el usuario puede definir o modificar la decisión médica individual.
     * Exclusivo de la dueña.
     */
    public function gestionarDecisionMedica(User $user, Socio $socio): bool
    {
        return $user->esDuena();
    }

    /**
     * Determina si el usuario puede emitir o revocar credenciales (QR y PIN).
     * Exclusivo de la dueña.
     */
    public function emitirCredenciales(User $user, Socio $socio): bool
    {
        return $user->esDuena();
    }
}
