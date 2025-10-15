<?php

namespace App\Console\Commands;

use App\Repositories\Panel\PanelRepository;
use Illuminate\Console\Command;

class PanelSelectionDebugCommand extends Command
{
    protected $signature = 'panel:selection-debug';
    protected $description = 'Сравнение старой и новой системы выбора панелей';

    public function handle(PanelRepository $panelRepository)
    {
        $this->info('🔄 Сравнение систем выбора панелей...');

        $comparison = $panelRepository->comparePanelSelection();

        if (isset($comparison['error'])) {
            $this->error($comparison['error']);
            return;
        }

        $this->line("📊 Время проверки: {$comparison['timestamp']}");
        $this->line("🔵 Старая система выбрала: Панель ID {$comparison['old_system_selected']}");
        $this->line("🟢 Новая система выбрала: Панель ID {$comparison['new_system_selected']}");

        $this->line("\n📋 Детальная информация по панелям:");
        $this->line(str_repeat('-', 150));

        $headers = ['ID', 'Адрес', 'Активные', 'Всего', 'Последняя акт.', 'CPU%', 'Память%', 'Общ.Score', 'User', 'Load', 'Time', 'Rand', 'Выбор'];

        $rows = [];
        foreach ($comparison['panels'] as $panel) {
            $cpuUsage = $panel['server_stats']['cpu_usage'] ?? 'N/A';
            $memoryUsed = $panel['server_stats']['mem_used'] ?? 0;
            $memoryTotal = $panel['server_stats']['mem_total'] ?? 1;
            $memoryUsage = $memoryTotal > 0 ? round(($memoryUsed / $memoryTotal) * 100, 1) : 'N/A';

            $selection = '';
            if ($panel['is_old_selected'] && $panel['is_new_selected']) {
                $selection = '🔵🟢 ОБЕ';
            } elseif ($panel['is_old_selected']) {
                $selection = '🔵 СТАРАЯ';
            } elseif ($panel['is_new_selected']) {
                $selection = '🟢 НОВАЯ';
            }

            $lastActivity = $panel['last_activity'] ? $panel['last_activity']->format('H:i') : 'никогда';

            $rows[] = [
                $panel['id'],
                substr($panel['address'], 0, 15) . '...',
                $panel['active_users'],
                $panel['total_users'],
                $lastActivity,
                $cpuUsage,
                $memoryUsage,
                number_format($panel['optimized_score'], 1),
                number_format($panel['score_details']['user_score'], 1),
                number_format($panel['score_details']['load_score'], 1),
                number_format($panel['score_details']['time_score'], 1),
                number_format($panel['score_details']['random_score'], 1),
                $selection
            ];
        }

        $this->table($headers, $rows);

        $this->line("\n💡 Веса алгоритма: Пользователи (40%), Нагрузка (40%), Время (15%), Случайность (5%)");
        $this->line("💡 Для тестирования новой системы используйте:");
        $this->line("   \$panel = \$panelRepository->getOptimizedMarzbanPanel();");
    }
}
