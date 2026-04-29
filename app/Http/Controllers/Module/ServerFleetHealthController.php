<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Server\Server;
use App\Services\Server\ServerFleetProbeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerFleetHealthController extends Controller
{
    public function __construct(
        private ServerFleetProbeService $fleetProbeService
    ) {}

    /**
     * Страница сводной проверки VPS в статусе «Настроен».
     */
    public function index(): View
    {
        $configuredCount = Server::query()
            ->where('server_status', Server::SERVER_CONFIGURED)
            ->count();

        $title = 'Проверка серверов (массовая)';
        $pageTitle = 'Проверка серверов';

        return view('module.server.health-report', [
            'configuredCount' => $configuredCount,
            'title' => $title,
            'pageTitle' => $pageTitle,
        ]);
    }

    /**
     * Запуск проверки (JSON для страницы).
     */
    public function run(Request $request): JsonResponse
    {
        $includeTestSpeed = $request->boolean('include_test_speed');

        $servers = Server::query()
            ->where('server_status', Server::SERVER_CONFIGURED)
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->orderBy('name')
            ->get();

        $payload = $this->fleetProbeService->probe($servers, $includeTestSpeed);

        return response()->json([
            'success' => true,
            'data' => $payload,
            'included_count' => $servers->count(),
        ]);
    }
}
