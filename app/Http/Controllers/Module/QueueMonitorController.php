<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Контроль очереди заданий: перевыпуск ключей, рассылки, миграции и др.
 * Один раздел для мониторинга и управления воркером.
 */
class QueueMonitorController extends Controller
{
    public function index(): View
    {
        $connection = config('queue.default');
        $pending = 0;
        $failed = 0;
        $failedJobs = [];

        $workerLastActivityAt = null;
        $workerStatus = null; // 'active' | 'idle' | 'possibly_down' | 'unknown'

        if ($connection === 'database') {
            if (Schema::hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->count();
                $failedJobs = DB::table('failed_jobs')
                    ->orderByDesc('failed_at')
                    ->limit(50)
                    ->get(['id', 'uuid', 'queue', 'failed_at', 'exception']);
            }
            $ts = Cache::get('queue_worker_last_activity_at');
            if ($ts) {
                $workerLastActivityAt = \Carbon\Carbon::createFromTimestamp($ts);
                $minutesAgo = (int) $workerLastActivityAt->diffInMinutes(now());
                if ($minutesAgo <= 5) {
                    $workerStatus = 'active';
                } elseif ($minutesAgo <= 60) {
                    $workerStatus = 'idle';
                } elseif ($pending > 0) {
                    $workerStatus = 'possibly_down';
                } else {
                    $workerStatus = 'idle';
                }
            } else {
                $workerStatus = $pending > 0 ? 'possibly_down' : 'unknown';
            }
        }

        return view('module.queue.index', [
            'queueConnection' => $connection,
            'queuePending' => $pending,
            'queueFailed' => $failed,
            'failedJobs' => $failedJobs,
            'workerLastActivityAt' => $workerLastActivityAt,
            'workerStatus' => $workerStatus,
        ]);
    }

    /**
     * Повторить одно провалившееся задание.
     */
    public function retry(Request $request): RedirectResponse
    {
        $uuid = $request->input('uuid');
        if (!$uuid) {
            return redirect()->route('admin.module.queue.index')->with('error', 'Не указан UUID задания.');
        }
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        return redirect()->route('admin.module.queue.index')->with('success', 'Задание поставлено в очередь на повтор.');
    }

    /**
     * Повторить все провалившиеся задания.
     */
    public function retryAll(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        return redirect()->route('admin.module.queue.index')->with('success', 'Все провалившиеся задания поставлены в очередь на повтор.');
    }

    /**
     * Очистить список провалившихся (без повтора).
     */
    public function flush(): RedirectResponse
    {
        Artisan::call('queue:flush');
        return redirect()->route('admin.module.queue.index')->with('success', 'Список провалившихся заданий очищен.');
    }
}
