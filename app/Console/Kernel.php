<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\MonitorConnectionLimits::class,
        \App\Console\Commands\ProcessViolationsCommand::class,
        \App\Console\Commands\CleanOldLogsCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Проверяем статус серверов каждые 5 минут
        $schedule->command('servers:check-status')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/server-status.log'));

        // Проверяем истекающие ключи каждый день в 10:00
        $schedule->command('notify:expiring-keys')
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/key-notifications.log'));

        // Проверка нарушений лимитов подключений каждые 10 минут с окном 15 минут
        $schedule->command('vpn:monitor-fixed --threshold=3 --window=15')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/vpn-monitor.log'));

        // Автоматическая обработка нарушений (отправка уведомлений, перевыпуск ключей, авто-решение) каждый час
        $schedule->command('violations:process')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/violation-process.log'));

        // Очистка старых логов каждый день в 3:00
        $schedule->command('logs:clean --days=30')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/logs-cleanup.log'));

        // Проверка панелей с ошибками каждые 15 минут
        $schedule->command('panels:check-errors')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/panel-error-check.log'));

        // Прогрев кэша интеллектуального выбора панели (активация/покупка попадают в горячий кэш)
        if (config('panel.selection_warm_enabled', true)) {
            $warmEvery = (int) config('panel.selection_warm_every_minutes', 1);
            $warmEvery = max(1, min(59, $warmEvery));
            $schedule->command('panel:warm-selection-cache')
                ->cron('*/' . $warmEvery . ' * * * *')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/panel-selection-warm.log'));
        }

        // Страница «Настройки распределения»: тяжёлый compareAllStrategies только здесь, не в HTTP.
        if (config('panel.rotation_settings_warm_enabled', true)) {
            $uiWarmEvery = (int) config('panel.rotation_settings_warm_every_minutes', 5);
            $uiWarmEvery = max(1, min(59, $uiWarmEvery));
            $schedule->command('panel:warm-rotation-settings')
                ->cron('*/' . $uiWarmEvery . ' * * * *')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/panel-rotation-settings-warm.log'));
        }

        // Пересчёт selection_scope_score для панелей (трафик провайдера + CPU Marzban)
        if (config('panel.scope_recalc_enabled', true)) {
            $schedule->command('panel:recalculate-selection-scope')
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/panel-selection-scope.log'));
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
