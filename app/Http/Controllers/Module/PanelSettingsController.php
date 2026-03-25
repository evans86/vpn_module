<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Repositories\Panel\PanelRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PanelSettingsController extends Controller
{
    /**
     * Страница настроек ротации панелей (интеллектуальный выбор без переключения стратегий).
     */
    public function index()
    {
        $panelRepository = app(PanelRepository::class);

        // Только чтение кэша: compareAllStrategies() на больших БД занимает минуты и рвёт таймаут HTTP.
        // Прогрев: `php artisan panel:warm-rotation-settings` и cron (panel.rotation_settings_warm_*).
        $cacheKey = (string) config('panel.rotation_settings_cache_key', 'panel_rotation_settings_comparison_v2');
        $comparison = Cache::get($cacheKey);
        if ($comparison === null) {
            $comparison = [
                'error' => 'Данные для таблицы ещё не собраны или кэш истёк. На сервере выполните один раз: php artisan panel:warm-rotation-settings — затем обновите страницу. Для автообновления включите cron (команда в расписании Laravel, см. PANEL_ROTATION_SETTINGS_WARM_* в .env).',
            ];
        }

        $panelsWithErrors = $panelRepository->getPanelsWithErrors();

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
                if (! isset($errorHistory[$id])) {
                    $errorHistory[$id] = collect();
                }
            }
        }

        $excludedPanels = \App\Models\Panel\Panel::where('excluded_from_rotation', true)
            ->where('panel_status', \App\Models\Panel\Panel::PANEL_CONFIGURED)
            ->where('has_error', false)
            ->with('server')
            ->get();

        return view('module.panel-settings.index', compact('comparison', 'panelsWithErrors', 'errorHistory', 'excludedPanels'));
    }

    /**
     * Снять пометку об ошибке с панели
     */
    public function clearPanelError(Request $request, PanelRepository $panelRepository): RedirectResponse
    {
        $validated = $request->validate([
            'panel_id' => 'required|integer|exists:panel,id',
        ]);

        try {
            $panelRepository->clearPanelError($validated['panel_id'], 'manual', 'Проблема решена администратором');
            $panelRepository->forgetRotationSelectionCache();
            Cache::forget((string) config('panel.rotation_settings_cache_key', 'panel_rotation_settings_comparison_v2'));
            Cache::forget('panel_intelligent_rotation_comparison');

            Log::info('Panel error cleared by admin', [
                'panel_id' => $validated['panel_id'],
                'user_id' => auth()->id(),
                'source' => 'panel',
            ]);

            return redirect()->route('admin.module.panel-settings.index')
                ->with('success', 'Ошибка с панели снята. Панель возвращена в ротацию.');

        } catch (\Exception $e) {
            Log::error('Failed to clear panel error', [
                'panel_id' => $validated['panel_id'],
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'source' => 'panel',
            ]);

            return redirect()->route('admin.module.panel-settings.index')
                ->with('error', 'Ошибка при снятии пометки: ' . $e->getMessage());
        }
    }
}
