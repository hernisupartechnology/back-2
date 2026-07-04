<?php

namespace Tests\Feature\Concerns;

use App\Models\Household;
use App\Models\User;

/**
 * Crea un hogar de prueba con owner + member + viewer supervisado,
 * evitando depender de factories dedicadas para cada modelo.
 */
trait CreatesHousehold
{
    protected User $owner;

    protected User $member;

    protected User $viewer;

    protected Household $household;

    protected function createHousehold(): void
    {
        $this->owner = User::factory()->create(['role' => 'owner']);

        $this->household = Household::create([
            'name' => 'Hogar de prueba',
            'owner_id' => $this->owner->id,
        ]);

        $this->owner->update(['household_id' => $this->household->id]);

        $this->member = User::factory()->create([
            'role' => 'member',
            'household_id' => $this->household->id,
        ]);

        $this->viewer = User::factory()->create([
            'role' => 'viewer',
            'household_id' => $this->household->id,
            'is_minor' => true,
            'supervised_by' => $this->member->id,
        ]);
    }
}
