<?php

use App\Http\Controllers\Api\AllergyController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChronicConditionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\MedicalDocumentController;
use App\Http\Controllers\Api\MedicalLeaveController;
use App\Http\Controllers\Api\MedicationController;
use App\Http\Controllers\Api\MedicationIntakeController;
use App\Http\Controllers\Api\MedicationScheduleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\VaccinationController;
use App\Http\Controllers\Api\VitalSignController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — UparVital
|--------------------------------------------------------------------------
*/

// ── Health check público (PWA offline detection) ────────────────────────────
Route::get('/health-check', fn () => response()->json(['status' => 'ok', 'app' => 'UparVital']));

// ── Rutas públicas de autenticación ─────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// ── Rutas protegidas con Sanctum ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Perfil del usuario autenticado
    Route::put('/auth/profile', [ProfileController::class, 'update']);
    Route::post('/auth/profile/avatar', [ProfileController::class, 'updateAvatar']);

    // ── Hogares ─────────────────────────────────────────────────────────────
    Route::prefix('households')->group(function () {
        Route::post('/', [HouseholdController::class, 'store']);
        Route::get('/current', [HouseholdController::class, 'current']);
        Route::put('/{id}', [HouseholdController::class, 'update']);
        Route::get('/{id}/members', [HouseholdController::class, 'members']);
        Route::post('/invite', [HouseholdController::class, 'invite']);
        Route::post('/join', [HouseholdController::class, 'join']);
        Route::put('/{id}/members/{userId}/role', [HouseholdController::class, 'updateRole']);
        Route::put('/{id}/members/{userId}/supervisor', [HouseholdController::class, 'updateSupervisor']);
        Route::delete('/{id}/members/{userId}', [HouseholdController::class, 'removeMember']);
        Route::post('/{id}/transfer-ownership', [HouseholdController::class, 'transferOwnership']);
    });

    // ── Dashboard ───────────────────────────────────────────────────────────
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/alerts', [DashboardController::class, 'alerts']);
        Route::get('/activity', [DashboardController::class, 'activity']);
    });

    // ── Citas y necesidades ─────────────────────────────────────────────────
    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::get('/{id}', [AppointmentController::class, 'show']);
        Route::put('/{id}', [AppointmentController::class, 'update']);
        Route::patch('/{id}/status', [AppointmentController::class, 'changeStatus']);
        Route::patch('/{id}/schedule', [AppointmentController::class, 'scheduleNeed']);
        Route::post('/{id}/generate-next', [AppointmentController::class, 'generateNext']);
        Route::get('/{id}/recurrence-log', [AppointmentController::class, 'recurrenceLog']);
        Route::delete('/{id}', [AppointmentController::class, 'destroy']);
    });

    // ── Medicamentos ────────────────────────────────────────────────────────
    Route::prefix('medications')->group(function () {
        Route::get('/', [MedicationController::class, 'index']);
        Route::post('/', [MedicationController::class, 'store']);
        Route::get('/alerts', [MedicationController::class, 'alerts']);
        Route::get('/today-intakes', [MedicationIntakeController::class, 'todayIntakes']);
        Route::patch('/intake-logs/{logId}/quick-take', [MedicationIntakeController::class, 'quickTake']);
        Route::patch('/intake-logs/{logId}/snooze', [MedicationIntakeController::class, 'snooze']);
        Route::get('/{id}', [MedicationController::class, 'show']);
        Route::put('/{id}', [MedicationController::class, 'update']);
        Route::patch('/{id}/status', [MedicationController::class, 'changeStatus']);
        Route::delete('/{id}', [MedicationController::class, 'destroy']);
        Route::post('/{id}/renew', [MedicationController::class, 'renew']);
        Route::get('/{id}/renewals', [MedicationController::class, 'renewals']);
        Route::get('/{id}/schedules', [MedicationScheduleController::class, 'index']);
        Route::post('/{id}/schedules', [MedicationScheduleController::class, 'store']);
        Route::delete('/{id}/schedules/{scheduleId}', [MedicationScheduleController::class, 'destroy']);
        Route::get('/{id}/intake-logs', [MedicationIntakeController::class, 'index']);
        Route::post('/{id}/intake-logs', [MedicationIntakeController::class, 'store']);
        Route::patch('/{id}/intake-logs/{logId}', [MedicationIntakeController::class, 'update']);
        Route::get('/{id}/adherence', [MedicationIntakeController::class, 'adherence']);
    });

    // ── Push subscriptions ──────────────────────────────────────────────────
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    // ── Exámenes ────────────────────────────────────────────────────────────
    Route::prefix('exams')->group(function () {
        Route::get('/', [ExamController::class, 'index']);
        Route::post('/', [ExamController::class, 'store']);
        Route::get('/{id}', [ExamController::class, 'show']);
        Route::put('/{id}', [ExamController::class, 'update']);
        Route::patch('/{id}/status', [ExamController::class, 'changeStatus']);
        Route::delete('/{id}', [ExamController::class, 'destroy']);
    });

    // ── Remisiones ──────────────────────────────────────────────────────────
    Route::prefix('referrals')->group(function () {
        Route::get('/', [ReferralController::class, 'index']);
        Route::post('/', [ReferralController::class, 'store']);
        Route::get('/{id}', [ReferralController::class, 'show']);
        Route::put('/{id}', [ReferralController::class, 'update']);
        Route::patch('/{id}/status', [ReferralController::class, 'changeStatus']);
        Route::delete('/{id}', [ReferralController::class, 'destroy']);
    });

    // ── Incapacidades ───────────────────────────────────────────────────────
    Route::prefix('medical-leaves')->group(function () {
        Route::get('/', [MedicalLeaveController::class, 'index']);
        Route::post('/', [MedicalLeaveController::class, 'store']);
        Route::put('/{id}', [MedicalLeaveController::class, 'update']);
        Route::delete('/{id}', [MedicalLeaveController::class, 'destroy']);
    });

    // ── Vacunas ─────────────────────────────────────────────────────────────
    Route::prefix('vaccinations')->group(function () {
        Route::get('/', [VaccinationController::class, 'index']);
        Route::post('/', [VaccinationController::class, 'store']);
        Route::put('/{id}', [VaccinationController::class, 'update']);
        Route::delete('/{id}', [VaccinationController::class, 'destroy']);
    });

    // ── Signos vitales ──────────────────────────────────────────────────────
    Route::prefix('vital-signs')->group(function () {
        Route::get('/', [VitalSignController::class, 'index']);
        Route::post('/', [VitalSignController::class, 'store']);
        Route::put('/{id}', [VitalSignController::class, 'update']);
        Route::delete('/{id}', [VitalSignController::class, 'destroy']);
    });

    // ── Médicos ─────────────────────────────────────────────────────────────
    Route::apiResource('doctors', DoctorController::class);

    // ── Alergias ────────────────────────────────────────────────────────────
    Route::prefix('allergies')->group(function () {
        Route::get('/', [AllergyController::class, 'index']);
        Route::post('/', [AllergyController::class, 'store']);
        Route::put('/{id}', [AllergyController::class, 'update']);
        Route::delete('/{id}', [AllergyController::class, 'destroy']);
    });

    // ── Condiciones crónicas ────────────────────────────────────────────────
    Route::prefix('chronic-conditions')->group(function () {
        Route::get('/', [ChronicConditionController::class, 'index']);
        Route::post('/', [ChronicConditionController::class, 'store']);
        Route::put('/{id}', [ChronicConditionController::class, 'update']);
        Route::delete('/{id}', [ChronicConditionController::class, 'destroy']);
    });

    // ── Documentos médicos — SIEMPRE via controlador autenticado ───────────
    Route::prefix('medical-documents')->group(function () {
        Route::get('/', [MedicalDocumentController::class, 'index']);
        Route::post('/', [MedicalDocumentController::class, 'store']);
        Route::get('/{id}', [MedicalDocumentController::class, 'show']); // ← verifica hogar
        Route::delete('/{id}', [MedicalDocumentController::class, 'destroy']);
    });

    // ── Reportes ────────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        Route::post('/generate', [ReportController::class, 'generate']);
        Route::get('/history', [ReportController::class, 'history']);
        Route::get('/{id}/download', [ReportController::class, 'download']);
    });

    // ── Notificaciones ──────────────────────────────────────────────────────
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
