<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Resources\MedicalDocumentResource;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos médicos adjuntos. Los archivos se guardan en el disco "local"
 * (storage/app/private/...) y SIEMPRE se sirven vía este controlador
 * autenticado — nunca por una ruta pública o el disco "public".
 */
class MedicalDocumentController extends Controller
{
    use ScopesVisibleUsers;

    private const MAX_SIZE_KB = 10240; // 10MB

    private const PER_PAGE = 24;

    public function index(Request $request): JsonResponse
    {
        $visibleIds = $this->visibleUserIds($request->user());

        if ($request->filled('userId')) {
            abort_unless(in_array((int) $request->integer('userId'), $visibleIds, true), 403, 'No tienes acceso a los documentos de este miembro.');
            $visibleIds = [$request->integer('userId')];
        }

        $query = MedicalDocument::whereIn('user_id', $visibleIds)->with('patient');

        if ($request->filled('type')) {
            $query->where('document_type', $request->string('type'));
        }
        if ($request->filled('relatedType')) {
            $query->where('related_type', $request->string('relatedType'));
        }
        if ($request->filled('relatedId')) {
            $query->where('related_id', $request->integer('relatedId'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
        }

        // Forma plana (data/current_page/last_page/...), consistente con
        // NotificationController/MedicalLeaveController — la galería de
        // documentos de un paciente crónico con años de historial puede
        // acumular cientos de archivos.
        $documents = $query->orderByDesc('document_date')->orderByDesc('uploaded_at')
            ->paginate(min(60, $request->integer('per_page') ?: self::PER_PAGE));

        return response()->json([
            'data' => MedicalDocumentResource::collection($documents->items()),
            'current_page' => $documents->currentPage(),
            'last_page' => $documents->lastPage(),
            'per_page' => $documents->perPage(),
            'total' => $documents->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = User::findOrFail($request->integer('user_id'));
        $this->authorize('create', [MedicalDocument::class, $patient]);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'document_type' => 'required|in:historia_clinica,orden_medicamento,orden_examen,resultado_examen,autorizacion_eps,incapacidad,remision,vacuna,otro',
            'document_date' => 'nullable|date',
            'related_type' => 'nullable|in:appointment,medication,exam,referral,medical_leave,vaccination,general',
            'related_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'file' => 'required|file|max:'.self::MAX_SIZE_KB.'|mimes:jpg,jpeg,png,webp,pdf',
        ]);

        $file = $request->file('file');
        $realMime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file->getRealPath());
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        abort_unless(in_array($realMime, $allowedMimes, true), 422, 'El archivo no es una imagen o PDF válido.');

        $year = now()->year;
        $directory = "medical/{$user->household_id}/{$patient->id}/{$year}";
        $filename = Str::slug(pathinfo($data['title'], PATHINFO_FILENAME)).'_'.Str::uuid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs($directory, $filename, 'local');

        $document = MedicalDocument::create([
            'household_id' => $user->household_id,
            'user_id' => $patient->id,
            'uploaded_by' => $user->id,
            'related_type' => $data['related_type'] ?? 'general',
            'related_id' => $data['related_id'] ?? null,
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $realMime === 'application/pdf' ? 'pdf' : 'image',
            'file_size' => $file->getSize(),
            'document_date' => $data['document_date'] ?? now()->toDateString(),
            'uploaded_at' => now(),
        ]);

        return (new MedicalDocumentResource($document->load('patient')))
            ->additional(['message' => 'Documento subido correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    /** Sirve el archivo de forma autenticada — verifica hogar antes de entregar el binario. */
    public function show(int $id): StreamedResponse|Response
    {
        $document = MedicalDocument::findOrFail($id);
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'El archivo ya no está disponible.');

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => $document->file_type === 'pdf' ? 'application/pdf' : 'image/'.pathinfo($document->file_path, PATHINFO_EXTENSION)]
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $document = MedicalDocument::findOrFail($id);
        $this->authorize('delete', $document);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Documento eliminado correctamente.']);
    }
}
