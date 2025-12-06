<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PanelSettingsController extends Controller
{
    /**
     * Показать страницу настроек распределения панелей
     */
    public function index()
    {
        $currentStrategy = config('panel.selection_strategy', 'intelligent');
        $strategies = [
            'balanced' => [
                'name' => 'Равномерное распределение',
                'description' => 'Выбирает панель с минимальным количеством пользователей (старая система)',
                'icon' => '⚖️'
            ],
            'traffic_based' => [
                'name' => 'На основе трафика сервера',
                'description' => 'Выбирает панель на сервере с наименьшим процентом использования трафика (новая система)',
                'icon' => '📊'
            ],
            'intelligent' => [
                'name' => 'Интеллектуальная система',
                'description' => 'Комплексный анализ: пользователи, нагрузка CPU/памяти и трафик',
                'icon' => '🧠'
            ]
        ];

        // Получаем статистику и сравнение стратегий
        $panelRepository = app(\App\Repositories\Panel\PanelRepository::class);
        $comparison = $panelRepository->compareAllStrategies();

        return view('module.panel-settings.index', compact('currentStrategy', 'strategies', 'comparison'));
    }

    /**
     * Обновить настройку стратегии распределения
     */
    public function updateStrategy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'strategy' => 'required|string|in:balanced,traffic_based,intelligent'
        ]);

        try {
            $oldValue = config('panel.selection_strategy', 'intelligent');
            $newValue = $validated['strategy'];
            
            // Обновляем конфиг через .env или кэш
            // В продакшене лучше использовать БД или Redis для хранения настроек
            $configPath = config_path('panel.php');
            
            if (file_exists($configPath)) {
                $config = file_get_contents($configPath);
                
                // Заменяем значение в конфиге
                $config = preg_replace(
                    "/'selection_strategy' => env\('PANEL_SELECTION_STRATEGY', '[^']+'\)/",
                    "'selection_strategy' => env('PANEL_SELECTION_STRATEGY', '{$newValue}')",
                    $config
                );
                
                file_put_contents($configPath, $config);
            }
            
            // Очищаем кэш конфига и обновляем текущее значение
            Cache::forget('config.panel');
            Cache::forget('config.panel.selection_strategy');
            config(['panel.selection_strategy' => $newValue]);

            // Очищаем кэш выбора панелей
            Cache::forget('optimized_marzban_panel_balanced');
            Cache::forget('optimized_marzban_panel_traffic_based');
            Cache::forget('optimized_marzban_panel_intelligent');

            Log::info('Panel selection strategy updated', [
                'old_strategy' => $oldValue ?? 'intelligent',
                'new_strategy' => $newValue,
                'user_id' => auth()->id()
            ]);

            return redirect()->route('admin.module.panel-settings.index')
                ->with('success', 'Стратегия распределения успешно обновлена');

        } catch (\Exception $e) {
            Log::error('Failed to update panel selection strategy', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->route('admin.module.panel-settings.index')
                ->with('error', 'Ошибка при обновлении настройки: ' . $e->getMessage());
        }
    }
}

