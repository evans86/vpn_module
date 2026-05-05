<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Server\Server;
use App\Services\Server\FleetProbeTargetResolver;
use App\Services\Server\HostVpnWebClassifier;
use App\Services\Server\ServerFleetProbeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ServerFleetHealthController extends Controller
{
    /**
     * @var ServerFleetProbeService
     */
    private ServerFleetProbeService $fleetProbeService;

    public function __construct(ServerFleetProbeService $fleetProbeService)
    {
        $this->fleetProbeService = $fleetProbeService;
    }

    /**
     * Страница сводной проверки VPS в статусе «Настроен».
     *
     * @see FleetProbeTargetResolver цели ICMP/HTTPS (БД панелей + .env + APP_*)
     */
    public function index(): View
    {
        $resolver = app(FleetProbeTargetResolver::class);
        $panelHosts = $resolver->mergedPanelHosts();
        $ourHosts = $resolver->mergedOurDomainHosts();

        return view('module.server.health-report', [
            'title' => 'Проверка сети и серверов',
            'pageTitle' => 'Проверка сети и серверов',
            'fleetReportMeta' => [
                'panel_targets_count' => count($panelHosts),
                'our_domains_targets_count' => count($ourHosts),
            ],
            'classifyHostUrl' => route('admin.module.server-fleet.classify-host'),
        ]);
    }

    /**
     * Эвристика «веб / VPN» по хосту или IPv4 (без внешних скриптов).
     */
    public function classifyHost(Request $request, HostVpnWebClassifier $classifier): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:512'],
        ]);

        $result = $classifier->classify($data['host']);
        $ok = (bool) ($result['ok'] ?? false);

        return response()->json([
            'success' => $ok,
            'result' => $result,
            'message' => $ok ? null : ($result['error'] ?? 'Не удалось выполнить классификацию'),
        ]);
    }

    /**
     * Запуск проверки (JSON для страницы).
     */
    public function run(Request $request): JsonResponse
    {
        $includeTestSpeed = $request->boolean('include_test_speed');

        $tbl = (new Server)->getTable();
        $select = [
            'id',
            'name',
            'ip',
            'server_status',
            'decoy_stub_include_123_rar',
            'decoy_stub_last_applied_at',
            'decoy_stub_last_message',
        ];
        if (Schema::hasColumn($tbl, 'decoy_stub_test_speed_token')) {
            $select[] = 'decoy_stub_test_speed_token';
        }

        $servers = Server::query()
            ->select($select)
            ->where('server_status', Server::SERVER_CONFIGURED)
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->orderBy('name')
            ->get();

        if ($includeTestSpeed && function_exists('set_time_limit')) {
            /** @see ServerFleetProbeService::probeTestSpeed время полного /test-speed на каждый сервер может быть многих минут */
            set_time_limit(0);
        }

        $payload = $this->fleetProbeService->probe($servers, $includeTestSpeed);

        return response()->json([
            'success' => true,
            'data' => $payload,
            'included_count' => $servers->count(),
        ]);
    }
}
