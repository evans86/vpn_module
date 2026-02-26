<?php

namespace App\Queue;

use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;

/**
 * Воркер очереди без использования pcntl_signal / pcntl_alarm.
 * Используется на хостингах, где pcntl отключён (disable_functions).
 * Регистрация в AppServiceProvider.
 */
class WorkerNoPcntl extends Worker
{
    /**
     * Не регистрировать обработчики сигналов — pcntl может быть отключён.
     */
    protected function listenForSignals(): void
    {
        // no-op
    }

    /**
     * Не ставить alarm по таймауту — использует pcntl_alarm/pcntl_signal.
     */
    protected function registerTimeoutHandler($job, WorkerOptions $options): void
    {
        // no-op (таймаут джоба не будет убивать процесс по SIGALRM)
    }

    /**
     * Не сбрасывать alarm.
     */
    protected function resetTimeoutHandler(): void
    {
        // no-op
    }

    /**
     * Сообщаем, что async-сигналы не поддерживаются — daemon не будет их регистрировать.
     */
    protected function supportsAsyncSignals(): bool
    {
        return false;
    }
}
