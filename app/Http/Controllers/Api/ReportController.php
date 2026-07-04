<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportHistory;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Generación de reportes (individual, del hogar, alertas, incapacidades)
 * en PDF o Excel, con historial de los últimos 10 reportes por usuario.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report_type' => 'required|in:individual,household,alerts,leaves',
            'format' => 'required|in:pdf,excel',
            'user_id' => 'required_if:report_type,individual|integer|exists:users,id',
            'sections' => 'nullable|array',
            'period' => 'nullable|in:month,year,all',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $user = $request->user();

        if ($data['report_type'] === 'individual') {
            $patient = User::findOrFail($data['user_id']);
            abort_unless($user->household_id === $patient->household_id && $user->canManage($patient), 403,
                'No tienes acceso al historial de este miembro.');
        } elseif (in_array($data['report_type'], ['household', 'alerts', 'leaves'], true)) {
            abort_unless($user->isOwner() || $data['report_type'] !== 'household', 403,
                'Solo el propietario del hogar puede generar el reporte completo del hogar.');
        }

        $history = $this->reportService->generate($user, $data['report_type'], $data['format'], $data);

        return response()->json([
            'message' => '¡Reporte generado correctamente!',
            'report' => $history,
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $reports = ReportHistory::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json(['reports' => $reports]);
    }

    public function download(Request $request, int $id)
    {
        $report = ReportHistory::where('user_id', $request->user()->id)->findOrFail($id);

        abort_unless($report->status === 'completado' && $report->file_path, 404, 'El reporte no está disponible.');
        abort_unless(Storage::disk('local')->exists($report->file_path), 404, 'El archivo ya no está disponible.');

        return Storage::disk('local')->download($report->file_path, $report->file_name);
    }
}
