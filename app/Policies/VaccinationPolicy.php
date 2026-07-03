<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vaccination;

class VaccinationPolicy
{
    public function view(User $user, Vaccination $vaccination): bool
    {
        return $user->household_id === $vaccination->patient->household_id && $user->canManage($vaccination->patient);
    }

    public function create(User $user, User $patient): bool
    {
        return ! $user->isViewer() && $user->household_id === $patient->household_id && $user->canManage($patient);
    }

    public function update(User $user, Vaccination $vaccination): bool
    {
        return ! $user->isViewer() && $user->household_id === $vaccination->patient->household_id && $user->canManage($vaccination->patient);
    }

    public function delete(User $user, Vaccination $vaccination): bool
    {
        return $this->update($user, $vaccination);
    }
}
