<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

/**
 * Política del catálogo de médicos — compartido por todo el hogar.
 * Cualquier miembro lo ve; owner y member pueden gestionarlo, viewer no.
 */
class DoctorPolicy
{
    public function view(User $user, Doctor $doctor): bool
    {
        return $user->household_id === $doctor->household_id;
    }

    public function create(User $user): bool
    {
        return ! $user->isViewer();
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return ! $user->isViewer() && $user->household_id === $doctor->household_id;
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        return $this->update($user, $doctor);
    }
}
