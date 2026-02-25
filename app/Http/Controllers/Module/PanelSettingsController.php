<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Repositories\Panel\PanelRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PanelSettingsController extends Controller
{
    /**
     * Показать страницу настроек распределения панелей
     */
    public function index()
    {
        // Очищаем кэш конфига перед чтением, чтобы получить актуальное значение
        Cache::forget('config.panel.selection_strategy');
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

        // Получаем статистику и сравнение стратегий с кэшированием
        $panelRepository = app(\App\Repositories\Panel\PanelRepository::class);

        // Кэшируем сравнение на 5 минут, чтобы не делать запросы к API при каждой загрузке
        $comparison = Cache::remember('panel_strategies_comparison', 300, function () use ($panelRepository) {
            return $panelRepository->compareAllStrategies();
        });

        // Получаем панели с ошибками
        $panelsWithErrors = $panelRepository->getPanelsWithErrors();

        // История ошибок — один запрос вместо N
        $errorHistory = [];
        if ($panelsWithErrors->isNotEmpty()) {
            $panelIds = $panelsWithErrors->pluck('id')->toArray();
            $histories = \App\Models\Panel\PanelErrorHistory::whereIn('panel_id', $panelIds)
                ->orderBy('error_occurred_at', 'desc')
                ->get();
            foreach ($histories->groupBy('panel_id') as $panelId => $items) {
                $errorHistory[$panelId] = $items->sortByDesc('error_occurred_at')->take(10)->values();
            }
            foreach ($panelIds as $id) {
                if (!isset($errorHistory[$id])) {
                    $errorHistory[$id] = collect();
                }
            }
        }

        // Получаем панели, исключенные из ротации (но без ошибок)
        $excludedPanels = \App\Models\Panel\Panel::where('excluded_from_rotation', true)
            ->where('panel_status', \App\Models\Panel\Panel::PANEL_CONFIGURED)
            ->where('has_error', false) // Только панели без ошибок
            ->with('server')
            ->get();

        return view('module.panel-settings.index', compact('currentStrategy', 'strategies', 'comparison', 'panelsWithErrors', 'errorHistory', 'excludedPanels'));
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

                // Заменяем значение в конфиге (более гибкий паттерн)
                $config = preg_replace(
                    "/('selection_strategy'\s*=>\s*env\('PANEL_SELECTION_STRATEGY',\s*')[^']+(')/",
                    "$1{$newValue}$2",
                    $config
                );

                // Если паттерн не сработал, пробуем другой вариант
                if (!preg_match("/'selection_strategy' => env\('PANEL_SELECTION_STRATEGY', '{$newValue}'\)/", $config)) {
                    $config = preg_replace(
                        "/('selection_strategy'\s*=>\s*env\('PANEL_SELECTION_STRATEGY',\s*')[^)]+\)/",
                        "$1'{$newValue}')",
                        $config
                    );
                }

                file_put_contents($configPath, $config);
            }

            // Очищаем все кэши конфига
            Cache::forget('config.panel');
            Cache::forget('config.panel.selection_strategy');

            // Очищаем кэш конфига Laravel через Artisan
            try {
                Artisan::call('config:clear');
            } catch (\Exception $e) {
                Log::warning('Failed to clear config cache via Artisan', ['error' => $e->getMessage()]);
            }

            // Обновляем текущее значение в runtime
            config(['panel.selection_strategy' => $newValue]);

            // Очищаем кэш выбора панелей для всех стратегий
            Cache::forget('optimized_marzban_panel_balanced');
            Cache::forget('optimized_marzban_panel_traffic_based');
            Cache::forget('optimized_marzban_panel_intelligent');

            // Очищаем кэш сравнения стратегий
            Cache::forget('panel_strategies_comparison');

            Log::info('Panel selection strategy updated', [
                'old_strategy' => $oldValue ?? 'intelligent',
                'new_strategy' => $newValue,
                'user_id' => auth()->id(),
                'source' => 'panel'
            ]);

            return redirect()->route('admin.module.panel-settings.index')
                ->with('success', 'Стратегия распределения успешно обновлена');

        } catch (\Exception $e) {
            Log::error('Failed to update panel selection strategy', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'source' => 'panel'
            ]);

            return redirect()->route('admin.module.panel-settings.index')
                ->with('error', 'Ошибка при обновлении настройки: ' . $e->getMessage());
        }
    }

    /**
     * Снять пометку об ошибке с панели
     */
    public function clearPanelError(Request $request, PanelRepository $panelRepository): RedirectResponse
    {
        $validated = $request->validate([
            'panel_id' => 'required|integer|exists:panel,id'
        ]);

        try {
            $panelRepository->clearPanelError($validated['panel_id'], 'manual', 'Проблема решена администратором');

            Log::info('Panel error cleared by admin', [
                'panel_id' => $validated['panel_id'],
                'user_id' => auth()->id(),
                'source' => 'panel'
            ]);

            return redirect()->route('admin.module.panel-settings.index')
                ->with('success', 'Ошибка с панели снята. Панель возвращена в ротацию.');

        } catch (\Exception $e) {
            Log::error('Failed to clear panel error', [
                'panel_id' => $validated['panel_id'],
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'source' => 'panel'
            ]);

            return redirect()->route('admin.module.panel-settings.index')
                ->with('error', 'Ошибка при снятии пометки: ' . $e->getMessage());
        }
    }
}

