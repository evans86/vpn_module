<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use Illuminate\View\View;

/**
 * Новая страница распределения (scope в БД). Старая — panel-settings.
 */
class PanelDistributionController extends Controller
{
    public function index(): View
    {
        $panels = Panel::query()
            ->where('panel', Panel::MARZBAN)
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('has_error', false)
            ->where('excluded_from_rotation', false)
            ->whereHas('server', function ($q) {
                $q->where('tariff_tier', (string) config('panel.activation_tariff_tier', 'full'));
            })
            ->with(['server.location'])
            ->orderByDesc('selection_scope_score')
            ->orderBy('id')
            ->limit(500)
            ->get();

        return view('module.panel-distribution.index', [
            'panels' => $panels,
            'v2Enabled' => (bool) config('panel.selection_v2_enabled', false),
            'v2CacheTtl' => (int) config('panel.selection_v2_cache_ttl', 0),
            'tariffTier' => (string) config('panel.activation_tariff_tier', 'full'),
        ]);
    }
}
