<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de cada ocurrencia en la cadena de citas recurrentes. */
class AppointmentRecurrenceLog extends Model
{
    protected $table = 'appointment_recurrence_log';

    protected $fillable = [
        'parent_appointment_id', 'appointment_id',
        'recurrence_number', 'scheduled_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['scheduled_date' => 'datetime'];
    }

    public function parentAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'parent_appointment_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
