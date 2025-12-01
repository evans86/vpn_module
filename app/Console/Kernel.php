<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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

        // Автоматическая обработка нарушений (отправка уведомлений, авто-решение) каждый час
        // Примечание: Проверка нарушений уже настроена в cron вручную (каждые 10 минут)
        $schedule->command('violations:process --notify-new')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/violation-process.log'));

        // Очистка старых логов каждый день в 3:00
        $schedule->command('logs:clean --days=30')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/logs-cleanup.log'));
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
