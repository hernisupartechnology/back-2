<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Horario programado de una toma de medicamento. */
class MedicationSchedule extends Model
{
    protected $fillable = [
        'medication_id', 'user_id', 'time_of_day', 'label',
        'days_of_week', 'is_active', 'reminder_minutes_before',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intakeLogs(): HasMany
    {
        return $this->hasMany(MedicationIntakeLog::class);
    }

    /**
     * Verifica si este horario aplica para el día de la semana dado.
     *
     * @param  int  $dayOfWeek  1=Lunes ... 7=Domingo (ISO 8601)
     */
    public function appliesOnDay(int $dayOfWeek): bool
    {
        // Si days_of_week es null → aplica todos los días
        if (is_null($this->days_of_week)) {
            return true;
        }

        return in_array($dayOfWeek, $this->days_of_week);
    }
}
