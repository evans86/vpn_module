<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Logging\DatabaseLogger;
use App\Models\Panel\Panel;
use App\Services\Panel\PanelStrategy;
use GuzzleHttp\Exception\GuzzleException;

class ServerMonitoringController extends Controller
{
    private DatabaseLogger $logger;

    public function __construct(
        DatabaseLogger $logger
    )
    {
        $this->logger = $logger;
    }

    /**
     * @throws GuzzleException
     */
    public function index()
    {
        /**
         * @var Panel $panel
         */
        $panel = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED)->first();
        $strategy = new PanelStrategy($panel->panel);
        $panel_stats = $strategy->getServerStats(23);



        var_dump($panel_stats);

        return view('module.server-monitoring.index');
    }
}
