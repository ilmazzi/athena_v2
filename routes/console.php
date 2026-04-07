<?php

use App\Jobs\ControllaScadenzeContiDeposito;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// ==========================================
// SCHEDULER CONFIGURATION
// ==========================================

// Controllo scadenze conti deposito - ogni giorno alle 09:00
Schedule::job(new ControllaScadenzeContiDeposito())
    ->dailyAt('09:00')
    ->name('controlla-scadenze-depositi')
    ->description('Controlla scadenze conti deposito e invia notifiche')
    ->onOneServer()
    ->withoutOverlapping();

// Controllo aggiuntivo - ogni lunedì alle 08:00 per depositi scaduti
Schedule::job(new ControllaScadenzeContiDeposito())
    ->weeklyOn(1, '08:00')
    ->name('controllo-settimanale-depositi-scaduti')
    ->description('Controllo settimanale depositi scaduti')
    ->onOneServer()
    ->withoutOverlapping();

// Audit integrità magazzino - ogni ora (read-only) su connessione dedicata da env
Schedule::command('audit:integrita-magazzino --use-env-audit-db --fail-on-critical')
    ->hourly()
    ->name('audit-integrita-magazzino-orario')
    ->description('Audit orario coerenza articoli/giacenze/movimenti/deposito')
    ->onOneServer()
    ->withoutOverlapping();

// ==========================================
// ARTISAN COMMANDS
// ==========================================

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
