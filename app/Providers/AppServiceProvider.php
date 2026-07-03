<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\MedicalLeave;
use App\Models\Medication;
use App\Models\MedicationIntakeLog;
use App\Observers\AppointmentObserver;
use App\Observers\MedicalLeaveObserver;
use App\Observers\MedicationIntakeObserver;
use App\Observers\MedicationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios de la aplicación.
     */
    public function register(): void
    {
        //
    }

    /**
     * Inicializa servicios al arrancar la aplicación.
     * Registra observers de Eloquent y configuraciones globales.
     */
    public function boot(): void
    {
        // Observers — calculan campos derivados automáticamente
        Appointment::observe(AppointmentObserver::class);
        Medication::observe(MedicationObserver::class);
        MedicationIntakeLog::observe(MedicationIntakeObserver::class);
        MedicalLeave::observe(MedicalLeaveObserver::class);
    }
}
