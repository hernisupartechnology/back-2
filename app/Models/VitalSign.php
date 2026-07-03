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
}

// ──────────────────────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rangos normales personalizables de signos vitales por miembro. */
class VitalSignRange extends Model
{
    protected $fillable = [
        'user_id',
        'systolic_min', 'systolic_max',
        'diastolic_min', 'diastolic_max',
        'glucose_min', 'glucose_max',
        'oxygen_min',
    ];

    protected function casts(): array
    {
        return [
            'glucose_min' => 'decimal:1',
            'glucose_max' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Verifica si una medición de presión sistólica está fuera del rango normal.
     */
    public function isSystolicOutOfRange(int $value): bool
    {
        return $value < $this->systolic_min || $value > $this->systolic_max;
    }

    public function isDiastolicOutOfRange(int $value): bool
    {
        return $value < $this->diastolic_min || $value > $this->diastolic_max;
    }

    public function isGlucoseOutOfRange(float $value): bool
    {
        return $value < $this->glucose_min || $value > $this->glucose_max;
    }

    public function isOxygenOutOfRange(int $value): bool
    {
        return $value < $this->oxygen_min;
    }
}
