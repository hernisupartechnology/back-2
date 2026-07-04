<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentRecurrenceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesHousehold;
use Tests\TestCase;

/**
 * Flujo completo de una cita: necesidad → programada → realizada,
 * y generación automática de la siguiente cita cuando es recurrente.
 */
class AppointmentStatusFlowTest extends TestCase
{
    use CreatesHousehold, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createHousehold();
    }

    public function test_owner_can_register_a_need_and_schedule_it(): void
    {
        $store = $this->actingAs($this->owner)->postJson('/api/appointments', [
            'user_id' => $this->owner->id,
            'specialty' => 'Cardiología',
            'is_need' => true,
            'need_reason' => 'Dolor en el pecho',
            'need_urgency' => 'urgente',
        ]);

        $store->assertCreated()->assertJsonPath('data.status', 'necesidad');
        $appointmentId = $store->json('data.id');

        $schedule = $this->actingAs($this->owner)->patchJson("/api/appointments/{$appointmentId}/schedule", [
            'appointment_date' => now()->addDays(5)->toDateTimeString(),
        ]);

        $schedule->assertOk()
            ->assertJsonPath('data.status', 'programada')
            ->assertJsonPath('data.is_need', false);
    }

    public function test_marking_a_recurring_appointment_as_realizada_generates_the_next_one(): void
    {
        $appointment = Appointment::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'registered_by' => $this->owner->id,
            'specialty' => 'Endocrinología',
            'appointment_type' => 'control',
            'appointment_date' => now()->subDay(),
            'status' => 'programada',
            'is_recurring' => true,
            'recurrence_type' => 'mensual',
        ]);

        $this->assertSame(30, $appointment->fresh()->recurrence_interval_days);

        $response = $this->actingAs($this->owner)->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'realizada',
            'diagnosis' => 'Todo en orden',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'realizada');

        $this->assertDatabaseCount('appointments', 2);

        $next = Appointment::where('parent_appointment_id', $appointment->id)->first();
        $this->assertNotNull($next, 'Debe existir la siguiente cita de la cadena.');
        $this->assertSame(2, $next->recurrence_number);
        $this->assertSame('programada', $next->status);
        $this->assertTrue(
            $next->appointment_date->isSameDay($appointment->appointment_date->copy()->addDays(30)),
            'La siguiente cita debe calcularse a partir de recurrence_interval_days.'
        );

        $this->assertDatabaseCount('appointment_recurrence_log', 1);
        $log = AppointmentRecurrenceLog::first();
        $this->assertSame($appointment->id, $log->parent_appointment_id);
        $this->assertSame($next->id, $log->appointment_id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'appointment.recurrence_generated',
        ]);
    }

    public function test_generate_next_endpoint_is_blocked_once_already_generated(): void
    {
        $appointment = Appointment::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'registered_by' => $this->owner->id,
            'specialty' => 'Endocrinología',
            'appointment_type' => 'control',
            'appointment_date' => now(),
            'status' => 'programada',
            'is_recurring' => true,
            'recurrence_type' => 'mensual',
        ]);

        $appointment->update(['status' => 'realizada']);
        $this->assertTrue($appointment->fresh()->next_recurrence_generated);

        $this->actingAs($this->owner)
            ->postJson("/api/appointments/{$appointment->id}/generate-next")
            ->assertStatus(422);
    }

    public function test_cancelling_an_appointment_requires_reason_and_who_cancelled(): void
    {
        $appointment = Appointment::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'registered_by' => $this->owner->id,
            'specialty' => 'Dermatología',
            'appointment_date' => now()->addDays(3),
            'status' => 'programada',
        ]);

        $this->actingAs($this->owner)
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'cancelada'])
            ->assertStatus(422);

        $this->actingAs($this->owner)
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'cancelada',
                'cancelled_reason' => 'El médico no pudo atender',
                'cancelled_by' => 'ips',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelada')
            ->assertJsonPath('data.cancelled_by', 'ips');
    }
}
