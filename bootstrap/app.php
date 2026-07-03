<?php

use App\Jobs\CheckHealthAlertsJob;
use App\Jobs\SendMedicationReminderJob;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Revisa citas, medicamentos, exámenes, remisiones y vacunas
        // vencidas o próximas a vencer, y genera notificaciones. 7:00 AM.
        $schedule->job(new CheckHealthAlertsJob)->dailyAt('07:00');

        // Envía recordatorios push de tomas de medicamentos próximas.
        $schedule->job(new SendMedicationReminderJob)->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware de Sanctum para SPA stateful
        $middleware->statefulApi();

        // Nota: el grupo "api" ya aplica throttle:api por defecto (usa el
        // rate limiter "api" definido abajo, respaldado por CACHE_STORE).
        // No usar throttleWithRedis() — el stack del proyecto no usa Redis.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Siempre responder JSON en rutas /api/*
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->booting(function () {
        // Configurar rate limiters de la API
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(300)->by($request->user()->id)
                : Limit::perMinute(60)->by($request->ip());
        });
    })
    ->create();
