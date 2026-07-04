<?php

namespace Tests\Feature;

use App\Jobs\CheckHealthAlertsJob;
use App\Models\Appointment;
use App\Models\Medication;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesHousehold;
use Tests\TestCase;

/**
 * CheckHealthAlertsJob — genera notificaciones para necesidades urgentes y
 * medicamentos por vencer, y es idempotente (no duplica si se re-ejecuta
 * el mismo día).
 */
class HealthAlertsJobTest extends TestCase
{
    use CreatesHousehold, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createHousehold();
    }

    public function test_it_notifies_urgent_unscheduled_needs(): void
    {
        Appointment::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'registered_by' => $this->owner->id,
            'specialty' => 'Cardiología',
            'is_need' => true,
            'need_urgency' => 'urgente',
            'need_registered_date' => now()->toDateString(),
            'status' => 'necesidad',
        ]);

        app(CheckHealthAlertsJob::class)->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'appointment.need_urgent',
        ]);
    }

    public function test_it_notifies_medications_close_to_expiring(): void
    {
        Medication::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'registered_by' => $this->owner->id,
            'name' => 'Losartán',
            'dosage' => '50mg',
            'frequency' => 'una vez al día',
            'duration_days' => 3,
            'start_date' => now()->subDays(2)->toDateString(), // vence en 1 día
            'is_recurring' => true,
            'alert_days_before' => 10,
            'status' => 'en_uso',
        ]);

        app(CheckHealthAlertsJob::class)->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'medication.expiring_soon',
        ]);
    }

    public function test_it_does_not_duplicate_notifications_on_a_second_run_the_same_day(): void
    {
        Appointment::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'registered_by' => $this->owner->id,
            'specialty' => 'Cardiología',
            'is_need' => true,
            'need_urgency' => 'urgente',
            'need_registered_date' => now()->toDateString(),
            'status' => 'necesidad',
        ]);

        app(CheckHealthAlertsJob::class)->handle();
        app(CheckHealthAlertsJob::class)->handle();

        $this->assertSame(1, Notification::where('type', 'appointment.need_urgent')->count());
    }
}
