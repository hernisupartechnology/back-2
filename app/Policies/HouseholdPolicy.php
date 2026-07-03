<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

/**
 * Política de acceso a hogares.
 * Solo los miembros del hogar pueden verlo; solo el owner puede gestionarlo.
 */
class HouseholdPolicy
{
    /** Cualquier miembro del hogar puede verlo. */
    public function view(User $user, Household $household): bool
    {
        return $user->household_id === $household->id;
    }

    /** Solo el propietario puede renombrar o actualizar el hogar. */
    public function update(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id;
    }

    /** Solo el propietario puede invitar, cambiar roles o eliminar miembros. */
    public function manage(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id;
    }
}
