<?php

namespace App\Policies;

use App\Models\Referral;
use App\Models\User;

class ReferralPolicy
{
    public function view(User $user, Referral $referral): bool
    {
        return $user->household_id === $referral->household_id && $user->canManage($referral->patient);
    }

    public function create(User $user, User $patient): bool
    {
        return ! $user->isViewer() && $user->household_id === $patient->household_id && $user->canManage($patient);
    }

    public function update(User $user, Referral $referral): bool
    {
        return ! $user->isViewer() && $user->household_id === $referral->household_id && $user->canManage($referral->patient);
    }

    public function delete(User $user, Referral $referral): bool
    {
        return $this->update($user, $referral);
    }
}
