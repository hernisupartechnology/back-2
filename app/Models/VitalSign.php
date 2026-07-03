<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    protected $fillable = [
        'user_id', 'registered_by', 'appointment_id', 'measurement_date',
        'systolic_pressure', 'diastolic_pressure', 'heart_rate',
        'blood_glucose', 'weight', 'height', 'temperature', 'oxygen_saturation', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'measurement_date' => 'datetime',
            'blood_glucose' => 'decimal:1',
            'weight' => 'decimal:2',
            'height' => 'decimal:1',
            'temperature' => 'decimal:1',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** Rango normal del paciente — permite eager-load para evitar N+1 al calcular alertas. */
    public function range(): BelongsTo
    {
        return $this->belongsTo(VitalSignRange::class, 'user_id', 'user_id');
    }
}
