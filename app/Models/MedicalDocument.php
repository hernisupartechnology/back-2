<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento médico adjunto.
 * Los archivos NUNCA se sirven por rutas públicas — siempre via controlador autenticado.
 */
class MedicalDocument extends Model
{
    protected $fillable = [
        'household_id', 'user_id', 'uploaded_by',
        'related_type', 'related_id',
        'document_type', 'title', 'description',
        'file_path', 'file_name', 'file_type', 'file_size',
        'document_date', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'uploaded_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
