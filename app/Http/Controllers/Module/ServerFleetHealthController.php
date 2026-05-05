<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Server\Server;
use App\Services\Server\FleetProbeTargetResolver;
use App\Services\Server\HostVpnWebClassifier;
use App\Services\Server\ServerFleetProbeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
     *
     * Пакетный режим: {@see $request} fleet_global_only, after_id, per_batch — чтобы ответ укладывался в лимит Cloudflare.
     */
    public function run(Request $request): JsonResponse
    {
        $includeTestSpeed = $request->boolean('include_test_speed');
        $afterId = max(0, (int) $request->input('after_id', 0));
        $perBatch = min(5, max(1, (int) $request->input('per_batch', 1)));
        if ($includeTestSpeed) {
            $perBatch = 1;
        }

        if ($includeTestSpeed && function_exists('set_time_limit')) {
            /** @see ServerFleetProbeService::probeTestSpeed время полного /test-speed на каждый сервер может быть многих минут */
            set_time_limit(0);
        }

        $base = $this->fleetConfiguredServersBaseQuery();
        $matchedTotal = (clone $base)->count();

        if ($request->boolean('fleet_global_only')) {
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }
            $t0 = microtime(true);
            $globalProbes = $this->fleetProbeService->gatherGlobalProbesFromAppHost();

            return response()->json([
                'success' => true,
                'fleet_phase' => 'global',
                'included_count' => $matchedTotal,
                'data' => [
                    'global_probes' => $globalProbes,
                    'global_probes_text' => $this->fleetProbeService->globalProbesToText($globalProbes),
                    'report_started_at' => now()->format('Y-m-d H:i:s'),
                    'elapsed_ms' => round((microtime(true) - $t0) * 1000),
                ],
            ]);
        }

        $slice = (clone $base)->where('id', '>', $afterId)->limit($perBatch)->get();

        $emptySummary = [
            'total' => 0,
            'http_ok' => 0,
            'https_ok' => 0,
            'stub_ok_db' => 0,
            'lure_http_ok' => 0,
            'test_speed_ok' => 0,
            'test_speed_fail' => 0,
            'test_speed_skipped' => 0,
        ];

        if ($slice->isEmpty()) {
            return response()->json([
                'success' => true,
                'fleet_phase' => 'servers',
                'message' => $afterId === 0
                    ? 'Нет серверов со статусом «Настроен» с непустым IP.'
                    : 'Узлов больше нет.',
                'included_count' => $matchedTotal,
                'data' => [
                    'summary' => $emptySummary,
                    'rows' => [],
                    'elapsed_ms' => 0,
                    'global_probes' => null,
                    'global_probes_text' => null,
                    'servers_text_chunk' => '',
                ],
                'batch' => [
                    'after_id' => $afterId,
                    'next_after_id' => $afterId,
                    'has_more' => false,
                    'per_batch' => $perBatch,
                    'matched_total' => $matchedTotal,
                ],
            ]);
        }

        $chunk = $this->fleetProbeService->probeServerChunk($slice, $includeTestSpeed);
        $serversTextChunk = $this->fleetProbeService->textReportServersSection($chunk['rows'], $includeTestSpeed);
        $nextAfterId = (int) $slice->max('id');
        $hasMore = (clone $base)->where('id', '>', $nextAfterId)->exists();

        return response()->json([
            'success' => true,
            'fleet_phase' => 'servers',
            'included_count' => $matchedTotal,
            'data' => [
                'summary' => $chunk['summary'],
                'rows' => $chunk['rows'],
                'elapsed_ms' => $chunk['elapsed_ms'],
                'global_probes' => null,
                'global_probes_text' => null,
                'servers_text_chunk' => $serversTextChunk,
            ],
            'batch' => [
                'after_id' => $afterId,
                'next_after_id' => $nextAfterId,
                'has_more' => $hasMore,
                'per_batch' => $perBatch,
                'matched_total' => $matchedTotal,
            ],
        ]);
    }

    /**
     * Серверы «Настроен» для сводной проверки флота.
     *
     * @return Builder<Server>
     */
    private function fleetConfiguredServersBaseQuery(): Builder
    {
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

        return Server::query()
            ->select($select)
            ->where('server_status', Server::SERVER_CONFIGURED)
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->orderBy('id');
    }
}
