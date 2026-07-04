<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historial de reportes generados — se conservan los últimos 10 por usuario. */
class ReportHistory extends Model
{
    protected $table = 'report_history';

    protected $fillable = [
        'user_id', 'report_type', 'format', 'parameters',
        'file_path', 'file_name', 'file_size',
        'status', 'error_message', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
