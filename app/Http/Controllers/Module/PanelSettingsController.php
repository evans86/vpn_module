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
     * @deprecated Используйте «Панели и распределение» (panel-distribution.index).
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.module.panel-distribution.index');
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

            return redirect()->route('admin.module.panel-distribution.index')
                ->with('success', 'Ошибка с панели снята. Панель возвращена в ротацию.');

        } catch (\Exception $e) {
            Log::error('Failed to clear panel error', [
                'panel_id' => $validated['panel_id'],
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'source' => 'panel',
            ]);

            return redirect()->route('admin.module.panel-distribution.index')
                ->with('error', 'Ошибка при снятии пометки: ' . $e->getMessage());
        }
    }
}
