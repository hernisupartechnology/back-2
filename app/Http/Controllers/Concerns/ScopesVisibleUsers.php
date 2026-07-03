<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

/**
 * IDs de usuarios cuyo historial puede ver un usuario autenticado dado su rol:
 * owner → todo el hogar; member → él mismo + sus viewers supervisados; viewer → solo él mismo.
 */
trait ScopesVisibleUsers
{
    /** @return array<int> */
    private function visibleUserIds(User $user): array
    {
        if ($user->isOwner()) {
            return User::where('household_id', $user->household_id)->pluck('id')->all();
        }

        if ($user->isMember()) {
            return User::where('household_id', $user->household_id)
                ->where(fn ($q) => $q->where('id', $user->id)->orWhere('supervised_by', $user->id))
                ->pluck('id')->all();
        }

        return [$user->id];
    }
}
