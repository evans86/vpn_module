<?php

namespace App\Console\Commands;

use App\Repositories\Panel\PanelRepository;
use Illuminate\Console\Command;

class PanelSelectionTestCommand extends Command
{
    protected $signature = 'panel:selection-test {iterations=5}';
    protected $description = 'Тестирование новой системы распределения';

    public function handle(PanelRepository $panelRepository)
    {
        $iterations = (int)$this->argument('iterations');
        $this->info("🧪 Тестирование новой системы на {$iterations} итераций...");

        $distribution = [];

        for ($i = 1; $i <= $iterations; $i++) {
            // Очищаем кэш перед каждой итерацией для чистого теста
            app('cache')->forget('optimized_marzban_panel');

            $panel = $panelRepository->getOptimizedMarzbanPanel();

            if ($panel) {
                $panelId = $panel->id;
                $distribution[$panelId] = ($distribution[$panelId] ?? 0) + 1;

                $this->line("Итерация {$i}: Выбрана панель ID {$panelId}");
            } else {
                $this->error("Итерация {$i}: Панель не найдена");
            }
        }

        $this->line("\n📈 Результаты распределения:");
        foreach ($distribution as $panelId => $count) {
            $percentage = ($count / $iterations) * 100;
            $this->line("Панель ID {$panelId}: {$count} раз (" . number_format($percentage, 1) . "%)");
        }
    }
}
