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

        // v2: compareAllStrategies без N HTTP к трафику и без дублирующих SQL (см. PanelRepository).
        $comparison = Cache::remember('panel_rotation_settings_comparison_v2', 900, function () use ($panelRepository) {
            return $panelRepository->compareAllStrategies();
        });

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
            Cache::forget('panel_rotation_settings_comparison_v2');
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
