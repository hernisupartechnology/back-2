<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesHousehold;
use Tests\TestCase;

/**
 * Control de acceso por rol: owner ve/gestiona todo el hogar; member solo
 * su propio historial y el de sus viewers supervisados; viewer es
 * únicamente de lectura y solo sobre su propio historial.
 */
class RoleAccessControlTest extends TestCase
{
    use CreatesHousehold, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createHousehold();
    }

    public function test_viewer_cannot_create_appointments(): void
    {
        $this->actingAs($this->viewer)
            ->postJson('/api/appointments', [
                'user_id' => $this->viewer->id,
                'specialty' => 'Pediatría',
                'is_need' => true,
                'need_reason' => 'Control',
            ])
            ->assertStatus(403);
    }

    public function test_viewer_can_read_their_own_appointments(): void
    {
        Appointment::create([
            'household_id' => $this->household->id,
            'user_id' => $this->viewer->id,
            'registered_by' => $this->member->id,
            'specialty' => 'Pediatría',
            'is_need' => true,
            'need_urgency' => 'rutina',
            'status' => 'necesidad',
        ]);

        $this->actingAs($this->viewer)
            ->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_member_cannot_manage_appointments_of_unrelated_adults(): void
    {
        $otherAdult = User::factory()->create([
            'role' => 'member',
            'household_id' => $this->household->id,
        ]);

        $this->actingAs($this->member)
            ->postJson('/api/appointments', [
                'user_id' => $otherAdult->id,
                'specialty' => 'Cardiología',
                'is_need' => true,
                'need_reason' => 'x',
            ])
            ->assertStatus(403);
    }

    public function test_member_can_manage_their_supervised_viewer(): void
    {
        $this->actingAs($this->member)
            ->postJson('/api/appointments', [
                'user_id' => $this->viewer->id,
                'specialty' => 'Pediatría',
                'is_need' => true,
                'need_reason' => 'Control de crecimiento',
            ])
            ->assertCreated();
    }

    public function test_owner_sees_the_whole_household_in_the_index(): void
    {
        Appointment::create([
            'household_id' => $this->household->id, 'user_id' => $this->owner->id,
            'registered_by' => $this->owner->id, 'specialty' => 'A', 'is_need' => true, 'status' => 'necesidad',
        ]);
        Appointment::create([
            'household_id' => $this->household->id, 'user_id' => $this->member->id,
            'registered_by' => $this->member->id, 'specialty' => 'B', 'is_need' => true, 'status' => 'necesidad',
        ]);
        Appointment::create([
            'household_id' => $this->household->id, 'user_id' => $this->viewer->id,
            'registered_by' => $this->member->id, 'specialty' => 'C', 'is_need' => true, 'status' => 'necesidad',
        ]);

        $this->actingAs($this->owner)
            ->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_only_owner_can_generate_the_full_household_report(): void
    {
        $this->actingAs($this->member)
            ->postJson('/api/reports/generate', ['report_type' => 'household', 'format' => 'pdf'])
            ->assertStatus(403);

        $this->actingAs($this->owner)
            ->postJson('/api/reports/generate', ['report_type' => 'household', 'format' => 'pdf'])
            ->assertCreated();
    }

    public function test_viewer_cannot_create_medications_for_themselves(): void
    {
        $this->actingAs($this->viewer)
            ->postJson('/api/medications', [
                'user_id' => $this->viewer->id,
                'name' => 'Acetaminofén',
                'dosage' => '500mg',
                'frequency' => 'cada 8 horas',
            ])
            ->assertStatus(403);
    }

    public function test_member_can_view_but_not_modify_a_medication_of_an_unrelated_adult(): void
    {
        $otherAdult = User::factory()->create([
            'role' => 'member', 'household_id' => $this->household->id,
        ]);

        $medication = Medication::create([
            'household_id' => $this->household->id, 'user_id' => $otherAdult->id,
            'registered_by' => $otherAdult->id, 'name' => 'X', 'dosage' => '1', 'frequency' => '1',
        ]);

        $this->actingAs($this->member)
            ->getJson('/api/medications')
            ->assertOk()
            ->assertJsonCount(0, 'data'); // no debe verlo en su propio listado

        $this->actingAs($this->member)
            ->putJson("/api/medications/{$medication->id}", ['name' => 'Y'])
            ->assertStatus(403);
    }
}
