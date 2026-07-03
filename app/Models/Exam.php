<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'appointment_id', 'household_id', 'user_id', 'registered_by',
        'name', 'exam_type', 'lab_or_center', 'urgency', 'status',
        'denied_reason', 'authorization_date', 'authorization_number',
        'scheduled_date', 'performed_date', 'result_date', 'result_summary',
        'delivered_to_doctor_date', 'cancelled_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'datetime',
            'authorization_date' => 'date',
            'performed_date' => 'date',
            'result_date' => 'date',
            'delivered_to_doctor_date' => 'date',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class, 'related_id')
            ->where('related_type', 'exam');
    }
}
