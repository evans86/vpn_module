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
        $this->line(str_repeat('-', 130));

        $headers = ['ID', 'Адрес', 'Актив.(DB)', 'Актив.(Stats)', 'Всего', 'CPU%', 'Память%', 'Score', 'Выбор'];

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

            $rows[] = [
                $panel['id'],
                substr($panel['address'], 0, 15) . '...',
                $panel['active_users_db'],
                $panel['active_users_stats'],
                $panel['total_users'],
                $cpuUsage,
                $memoryUsage,
                number_format($panel['optimized_score'], 1),
                $selection
            ];
        }

        $this->table($headers, $rows);

        $this->line("\n💡 Объяснение расхождений:");
        $this->line("   - Актив.(DB) - пользователи с активными ключами в нашей БД");
        $this->line("   - Актив.(Stats) - реально активные пользователи из статистики Marzban");
        $this->line("   - Новая система использует Актив.(Stats) для распределения");

        $this->line("\n💡 Для тестирования новой системы используйте:");
        $this->line("   \$panel = \$panelRepository->getOptimizedMarzbanPanel();");
    }
}
