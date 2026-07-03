<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de documento médico. NUNCA expone file_path (ruta privada de
 * almacenamiento) — el archivo se descarga vía GET /medical-documents/{id}.
 */
class MedicalDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'user_id' => $this->user_id,
            'uploaded_by' => $this->uploaded_by,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'document_type' => $this->document_type,
            'title' => $this->title,
            'description' => $this->description,
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'document_date' => $this->document_date?->format('Y-m-d'),
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'name' => $this->patient->name, 'avatar' => $this->patient->avatar,
            ]),
        ];
    }
}
