<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!filter_var(env('SLOW_QUERY_LOG_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $slowQueryThresholdMs = (int) env('SLOW_QUERY_THRESHOLD_MS', 500);
        $slowRequestThresholdMs = (int) env('SLOW_REQUEST_THRESHOLD_MS', 1500);

        DB::listen(function (QueryExecuted $query) use ($slowQueryThresholdMs) {
            if ($query->time < $slowQueryThresholdMs) {
                return;
            }

            $request = request();

            Log::warning('Slow query detected', [
                'time_ms' => $query->time,
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'url' => $request?->fullUrl(),
                'path' => $request?->path(),
                'method' => $request?->method(),
                'route' => $request?->route()?->getName(),
                'is_livewire' => $request?->is('livewire/*') ?? false,
                'component' => $request?->input('components.0.snapshot.memo.name'),
            ]);
        });

        app()->terminating(function () use ($slowRequestThresholdMs) {
            if (!app()->bound('request')) {
                return;
            }

            $request = request();
            $startedAt = defined('LARAVEL_START') ? LARAVEL_START : null;
            if (!$startedAt) {
                return;
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            if ($durationMs < $slowRequestThresholdMs) {
                return;
            }

            Log::warning('Slow request detected', [
                'duration_ms' => $durationMs,
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'is_livewire' => $request->is('livewire/*'),
                'component' => $request->input('components.0.snapshot.memo.name'),
            ]);
        });
    }
}
