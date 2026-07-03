<?php

namespace Database\Seeders;

use App\Models\Allergy;
use App\Models\Appointment;
use App\Models\ChronicCondition;
use App\Models\Doctor;
use App\Models\Household;
use App\Models\Medication;
use App\Models\MedicationSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de demostración — crea un hogar completo con datos realistas
 * para poder probar el flujo de la aplicación de inmediato tras el
 * `php artisan migrate --seed`.
 *
 * Credenciales de acceso (todas con la misma contraseña):
 *   owner@uparvital.com  / password  → Hernis (owner)
 *   member@uparvital.com / password  → Laura  (member)
 *   (el hijo "Mateo" es viewer, supervisado por Laura — no inicia sesión propia en la demo)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // ── Hogar y miembros ─────────────────────────────────────────────
        $owner = User::create([
            'name' => 'Hernis Mercado',
            'email' => 'owner@uparvital.com',
            'password' => $password,
            'role' => 'owner',
            'phone' => '3001234567',
            'birthdate' => '1988-05-14',
            'gender' => 'masculino',
            'blood_type' => 'O+',
            'eps' => 'Sura EPS',
            'ips_preferida' => 'IPS Sura Norte',
            'track_vital_signs' => true,
        ]);

        $household = Household::create([
            'name' => 'Familia Mercado',
            'owner_id' => $owner->id,
        ]);

        $owner->update(['household_id' => $household->id]);

        $member = User::create([
            'name' => 'Laura Gómez',
            'email' => 'member@uparvital.com',
            'password' => $password,
            'role' => 'member',
            'household_id' => $household->id,
            'phone' => '3007654321',
            'birthdate' => '1990-09-02',
            'gender' => 'femenino',
            'blood_type' => 'A+',
            'eps' => 'Sura EPS',
            'ips_preferida' => 'IPS Sura Norte',
        ]);

        $child = User::create([
            'name' => 'Mateo Mercado',
            'email' => 'mateo@uparvital.com',
            'password' => $password,
            'role' => 'viewer',
            'household_id' => $household->id,
            'birthdate' => '2019-03-20',
            'gender' => 'masculino',
            'blood_type' => 'O+',
            'eps' => 'Sura EPS',
            'is_minor' => true,
            'supervised_by' => $member->id,
        ]);

        // ── Médicos del hogar ────────────────────────────────────────────
        $pediatra = Doctor::create([
            'household_id' => $household->id,
            'name' => 'Dra. Camila Restrepo',
            'specialty' => 'Pediatría',
            'registration_number' => 'RM-45210',
            'phone' => '6014567890',
            'ips' => 'IPS Sura Norte',
        ]);

        $medicoGeneral = Doctor::create([
            'household_id' => $household->id,
            'name' => 'Dr. Andrés Torres',
            'specialty' => 'Medicina General',
            'ips' => 'IPS Sura Norte',
        ]);

        // ── Alergias y condiciones crónicas ──────────────────────────────
        Allergy::create([
            'user_id' => $owner->id,
            'type' => 'medicamento',
            'name' => 'Penicilina',
            'reaction' => 'Erupción cutánea y dificultad para respirar',
            'severity' => 'grave',
            'is_active' => true,
        ]);

        ChronicCondition::create([
            'user_id' => $owner->id,
            'name' => 'Hipertensión arterial',
            'diagnosed_date' => '2022-06-10',
            'treating_doctor_id' => $medicoGeneral->id,
            'is_active' => true,
        ]);

        // ── Citas y necesidades ───────────────────────────────────────────

        // Necesidad urgente sin agendar → semáforo rojo
        Appointment::create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'registered_by' => $owner->id,
            'specialty' => 'Cardiología',
            'appointment_type' => 'consulta',
            'is_need' => true,
            'need_reason' => 'Dolor en el pecho y palpitaciones ocasionales',
            'need_urgency' => 'urgente',
            'need_registered_date' => now()->subDays(2)->toDateString(),
            'status' => 'necesidad',
        ]);

        // Cita programada próxima (en 3 días) → semáforo amarillo
        Appointment::create([
            'household_id' => $household->id,
            'user_id' => $child->id,
            'registered_by' => $member->id,
            'doctor_id' => $pediatra->id,
            'specialty' => 'Pediatría',
            'appointment_type' => 'control',
            'ips' => 'IPS Sura Norte',
            'appointment_date' => now()->addDays(3)->setTime(9, 0),
            'reason' => 'Control de crecimiento y desarrollo',
            'status' => 'programada',
            'is_recurring' => true,
            'recurrence_type' => 'trimestral',
        ]);

        // Cita realizada hace un mes, con diagnóstico
        Appointment::create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'registered_by' => $member->id,
            'doctor_id' => $medicoGeneral->id,
            'specialty' => 'Medicina General',
            'appointment_type' => 'consulta',
            'ips' => 'IPS Sura Norte',
            'appointment_date' => now()->subMonth(),
            'reason' => 'Chequeo general',
            'diagnosis' => 'Paciente sana, se ordena examen de rutina',
            'status' => 'realizada',
        ]);

        // ── Medicamento recurrente con seguimiento de tomas ──────────────
        $medicamento = Medication::create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'registered_by' => $owner->id,
            'name' => 'Losartán',
            'active_ingredient' => 'Losartán potásico',
            'presentation' => 'tabletas',
            'dosage' => '50mg',
            'frequency' => 'una vez al día',
            'duration_days' => 30,
            'quantity' => 30,
            'start_date' => now()->subDays(10)->toDateString(),
            'is_recurring' => true,
            'recurrence_days' => 30,
            'alert_days_before' => 10,
            'status' => 'en_uso',
            'track_intake' => true,
            'intake_quantity_per_dose' => 1,
            'low_stock_alert_doses' => 5,
        ]);

        MedicationSchedule::create([
            'medication_id' => $medicamento->id,
            'user_id' => $owner->id,
            'time_of_day' => '07:00:00',
            'label' => 'Con el desayuno',
            'reminder_minutes_before' => 5,
        ]);
    }
}
