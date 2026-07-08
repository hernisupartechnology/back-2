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
        // vencidas o próximas a vencer, y genera notificaciones. 7:00 AM
        // hora Colombia — app.timezone se mantiene en UTC (ver config/app.php),
        // así que esta tarea necesita su propio timezone explícito o correría
        // a las 7:00 UTC (2:00 a.m. Colombia).
        $schedule->job(new CheckHealthAlertsJob)->dailyAt('07:00')->timezone('America/Bogota');

        // Envía recordatorios push de tomas de medicamentos próximas.
        $schedule->job(new SendMedicationReminderJob)->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // NO usar statefulApi(): esta app se autentica 100% con tokens Bearer
        // de Sanctum (el frontend guarda el token y lo manda en el header
        // Authorization — ver front/src/lib/axios.ts). statefulApi() activa
        // el modo SPA por cookies + sesión para peticiones cuyo Origin
        // coincide con SANCTUM_STATEFUL_DOMAINS, lo que fuerza el stack de
        // CSRF de la sesión web — y como el frontend nunca pide el cookie de
        // /sanctum/csrf-cookie ni envía withCredentials, toda petición
        // POST/PUT/PATCH/DELETE termina en "CSRF token mismatch" (419).

        // Esta app no tiene ruta web "login" — sin esto, un guest sin
        // Accept: application/json recibe un 500 (RouteNotFoundException al
        // intentar redirigir a route('login')) en vez de un 401 limpio.
        $middleware->redirectGuestsTo(fn () => null);

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
