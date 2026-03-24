<?php

namespace App\Repositories\Panel;

use App\Models\Panel\Panel;
use App\Models\Panel\PanelErrorHistory;
use App\Models\Panel\PanelMonthlyStatistics;
use App\Models\Server\Server;
use App\Models\ServerMonitoring\ServerMonitoring;
use App\Models\ServerUser\ServerUser;
use App\Models\KeyActivate\KeyActivate;
use App\Repositories\BaseRepository;
use App\Services\External\TimewebCloudAPI;
use App\Services\External\VdsinaAPI;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PanelRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return Panel::class;
    }

    // ==================== СУЩЕСТВУЮЩИЕ МЕТОДЫ (с изменениями) ====================

    /**
     * Get paginated panels with relations
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithRelations(int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()
            ->with(['server.location'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get all active panels
     * 
     * @param string|null $panelType Тип панели (если null, возвращаются все типы)
     * @return Collection
     */
    public function getAllConfiguredPanels(?string $panelType = null): Collection
    {
        // Получаем все настроенные панели (исключаем панели с ошибками)
        // Оптимизация: добавляем eager loading для сервера
        $query = $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('has_error', false) // Исключаем панели с ошибками
            ->with('server.location')
            ->orderBy('id', 'desc');
        
        // Если указан тип панели, фильтруем по нему
        if ($panelType !== null) {
            $query->where('panel', $panelType);
        }

        // Защита от исчерпания памяти: не загружать больше 2000 панелей за раз
        $query->limit(2000);

        return $query->get();
    }

    /**
     * Ключ кэша выбора панели (полный цикл: кандидаты + интеллектуальный score).
     */
    private function rotationPanelCacheKey(string $panelType, ?string $provider): string
    {
        if ($provider === null || $provider === '') {
            return 'panel_rotation:' . $panelType;
        }

        return 'panel_rotation:' . $panelType . ':p:' . md5(strtolower(trim($provider)));
    }

    /**
     * Сброс кэша выбора панели (после ошибки панели, смены ротации и т.д.).
     */
    public function forgetRotationSelectionCache(?string $provider = null): void
    {
        $panelType = Panel::MARZBAN;
        Cache::forget($this->rotationPanelCacheKey($panelType, null));
        if ($provider !== null && $provider !== '') {
            Cache::forget($this->rotationPanelCacheKey($panelType, $provider));

            return;
        }
        foreach (config('panel.multi_provider_slots', []) as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                Cache::forget($this->rotationPanelCacheKey($panelType, $p));
            }
        }
    }

    /**
     * Базовый запрос кандидатов в ротацию (без исключений по локации/IP в PHP).
     */
    private function buildCandidatePanelsQuery(string $panelType, ?string $provider = null)
    {
        $q = $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', $panelType)
            ->where('has_error', false)
            ->where('excluded_from_rotation', false)
            ->whereNotIn('id', function ($query) {
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->with('server.location')
            ->limit(1000);
        if ($provider !== null && $provider !== '') {
            $q->whereHas('server', fn ($sub) => $sub->where('provider', $provider));
        }

        return $q;
    }

    /**
     * Исключения из конфига (локации, IP, ID серверов).
     */
    private function applyExclusionRulesToPanels(Collection $panels): Collection
    {
        $excludedLocations = array_map('intval', array_filter(config('panel.excluded_locations', [])));
        $excludedServerIPs = array_filter(config('panel.excluded_server_ips', []));
        $excludedServerIDs = array_map('intval', array_filter(config('panel.excluded_server_ids', [])));

        if (empty($excludedLocations) && empty($excludedServerIPs) && empty($excludedServerIDs)) {
            return $panels;
        }

        return $panels->filter(function ($panel) use ($excludedLocations, $excludedServerIPs, $excludedServerIDs) {
            if (!empty($excludedLocations) && $panel->server && $panel->server->location_id) {
                if (in_array($panel->server->location_id, $excludedLocations)) {
                    return false;
                }
            }
            if (!empty($excludedServerIPs) && $panel->server && $panel->server->ip) {
                $serverIP = is_numeric($panel->server->ip) ? long2ip($panel->server->ip) : $panel->server->ip;
                if (in_array($serverIP, $excludedServerIPs)) {
                    return false;
                }
            }
            if (!empty($excludedServerIDs) && $panel->server && $panel->server->id) {
                if (in_array($panel->server->id, $excludedServerIDs)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Панели, участвующие в ротации (запрос + фильтры исключений).
     */
    private function getCandidatePanelsForRotation(string $panelType, ?string $provider = null): Collection
    {
        $panels = $this->buildCandidatePanelsQuery($panelType, $provider)->get();

        return $this->applyExclusionRulesToPanels($panels);
    }

    /**
     * Один запрос к БД для кандидатов сразу по нескольким провайдерам (вместо N последовательных get).
     *
     * @param array<int, string> $providers
     * @return array<string, Collection> ключ — строка из конфига (как в multi_provider_slots)
     */
    private function getCandidatePanelsForRotationBatch(string $panelType, array $providers): array
    {
        $providers = array_values(array_unique(array_filter(array_map('trim', $providers), function ($p) {
            return (string) $p !== '';
        })));

        $out = [];
        foreach ($providers as $p) {
            $out[$p] = collect();
        }

        if ($providers === []) {
            return $out;
        }

        $limit = min(3000, 1000 * count($providers));

        $rows = $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', $panelType)
            ->where('has_error', false)
            ->where('excluded_from_rotation', false)
            ->whereNotIn('id', function ($query) {
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->whereHas('server', function ($sub) use ($providers) {
                $sub->whereIn('provider', $providers);
            })
            ->with('server.location')
            ->limit($limit)
            ->get();

        $rows = $this->applyExclusionRulesToPanels($rows);

        foreach ($rows as $panel) {
            $srv = (string) ($panel->server->provider ?? '');
            if ($srv === '') {
                continue;
            }
            $matchedKey = null;
            foreach ($providers as $wanted) {
                if ($srv === $wanted || strcasecmp($srv, $wanted) === 0) {
                    $matchedKey = $wanted;
                    break;
                }
            }
            if ($matchedKey !== null) {
                $out[$matchedKey]->push($panel);
            }
        }

        return $out;
    }

    /**
     * Последняя запись server_monitoring по каждой панели (один проход БД).
     *
     * @return array<int, array{stats: ?array, fresh: bool, created_at: ?\Carbon\Carbon}>
     */
    private function loadLatestMonitoringByPanelIds(array $panelIds): array
    {
        $panelIds = array_values(array_unique(array_map('intval', $panelIds)));
        if ($panelIds === []) {
            return [];
        }

        $maxAgeMinutes = 5;

        $latestIds = DB::table('server_monitoring')
            ->select('panel_id', DB::raw('MAX(id) as max_id'))
            ->whereIn('panel_id', $panelIds)
            ->groupBy('panel_id');

        $rows = DB::table('server_monitoring as sm')
            ->joinSub($latestIds, 't', function ($join) {
                $join->on('sm.panel_id', '=', 't.panel_id')
                    ->on('sm.id', '=', 't.max_id');
            })
            ->select('sm.*')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $pid = (int) $row->panel_id;
            $decoded = json_decode($row->statistics, true);
            $stats = is_array($decoded) ? $decoded : null;
            $createdAt = Carbon::parse($row->created_at);
            $out[$pid] = [
                'stats' => $stats,
                'fresh' => Carbon::now()->diffInMinutes($createdAt) <= $maxAgeMinutes,
                'created_at' => $createdAt,
            ];
        }

        return $out;
    }

    /**
     * Активные пользователи по панелям (один запрос с group by).
     *
     * @return array<int, int>
     */
    private function loadActiveUsersCountByPanelIds(array $panelIds): array
    {
        $panelIds = array_values(array_unique(array_map('intval', $panelIds)));
        if ($panelIds === []) {
            return [];
        }

        $rows = ServerUser::query()
            ->whereIn('panel_id', $panelIds)
            ->whereHas('keyActivateUser.keyActivate', function ($q) {
                $q->where('status', KeyActivate::ACTIVE);
            })
            ->select('panel_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('panel_id')
            ->get();

        $map = array_fill_keys($panelIds, 0);
        foreach ($rows as $row) {
            $map[(int) $row->panel_id] = (int) $row->cnt;
        }

        return $map;
    }

    /**
     * Время создания последнего server_user по панелям.
     *
     * @return array<int, ?Carbon>
     */
    private function loadLastServerUserCreatedAtByPanelIds(array $panelIds): array
    {
        $panelIds = array_values(array_unique(array_map('intval', $panelIds)));
        if ($panelIds === []) {
            return [];
        }

        $rows = ServerUser::query()
            ->whereIn('panel_id', $panelIds)
            ->select('panel_id', DB::raw('MAX(created_at) as last_at'))
            ->groupBy('panel_id')
            ->get();

        $map = array_fill_keys($panelIds, null);
        foreach ($rows as $row) {
            $ts = $row->last_at ?? null;
            $map[(int) $row->panel_id] = $ts ? Carbon::parse($ts) : null;
        }

        return $map;
    }

    /**
     * Всего пользователей панели (для админки сравнения — пакетно).
     *
     * @return array<int, int>
     */
    private function loadTotalUsersCountByPanelIds(array $panelIds): array
    {
        $panelIds = array_values(array_unique(array_map('intval', $panelIds)));
        if ($panelIds === []) {
            return [];
        }

        $rows = DB::table('server_user')
            ->whereIn('panel_id', $panelIds)
            ->select('panel_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('panel_id')
            ->get();

        $map = array_fill_keys($panelIds, 0);
        foreach ($rows as $row) {
            $map[(int) $row->panel_id] = (int) $row->cnt;
        }

        return $map;
    }

    /**
     * Find panel by ID
     * @param int $id
     * @return Panel|null
     */
    public function findById(int $id): ?Panel
    {
        /** @var Panel|null $result */
        $result = $this->query()->find($id);
        return $result;
    }

    /**
     * Get configured servers without panels
     * @return Collection
     */
    public function getConfiguredServersWithoutPanels(): Collection
    {
        return Server::where('server_status', Server::SERVER_CONFIGURED)
            ->whereDoesntHave('panels')
            ->with('location')
            ->get()
            ->mapWithKeys(function ($server) {
                $locationName = $server->location ? " ({$server->location->name})" : '';
                return [$server->id => "{$server->name}{$locationName}"];
            });
    }

    /**
     * Get filtered panels
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getFilteredPanels(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->query()->with('server');

        // Фильтр по серверу (минимум 3 символа)
        if (!empty($filters['server']) && strlen($filters['server']) >= 3) {
            $query->whereHas('server', function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['server']}%")
                    ->orWhere('ip', 'like', "%{$filters['server']}%");
            });
        }

        // Фильтр по адресу панели (минимум 3 символа)
        if (!empty($filters['panel_adress']) && strlen($filters['panel_adress']) >= 3) {
            $query->where('panel_adress', 'like', "%{$filters['panel_adress']}%");
        }

        // Фильтр по статусу
        if (!empty($filters['status'])) {
            $query->where('panel_status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    // ==================== ВЫБОР ПАНЕЛИ (ИНТЕЛЛЕКТУАЛЬНЫЙ) ====================

    /**
     * Оптимальная панель Marzban для ротации.
     * Кэшируется весь цикл: загрузка кандидатов + расчёт score (без «тяжёлого» SELECT на каждый miss только по стратегии).
     *
     * @param bool $useCacheOnly зарезервировано (совместимость вызовов)
     */
    public function getOptimizedMarzbanPanel(?string $panelType = null, bool $useCacheOnly = false): ?Panel
    {
        $panelType = $panelType ?? Panel::MARZBAN;
        $ttl = (int) config('panel.selection_cache_ttl', 15);
        $cacheKey = $this->rotationPanelCacheKey($panelType, null);

        return Cache::remember($cacheKey, max(1, $ttl), function () use ($panelType, $useCacheOnly) {
            $panels = $this->getCandidatePanelsForRotation($panelType, null);
            if ($panels->isEmpty()) {
                Log::warning('PANEL_SELECTION: No available panels after filtering', ['source' => 'panel']);

                return null;
            }

            $panel = $this->selectOptimalPanelIntelligent($panels, $useCacheOnly);
            if ($panel) {
                return $panel;
            }

            Log::warning('PANEL_SELECTION [INTELLIGENT]: No valid score, fallback to lowest panel id', ['source' => 'panel']);

            return $panels->sortBy('id')->first();
        });
    }

    /**
     * То же для конкретного провайдера (мульти-провайдер).
     *
     * @param bool $useCacheOnly зарезервировано
     */
    public function getOptimizedMarzbanPanelForProvider(string $provider, ?string $panelType = null, bool $useCacheOnly = false): ?Panel
    {
        $map = $this->getOptimizedMarzbanPanelsForProviders([$provider], $panelType, $useCacheOnly);

        return $map[$provider] ?? null;
    }

    /**
     * Выбор панели по каждому провайдеру с общим батчем метрик (один проход load* вместо N последовательных).
     *
     * @return array<string, Panel|null> ключ — строка провайдера из конфига
     */
    public function getOptimizedMarzbanPanelsForProviders(array $providers, ?string $panelType = null, bool $useCacheOnly = false): array
    {
        $panelType = $panelType ?? Panel::MARZBAN;
        $ttl = max(1, (int) config('panel.selection_cache_ttl', 15));
        $providers = array_values(array_unique(array_filter(array_map('trim', $providers), function ($p) {
            return (string) $p !== '';
        })));

        $result = [];
        $missing = [];

        foreach ($providers as $provider) {
            $cacheKey = $this->rotationPanelCacheKey($panelType, $provider);
            if (Cache::has($cacheKey)) {
                $result[$provider] = Cache::get($cacheKey);

                continue;
            }
            $missing[] = $provider;
        }

        if ($missing !== []) {
            $resolved = $this->resolveMarzbanPanelsForProvidersUncached($missing, $panelType);
            foreach ($resolved as $provider => $panel) {
                $cacheKey = $this->rotationPanelCacheKey($panelType, $provider);
                Cache::put($cacheKey, $panel, $ttl);
                $result[$provider] = $panel;
            }
        }

        $ordered = [];
        foreach ($providers as $provider) {
            $ordered[$provider] = $result[$provider] ?? null;
        }

        return $ordered;
    }

    /**
     * Резолв панелей для списка провайдеров без чтения кэша (тяжёлая часть — один батч по метрикам).
     *
     * @param array<int, string> $missing
     * @return array<string, Panel|null>
     */
    private function resolveMarzbanPanelsForProvidersUncached(array $missing, string $panelType): array
    {
        $out = [];
        $collectByProvider = $this->getCandidatePanelsForRotationBatch($panelType, $missing);

        $allIds = [];
        foreach ($collectByProvider as $coll) {
            foreach ($coll->pluck('id') as $id) {
                $allIds[(int) $id] = true;
            }
        }
        $allIds = array_keys($allIds);

        $monitoring = $this->loadLatestMonitoringByPanelIds($allIds);
        $activeDb = $this->loadActiveUsersCountByPanelIds($allIds);
        $lastAt = $this->loadLastServerUserCreatedAtByPanelIds($allIds);

        foreach ($missing as $provider) {
            $panels = $collectByProvider[$provider];
            if ($panels->isEmpty()) {
                Log::info('PANEL_SELECTION: No panels for provider', ['provider' => $provider, 'source' => 'panel']);
                $out[$provider] = null;

                continue;
            }

            $panel = $this->selectOptimalPanelIntelligentWithMaps($panels, $monitoring, $activeDb, $lastAt);
            if ($panel) {
                $out[$provider] = $panel;

                continue;
            }

            Log::warning('PANEL_SELECTION [INTELLIGENT]: No valid score for provider, fallback to lowest id', [
                'provider' => $provider,
                'source' => 'panel',
            ]);

            $out[$provider] = $panels->sortBy('id')->first();
        }

        return $out;
    }

    /**
     * Сводка по интеллектуальной ротации для админки (без сравнения со старыми стратегиями).
     *
     * @param string|null $panelType по умолчанию Panel::MARZBAN
     */
    private const COMPARISON_PANELS_LIMIT = 150;

    public function compareAllStrategies(?string $panelType = null): array
    {
        $panelType = $panelType ?? Panel::MARZBAN;

        try {
            $rotationPanels = $this->query()
                ->where('panel_status', Panel::PANEL_CONFIGURED)
                ->where('panel', $panelType)
                ->where('has_error', false)
                ->where('excluded_from_rotation', false)
                ->whereNotIn('id', function ($query) {
                    $query->select('panel_id')
                        ->from('salesman')
                        ->whereNotNull('panel_id');
                })
                ->with('server.location')
                ->orderBy('id')
                ->limit(self::COMPARISON_PANELS_LIMIT)
                ->get();

            if ($rotationPanels->isEmpty()) {
                return ['error' => 'Нет доступных панелей'];
            }

            $intelligentPanel = null;
            try {
                $intelligentPanel = $this->selectOptimalPanelIntelligent($rotationPanels, false)
                    ?? $rotationPanels->sortBy('id')->first();
            } catch (\Exception $e) {
                Log::warning('Failed to select panel intelligently in comparison', [
                    'error' => $e->getMessage(),
                    'source' => 'panel',
                ]);
                $intelligentPanel = $rotationPanels->sortBy('id')->first();
            }

            $allPanels = $this->query()
                ->where('panel_status', Panel::PANEL_CONFIGURED)
                ->where('panel', $panelType)
                ->where('has_error', false)
                ->whereNotIn('id', function ($query) {
                    $query->select('panel_id')
                        ->from('salesman')
                        ->whereNotNull('panel_id');
                })
                ->with('server.location')
                ->orderBy('id')
                ->limit(self::COMPARISON_PANELS_LIMIT)
                ->get();

            $ids = $allPanels->pluck('id')->map(fn ($id) => (int) $id)->all();
            $monitoring = $this->loadLatestMonitoringByPanelIds($ids);
            $activeDb = $this->loadActiveUsersCountByPanelIds($ids);
            $totals = $this->loadTotalUsersCountByPanelIds($ids);
            $lastAt = $this->loadLastServerUserCreatedAtByPanelIds($ids);

            $panelsInfo = $allPanels->map(function ($panel) use ($intelligentPanel, $monitoring, $activeDb, $totals, $lastAt) {
                try {
                    $pid = (int) $panel->id;
                    $mon = $monitoring[$pid] ?? null;
                    $latestStats = $mon['stats'] ?? null;
                    $fresh = $mon['fresh'] ?? false;

                    if (! $latestStats || ! $fresh) {
                        $activeUsers = $activeDb[$pid] ?? 0;
                    } else {
                        $activeUsers = (int) ($latestStats['users_active'] ?? 0);
                    }

                    $trafficData = null;
                    try {
                        $trafficData = $this->getServerTrafficData($panel);
                    } catch (\Exception $e) {
                        Log::warning('Failed to get traffic data for panel in comparison', [
                            'panel_id' => $panel->id,
                            'error' => $e->getMessage(),
                            'source' => 'panel',
                        ]);
                    }

                    $cpuUsage = $latestStats['cpu_usage'] ?? 0;
                    $memoryUsed = $latestStats['mem_used'] ?? 0;
                    $memoryTotal = $latestStats['mem_total'] ?? 1;
                    $memoryUsage = ($memoryTotal > 0) ? ($memoryUsed / $memoryTotal) * 100 : 0;

                    $intelligentScore = $this->calculatePanelScore(
                        $panel,
                        $activeUsers,
                        $latestStats,
                        $lastAt[$pid] ?? null
                    );

                    return [
                        'id' => $panel->id,
                        'address' => $panel->panel_adress,
                        'server_name' => $panel->server->name ?? 'N/A',
                        'server_id' => $panel->server_id,
                        'total_users' => $totals[$pid] ?? 0,
                        'active_users' => $activeUsers,
                        'cpu_usage' => round($cpuUsage, 1),
                        'memory_usage' => round($memoryUsage, 1),
                        'traffic_used_percent' => $trafficData['used_percent'] ?? null,
                        'traffic_used_gb' => $trafficData ? round($trafficData['used'] / (1024 * 1024 * 1024), 2) : null,
                        'traffic_limit_gb' => $trafficData ? round($trafficData['limit'] / (1024 * 1024 * 1024), 2) : null,
                        'traffic_remaining_percent' => $trafficData['remaining_percent'] ?? null,
                        'intelligent_score' => round($intelligentScore, 1),
                        'last_activity' => $lastAt[$pid] ?? null,
                        'is_intelligent_selected' => $intelligentPanel && $intelligentPanel->id === $panel->id,
                        'has_fresh_stats' => $fresh && $latestStats !== null,
                        'has_traffic_data' => $trafficData !== null,
                        'excluded_from_rotation' => $panel->excluded_from_rotation ?? false,
                    ];
                } catch (\Exception $e) {
                    Log::warning('Error processing panel in comparison', [
                        'panel_id' => $panel->id,
                        'error' => $e->getMessage(),
                        'source' => 'panel',
                    ]);

                    return [
                        'id' => $panel->id,
                        'address' => $panel->panel_adress,
                        'server_name' => $panel->server->name ?? 'N/A',
                        'server_id' => $panel->server_id,
                        'total_users' => 0,
                        'active_users' => 0,
                        'cpu_usage' => 0,
                        'memory_usage' => 0,
                        'traffic_used_percent' => null,
                        'traffic_used_gb' => null,
                        'traffic_limit_gb' => null,
                        'traffic_remaining_percent' => null,
                        'intelligent_score' => 0,
                        'last_activity' => null,
                        'is_intelligent_selected' => false,
                        'has_fresh_stats' => false,
                        'has_traffic_data' => false,
                        'excluded_from_rotation' => $panel->excluded_from_rotation ?? false,
                    ];
                }
            });

            $strategyStats = [
                'intelligent' => [
                    'selected_panel_id' => $intelligentPanel ? $intelligentPanel->id : null,
                    'selected_panel_info' => $intelligentPanel ? $panelsInfo->firstWhere('id', $intelligentPanel->id) : null,
                ],
            ];

            return [
                'strategies' => $strategyStats,
                'panels' => $panelsInfo->values(),
                'summary' => [
                    'total_panels' => $allPanels->count(),
                    'panels_with_stats' => $panelsInfo->where('has_fresh_stats', true)->count(),
                    'panels_with_traffic' => $panelsInfo->where('has_traffic_data', true)->count(),
                    'avg_users' => round($panelsInfo->avg('total_users'), 1),
                    'avg_active_users' => round($panelsInfo->where('active_users', '>', 0)->avg('active_users') ?: 0, 1),
                    'avg_cpu' => round($panelsInfo->where('cpu_usage', '>', 0)->avg('cpu_usage') ?: 0, 1),
                    'avg_memory' => round($panelsInfo->where('memory_usage', '>', 0)->avg('memory_usage') ?: 0, 1),
                    'avg_traffic' => round($panelsInfo->where('traffic_used_percent', '>', 0)->avg('traffic_used_percent') ?: 0, 1),
                ],
                'timestamp' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to compare strategies', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'panel',
            ]);

            return ['error' => 'Ошибка при получении данных: ' . $e->getMessage()];
        }
    }

    // ==================== ПРИВАТНЫЕ МЕТОДЫ НОВОЙ СИСТЕМЫ ====================

    /**
     * Интеллектуальный выбор панели (пакетные запросы к БД вместо N+1 по каждой панели).
     *
     * @param bool $useCacheOnly зарезервировано
     */
    private function selectOptimalPanelIntelligent(Collection $panels, bool $useCacheOnly = false): ?Panel
    {
        if ($panels->isEmpty()) {
            return null;
        }

        $ids = $panels->pluck('id')->map(fn ($id) => (int) $id)->all();
        $monitoring = $this->loadLatestMonitoringByPanelIds($ids);
        $activeDb = $this->loadActiveUsersCountByPanelIds($ids);
        $lastAt = $this->loadLastServerUserCreatedAtByPanelIds($ids);

        return $this->selectOptimalPanelIntelligentWithMaps($panels, $monitoring, $activeDb, $lastAt);
    }

    /**
     * То же, что selectOptimalPanelIntelligent, но с уже загруженными картами метрик (для батча по провайдерам).
     */
    private function selectOptimalPanelIntelligentWithMaps(Collection $panels, array $monitoring, array $activeDb, array $lastAt): ?Panel
    {
        if ($panels->isEmpty()) {
            return null;
        }

        $scoredPanels = $panels->map(function ($panel) use ($monitoring, $activeDb, $lastAt) {
            $pid = (int) $panel->id;
            $mon = $monitoring[$pid] ?? null;
            $latestStats = $mon['stats'] ?? null;
            $fresh = $mon['fresh'] ?? false;

            if (! $latestStats || ! $fresh) {
                $activeUsers = $activeDb[$pid] ?? 0;
            } else {
                $activeUsers = (int) ($latestStats['users_active'] ?? 0);
            }

            $lastCreated = $lastAt[$pid] ?? null;
            $score = $this->calculatePanelScore($panel, $activeUsers, $latestStats, $lastCreated);

            return [
                'panel' => $panel,
                'score' => $score,
                'active_users' => $activeUsers,
                'has_fresh_stats' => $fresh && $latestStats !== null,
            ];
        })->filter(fn ($item) => $item['score'] >= 0);

        if ($scoredPanels->isEmpty()) {
            Log::warning('PANEL_SELECTION [NEW]: No panels with valid scores', ['source' => 'panel']);

            return null;
        }

        $selectedPanelData = $scoredPanels->sortByDesc('score')->first();

        return $selectedPanelData['panel'];
    }

    /**
     * Расчет комплексного score для панели
     */
    private function calculatePanelScore(
        Panel $panel,
        int $activeUsers,
        ?array $latestStats = null,
        ?Carbon $lastUserCreatedAt = null
    ): float {
        $score = 100.0;

        $userScore = $this->calculateUserBasedScore($activeUsers);
        $score += $userScore * 50;

        $loadScore = $this->calculateLoadBasedScore($panel, $latestStats);
        $score += $loadScore * 40;

        $timeScore = $lastUserCreatedAt !== null
            ? $this->calculateTimeBasedScoreFromCarbon($lastUserCreatedAt)
            : $this->calculateTimeBasedScore($panel);
        $score += $timeScore * 5;

        return max($score, 0);
    }

    /**
     * Score на основе количества пользователей (плавная функция вместо жестких порогов)
     */
    private function calculateUserBasedScore(int $activeUsersCount): float
    {
        // Плавная функция вместо жестких порогов
        // Используем реальные цифры из статистики: 465-520 активных пользователей
        $maxExpectedUsers = 530; // Максимальное ожидаемое количество пользователей
        
        // Нормализуем количество пользователей (0-1)
        $normalized = min(1.0, $activeUsersCount / $maxExpectedUsers);
        
        // Обратная зависимость: чем меньше пользователей, тем выше score
        // Используем квадратичную функцию для более плавного перехода
        $score = 1.0 - ($normalized * $normalized);
        
        // Обеспечиваем минимальный score даже при большом количестве пользователей
        return max(0.0, $score);
    }

    /**
     * Score на основе нагрузки сервера (плавная функция)
     */
    private function calculateLoadBasedScore(Panel $panel, ?array $latestStats = null): float
    {
        // Используем переданную статистику или получаем заново
        if (!$latestStats) {
            $latestStats = $this->getLatestPanelStats($panel->id);
        }

        if (!$latestStats) {
            // Если статистики нет, используем средний score, но с понижением
            return 0.3; // Понижен с 0.5, чтобы панели без статистики были менее приоритетными
        }

        $cpuUsage = $latestStats['cpu_usage'] ?? 100;
        $memoryUsed = $latestStats['mem_used'] ?? 0;
        $memoryTotal = $latestStats['mem_total'] ?? 1;
        $memoryUsage = ($memoryTotal > 0) ? ($memoryUsed / $memoryTotal) * 100 : 100;

        // Берем среднее значение CPU и памяти (более справедливо, чем максимум)
        $combinedLoad = ($cpuUsage + $memoryUsage) / 2;

        // Плавная функция вместо жестких порогов
        // Чем меньше нагрузка, тем выше score
        // Используем обратную квадратичную функцию для плавного перехода
        $normalizedLoad = min(1.0, $combinedLoad / 100);
        $score = 1.0 - ($normalizedLoad * $normalizedLoad);
        
        return max(0.0, $score);
    }

    /**
     * Score на основе времени последней активности
     */
    private function calculateTimeBasedScore(Panel $panel): float
    {
        return $this->calculateTimeBasedScoreFromCarbon($this->getLastUserCreationTime($panel->id));
    }

    private function calculateTimeBasedScoreFromCarbon(?Carbon $lastUserTime): float
    {
        if (! $lastUserTime) {
            return 1.0;
        }

        $minutesSinceLast = Carbon::now()->diffInMinutes($lastUserTime);

        if ($minutesSinceLast < 5) {
            return 0.0;
        }
        if ($minutesSinceLast < 15) {
            return 0.3;
        }
        if ($minutesSinceLast < 30) {
            return 0.6;
        }
        if ($minutesSinceLast < 60) {
            return 0.8;
        }

        return 1.0;
    }

    /**
     * Получение количества активных пользователей из статистики Marzban
     */
    private function getActiveUsersFromStats(int $panelId): int
    {
        $latestStats = $this->getLatestPanelStats($panelId);

        // Берем реальные данные из статистики Marzban
        return $latestStats['users_active'] ?? 0;
    }

    /**
     * Количество активных пользователей по панелям из статистики Marzban (server_monitoring).
     * Тот же источник, что и в «Детальная информация по панелям» — числа совпадают.
     *
     * @return array<int, int> [panel_id => active_users]
     */
    public function getActiveUserCountPerPanelFromStats(): array
    {
        $panelIds = Panel::query()
            ->where('panel', Panel::MARZBAN)
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('has_error', false)
            ->where('excluded_from_rotation', false)
            ->whereNotIn('id', function ($query) {
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->pluck('id');

        if ($panelIds->isEmpty()) {
            return [];
        }

        $latestPerPanel = ServerMonitoring::query()
            ->whereIn('panel_id', $panelIds)
            ->orderByDesc('created_at')
            ->get()
            ->unique('panel_id');

        $counts = [];
        foreach ($panelIds as $id) {
            $counts[$id] = 0;
        }
        foreach ($latestPerPanel as $row) {
            $decoded = is_string($row->statistics) ? json_decode($row->statistics, true) : $row->statistics;
            $counts[$row->panel_id] = (int) ($decoded['users_active'] ?? 0);
        }

        return $counts;
    }

    /**
     * Старый метод подсчета активных пользователей (для отладки)
     */
    private function getActiveUsersCount(int $panelId): int
    {
        return ServerUser::where('panel_id', $panelId)
            ->whereHas('keyActivateUser.keyActivate', function ($query) {
                $query->where('status', KeyActivate::ACTIVE);
            })
            ->count();
    }

    /**
     * Получение времени создания последнего пользователя
     */
    private function getLastUserCreationTime(int $panelId): ?Carbon
    {
        $lastUser = ServerUser::where('panel_id', $panelId)
            ->latest()
            ->first();

        return $lastUser ? $lastUser->created_at : null;
    }

    /**
     * Получение последней статистики панели
     */
    private function getLatestPanelStats(int $panelId): ?array
    {
        $latestStats = ServerMonitoring::where('panel_id', $panelId)
            ->latest()
            ->first();

        return $latestStats ? json_decode($latestStats->statistics, true) : null;
    }

    /**
     * Проверка актуальности статистики панели
     * Статистика считается актуальной, если она не старше 5 минут
     * 
     * @param int $panelId
     * @return bool
     */
    private function isStatsFresh(int $panelId): bool
    {
        $latestStats = ServerMonitoring::where('panel_id', $panelId)
            ->latest()
            ->first();

        if (!$latestStats) {
            return false;
        }

        // Статистика считается актуальной, если она не старше 5 минут
        $maxAgeMinutes = 5;
        $ageMinutes = Carbon::now()->diffInMinutes($latestStats->created_at);
        
        return $ageMinutes <= $maxAgeMinutes;
    }

    /**
     * Получить данные о трафике сервера только из кэша (без запросов к API)
     * Используется для быстрой активации ключей
     * 
     * @param Panel $panel Панель
     * @param string|null $provider Провайдер сервера (по умолчанию Server::VDSINA для обратной совместимости)
     * @return array|null
     */
    private function getServerTrafficDataFromCache(Panel $panel, ?string $provider = null): ?array
    {
        if (!$panel->server) {
            return null;
        }
        $provider = $provider ?? $panel->server->provider;
        if (!$provider || !$panel->server->provider_id) {
            return null;
        }
        $cacheKey = "server_traffic_{$provider}_{$panel->server->provider_id}";
        return Cache::get($cacheKey);
    }

    /**
     * Получить данные о трафике сервера.
     * Провайдер определяется по серверу панели; для каждого провайдера — своя ветка (VDSina, Timeweb и т.д.).
     * Формат ответа: ['limit' => bytes, 'current_month' => bytes, 'last_month' => bytes|null].
     *
     * @param Panel $panel Панель
     * @param string|null $provider Провайдер (если null — берётся из $panel->server->provider)
     * @return array|null
     */
    public function getServerTrafficData(Panel $panel, ?string $provider = null): ?array
    {
        if (!$panel->server) {
            return null;
        }

        $provider = $provider ?? $panel->server->provider;
        if (!$provider || !$panel->server->provider_id) {
            return null;
        }

        $cacheKey = "server_traffic_{$provider}_{$panel->server->provider_id}";
        $cacheTtl = (int) config('panel.traffic_cache_ttl', 1800);

        // Ветвление по провайдеру сервера (при добавлении нового провайдера — добавить ветку и API)
        if ($provider === Server::TIMEWEB) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                $cacheAge = Cache::get("{$cacheKey}_age", 0);
                $cacheMaxAge = $cacheTtl - 600;
                if ($cacheAge > 0 && (time() - $cacheAge) < $cacheMaxAge) {
                    return $cachedData;
                }
            }
            try {
                $timewebApi = new TimewebCloudAPI(config('services.api_keys.timeweb_key'));
                $trafficData = $timewebApi->getServerTraffic((int) $panel->server->provider_id);
                if ($trafficData !== null) {
                    Cache::put($cacheKey, $trafficData, $cacheTtl);
                    Cache::put("{$cacheKey}_age", time(), $cacheTtl);
                    return $trafficData;
                }
            } catch (\Exception $e) {
                Log::error('Timeweb Cloud: failed to get server traffic', [
                    'server_id' => $panel->server->id,
                    'provider_id' => $panel->server->provider_id,
                    'error' => $e->getMessage(),
                    'source' => 'panel',
                ]);
            }
            return $cachedData;
        }

        if ($provider !== Server::VDSINA) {
            return null;
        }

        // Проверяем глобальный флаг блокировки API из-за rate limit (403 Blacklisted)
        // Согласно поддержке VDSina, блокировка снимается автоматически через 4 часа
        $rateLimitBlocked = Cache::get('vdsina_api_rate_limit_blocked', false);
        $rateLimitBlockedUntil = Cache::get('vdsina_api_rate_limit_blocked_until', 0);
        
        // Если блокировка активна, НЕ делаем запросы к API, используем только кэш
        if ($rateLimitBlocked && $rateLimitBlockedUntil > time()) {
            $remainingTime = $rateLimitBlockedUntil - time();
            $remainingHours = round($remainingTime / 3600, 1);
            
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                Log::info('PANEL_SELECTION [TRAFFIC]: API blocked (403 Blacklisted), using cached data only (no new requests)', [
                    'source' => 'panel',
                    'server_id' => $panel->server->id,
                    'provider_id' => $panel->server->provider_id,
                    'blocked_until' => date('Y-m-d H:i:s', $rateLimitBlockedUntil),
                    'remaining_hours' => $remainingHours,
                    'message' => 'VDSina API will auto-unblock after 4 hours from last problematic request'
                ]);
                return $cachedData;
            }
            
            // Если кэша нет, возвращаем null (НЕ делаем запрос к API)
            Log::warning('PANEL_SELECTION [TRAFFIC]: API blocked but no cached data available', [
                'source' => 'panel',
                'server_id' => $panel->server->id,
                'provider_id' => $panel->server->provider_id,
                'blocked_until' => date('Y-m-d H:i:s', $rateLimitBlockedUntil),
                'remaining_hours' => $remainingHours
            ]);
            return null;
        }
        
        // Если блокировка истекла, снимаем флаги
        if ($rateLimitBlocked && $rateLimitBlockedUntil <= time()) {
            Cache::forget('vdsina_api_rate_limit_blocked');
            Cache::forget('vdsina_api_rate_limit_blocked_until');
            Log::info('PANEL_SELECTION [TRAFFIC]: Rate limit block expired, API requests allowed again', [
                'source' => 'panel',
                'server_id' => $panel->server->id
            ]);
        }

        // Сначала проверяем, есть ли уже кэшированные данные
        $cachedData = Cache::get($cacheKey);
        
        // Если кэш есть и он еще свежий (больше 10 минут осталось), возвращаем его
        // Это позволяет избежать лишних запросов к API
        if ($cachedData !== null) {
            $cacheAge = Cache::get("{$cacheKey}_age", 0);
            $cacheMaxAge = $cacheTtl - 600; // 10 минут до истечения
            if ($cacheAge > 0 && (time() - $cacheAge) < $cacheMaxAge) {
                return $cachedData;
            }
        }
        
        // Пытаемся получить свежие данные
        try {
            $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
            $trafficData = $vdsinaApi->getServerTraffic((int)$panel->server->provider_id);

            if ($trafficData !== null) {
                // Сохраняем в кэш
                Cache::put($cacheKey, $trafficData, $cacheTtl);
                Cache::put("{$cacheKey}_age", time(), $cacheTtl);
                
                // Снимаем блокировку, если она была
                Cache::forget('vdsina_api_rate_limit_blocked');
                Cache::forget('vdsina_api_rate_limit_blocked_until');
                
                return $trafficData;
            }
        } catch (\Exception $e) {
            // Обрабатываем ошибки rate limit gracefully
            $isRateLimit = strpos($e->getMessage(), 'rate limit') !== false 
                || strpos($e->getMessage(), 'Blacklisted') !== false
                || strpos($e->getMessage(), '403') !== false;
            
            if ($isRateLimit) {
                // Устанавливаем блокировку на 4 часа (согласно ответу поддержки VDSina)
                $blockDuration = 14400; // 4 часа в секундах
                $blockUntil = time() + $blockDuration;
                Cache::put('vdsina_api_rate_limit_blocked', true, $blockDuration);
                Cache::put('vdsina_api_rate_limit_blocked_until', $blockUntil, $blockDuration);
                
                Log::warning('PANEL_SELECTION [TRAFFIC]: Rate limit exceeded (403 Blacklisted), blocking API requests for 4 hours', [
                    'source' => 'panel',
                    'server_id' => $panel->server->id,
                    'provider_id' => $panel->server->provider_id,
                    'blocked_until' => date('Y-m-d H:i:s', $blockUntil),
                    'block_duration_hours' => 4
                ]);
            } else {
                Log::error('PANEL_SELECTION [TRAFFIC]: Failed to get traffic data', [
                    'source' => 'panel',
                    'server_id' => $panel->server->id,
                    'provider_id' => $panel->server->provider_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Если получили ошибку, но есть старые кэшированные данные - используем их
        if ($cachedData !== null) {
            Log::info('PANEL_SELECTION [TRAFFIC]: Using stale cached data due to API error', [
                'source' => 'panel',
                'server_id' => $panel->server->id,
                'provider_id' => $panel->server->provider_id
            ]);
            return $cachedData;
        }
        
        return null;
    }

    // ==================== МЕТОДЫ ДЛЯ СТАТИСТИКИ ПО МЕСЯЦАМ ====================

    /**
     * Получить статистику по панелям за текущий и прошлый месяц
     * API предоставляет данные только за текущий и прошлый месяц
     * 
     * @return array
     */
    public function getMonthlyStatistics(): array
    {
        $now = Carbon::now();
        
        // Определяем период текущего месяца
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        
        // Определяем период прошлого месяца (для сравнения)
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
        
        // Получаем все настроенные панели
        $panels = $this->getAllConfiguredPanels();
        
        $statistics = [];
        
        foreach ($panels as $panel) {
            // Получаем статистику за текущий месяц
            $currentMonthStats = $this->getPanelStatsForPeriod($panel, $currentMonthStart, $currentMonthEnd);
            
            // Получаем статистику за прошлый месяц
            $lastMonthStats = $this->getPanelStatsForPeriod($panel, $lastMonthStart, $lastMonthEnd);
            
            // Получаем данные о трафике из API
            $trafficData = $this->getServerTrafficData($panel);
            
            // Трафик за текущий месяц (из API current_month)
            $currentTraffic = null;
            if ($trafficData && array_key_exists('current_month', $trafficData) && $trafficData['current_month'] !== null) {
                $limitBytes = (int) ($trafficData['limit'] ?? 0);
                $currentTrafficBytes = (int) $trafficData['current_month'];
                $currentTraffic = [
                    'used_tb' => round($currentTrafficBytes / (1024 * 1024 * 1024 * 1024), 2),
                    'limit_tb' => $limitBytes > 0 ? round($limitBytes / (1024 * 1024 * 1024 * 1024), 2) : 0,
                    'used_percent' => $limitBytes > 0 ? round(($currentTrafficBytes / $limitBytes) * 100, 2) : 0,
                ];
            }
            
            // Трафик за прошлый месяц (из API last_month или из сохраненных данных)
            $lastTraffic = null;
            if ($trafficData && array_key_exists('last_month', $trafficData) && $trafficData['last_month'] !== null) {
                $limitBytes = (int) ($trafficData['limit'] ?? 0);
                $lastTrafficBytes = (int) $trafficData['last_month'];
                $lastTraffic = [
                    'used_tb' => round($lastTrafficBytes / (1024 * 1024 * 1024 * 1024), 2),
                    'limit_tb' => $limitBytes > 0 ? round($limitBytes / (1024 * 1024 * 1024 * 1024), 2) : 0,
                    'used_percent' => $limitBytes > 0 ? round(($lastTrafficBytes / $limitBytes) * 100, 2) : 0,
                ];
            } else {
                // Если API не предоставляет данные, пытаемся получить из сохраненных
                $savedStats = PanelMonthlyStatistics::where('panel_id', $panel->id)
                    ->where('year', $lastMonthStart->year)
                    ->where('month', $lastMonthStart->month)
                    ->first();
                
                if ($savedStats && $savedStats->traffic_used_bytes !== null) {
                    $limitBytes = $savedStats->traffic_limit_bytes ?? ($trafficData['limit'] ?? 0);
                    $lastTraffic = [
                        'used_tb' => round($savedStats->traffic_used_bytes / (1024 * 1024 * 1024 * 1024), 2),
                        'limit_tb' => round($limitBytes / (1024 * 1024 * 1024 * 1024), 2),
                        'used_percent' => $savedStats->traffic_used_percent ?? ($limitBytes > 0 ? round(($savedStats->traffic_used_bytes / $limitBytes) * 100, 2) : 0),
                    ];
                }
            }
            
            // Вычисляем динамику
            $trafficChange = null;
            if ($currentTraffic && $lastTraffic) {
                $trafficChange = round($currentTraffic['used_percent'] - $lastTraffic['used_percent'], 2);
            }
            
            $activeUsersChange = null;
            if ($currentMonthStats['active_users'] !== null && $lastMonthStats['active_users'] !== null) {
                $activeUsersChange = $currentMonthStats['active_users'] - $lastMonthStats['active_users'];
            }
            
            $onlineUsersChange = null;
            if ($currentMonthStats['online_users'] !== null && $lastMonthStats['online_users'] !== null) {
                $onlineUsersChange = $currentMonthStats['online_users'] - $lastMonthStats['online_users'];
            }
            
            $statistics[] = [
                'panel_id' => $panel->id,
                'panel_address' => $panel->panel_adress,
                'server_name' => $panel->server->name ?? 'N/A',
                'current_month' => [
                    'active_users' => $currentMonthStats['active_users'],
                    'online_users' => $currentMonthStats['online_users'],
                    'traffic' => $currentTraffic,
                ],
                'last_month' => [
                    'active_users' => $lastMonthStats['active_users'],
                    'online_users' => $lastMonthStats['online_users'],
                    'traffic' => $lastTraffic,
                ],
                'changes' => [
                    'active_users' => $activeUsersChange,
                    'online_users' => $onlineUsersChange,
                    'traffic_percent' => $trafficChange,
                ],
                'period' => [
                    'current' => [
                        'year' => $now->year,
                        'month' => $now->month,
                        'name' => $currentMonthStart->locale('ru')->monthName,
                    ],
                    'last' => [
                        'year' => $lastMonthStart->year,
                        'month' => $lastMonthStart->month,
                        'name' => $lastMonthStart->locale('ru')->monthName,
                    ],
                ],
            ];
        }
        
        return $statistics;
    }

    /**
     * Получить статистику панели за определенный период
     * Берет среднее значение за последние 7 дней периода или последнее значение
     * 
     * @param Panel $panel
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getPanelStatsForPeriod(Panel $panel, Carbon $startDate, Carbon $endDate): array
    {
        // Получаем статистики за период, но для более точной оценки берем последние 7 дней месяца
        $periodEnd = min($endDate, Carbon::now());
        $periodStart = max($startDate, $periodEnd->copy()->subDays(7));
        
        $stats = ServerMonitoring::where('panel_id', $panel->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Если за последние 7 дней нет данных, берем все данные за период
        if ($stats->isEmpty()) {
            $stats = ServerMonitoring::where('panel_id', $panel->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get();
        }
        
        if ($stats->isEmpty()) {
            return [
                'active_users' => null,
                'online_users' => null,
            ];
        }
        
        // Берем средние значения за период
        $activeUsers = [];
        $onlineUsers = [];
        
        foreach ($stats as $stat) {
            $data = json_decode($stat->statistics, true);
            if ($data) {
                if (isset($data['users_active'])) {
                    $activeUsers[] = (int)$data['users_active'];
                }
                if (isset($data['online_users'])) {
                    $onlineUsers[] = (int)$data['online_users'];
                }
            }
        }
        
        // Возвращаем среднее значение или последнее значение
        $avgActiveUsers = !empty($activeUsers) ? (int)round(array_sum($activeUsers) / count($activeUsers)) : null;
        $avgOnlineUsers = !empty($onlineUsers) ? (int)round(array_sum($onlineUsers) / count($onlineUsers)) : null;
        
        // Если среднее не получилось, берем последнее значение
        if ($avgActiveUsers === null && !empty($activeUsers)) {
            $avgActiveUsers = end($activeUsers);
        }
        if ($avgOnlineUsers === null && !empty($onlineUsers)) {
            $avgOnlineUsers = end($onlineUsers);
        }
        
        return [
            'active_users' => $avgActiveUsers,
            'online_users' => $avgOnlineUsers,
        ];
    }

    /**
     * Получить исторические данные для графиков
     * Использует данные из API для текущего и прошлого месяца, сохраненные данные для более старых месяцев
     * 
     * @param int $months Количество месяцев для отображения (по умолчанию 6)
     * @return array
     */
    public function getHistoricalStatistics(int $months = 6): array
    {
        $now = Carbon::now();
        $panels = $this->getAllConfiguredPanels();
        
        $historicalData = [];
        
        foreach ($panels as $panel) {
            $panelData = [
                'panel_id' => $panel->id,
                'panel_address' => $panel->panel_adress,
                'server_name' => $panel->server->name ?? 'N/A',
                'months' => [],
            ];
            
            // Получаем данные о трафике из API (для текущего и прошлого месяца)
            $trafficData = $this->getServerTrafficData($panel);
            
            // Определяем текущий и прошлый месяц один раз
            $currentYear = $now->year;
            $currentMonth = $now->month;
            $lastMonthDate = $now->copy()->subMonth();
            $lastYear = $lastMonthDate->year;
            $lastMonth = $lastMonthDate->month;
            
            // Получаем данные за последние N месяцев (от самого старого к текущему)
            // $i = 5 означает 5 месяцев назад, $i = 0 означает текущий месяц
            for ($i = $months - 1; $i >= 0; $i--) {
                $monthDate = $now->copy()->subMonths($i);
                $year = $monthDate->year;
                $month = $monthDate->month;
                
                $isCurrentMonth = ($year == $currentYear && $month == $currentMonth);
                $isLastMonth = ($year == $lastYear && $month == $lastMonth);
                
                // Processing month statistics
                
                $monthData = [
                    'year' => $year,
                    'month' => $month,
                    'month_name' => $monthDate->locale('ru')->monthName,
                    'active_users' => null,
                    'online_users' => null,
                    'traffic_used_tb' => null,
                    'traffic_limit_tb' => null,
                    'traffic_used_percent' => null,
                ];
                
                // Для текущего и прошлого месяца используем данные из API
                if ($isCurrentMonth || $isLastMonth) {
                    // Получаем статистику пользователей за период
                    $monthStart = $monthDate->copy()->startOfMonth();
                    $monthEnd = $monthDate->copy()->endOfMonth();
                    $stats = $this->getPanelStatsForPeriod($panel, $monthStart, $monthEnd);
                    
                    $monthData['active_users'] = $stats['active_users'];
                    $monthData['online_users'] = $stats['online_users'];
                    
                    // Получаем данные о трафике из API
                    if ($trafficData) {
                        $limitBytes = $trafficData['limit'];
                        $trafficLimitTb = round($limitBytes / (1024 * 1024 * 1024 * 1024), 2);
                        $monthData['traffic_limit_tb'] = $trafficLimitTb;
                        
                        if ($isCurrentMonth) {
                            // Текущий месяц
                            if (isset($trafficData['current_month']) && $trafficData['current_month'] !== null) {
                                $trafficBytes = $trafficData['current_month'];
                                $monthData['traffic_used_tb'] = round($trafficBytes / (1024 * 1024 * 1024 * 1024), 2);
                                $monthData['traffic_used_percent'] = $limitBytes > 0 ? round(($trafficBytes / $limitBytes) * 100, 2) : 0;
                            }
                        } elseif ($isLastMonth) {
                            // Прошлый месяц
                            if (isset($trafficData['last_month']) && $trafficData['last_month'] !== null && $trafficData['last_month'] > 0) {
                                // Используем данные из API
                                $trafficBytes = $trafficData['last_month'];
                                $monthData['traffic_used_tb'] = round($trafficBytes / (1024 * 1024 * 1024 * 1024), 2);
                                $monthData['traffic_used_percent'] = $limitBytes > 0 ? round(($trafficBytes / $limitBytes) * 100, 2) : 0;
                                
                                // Last month traffic data retrieved from API
                            } else {
                                // Если API не предоставляет данные, пытаемся получить из сохраненных
                                $savedStats = PanelMonthlyStatistics::where('panel_id', $panel->id)
                                    ->where('year', $year)
                                    ->where('month', $month)
                                    ->first();
                                
                                if ($savedStats && $savedStats->traffic_used_bytes !== null) {
                                    $savedLimitBytes = $savedStats->traffic_limit_bytes ?? $limitBytes;
                                    $monthData['traffic_used_tb'] = round($savedStats->traffic_used_bytes / (1024 * 1024 * 1024 * 1024), 2);
                                    $monthData['traffic_limit_tb'] = round($savedLimitBytes / (1024 * 1024 * 1024 * 1024), 2);
                                    $monthData['traffic_used_percent'] = $savedStats->traffic_used_percent ?? ($savedLimitBytes > 0 ? round(($savedStats->traffic_used_bytes / $savedLimitBytes) * 100, 2) : 0);
                                    
                                    // Last month traffic data retrieved from saved statistics
                                } else {
                                    Log::warning('Last month traffic data not available from API or saved statistics', [
                                        'source' => 'panel',
                                        'panel_id' => $panel->id,
                                        'year' => $year,
                                        'month' => $month,
                                        'api_last_month' => $trafficData['last_month'] ?? 'not set',
                                        'saved_stats_exists' => $savedStats !== null,
                                    ]);
                                }
                            }
                        }
                    }
                } else {
                    // Для более старых месяцев используем сохраненные данные
                    $savedStats = PanelMonthlyStatistics::where('panel_id', $panel->id)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->first();
                    
                    if ($savedStats) {
                        $monthData['active_users'] = $savedStats->active_users;
                        $monthData['online_users'] = $savedStats->online_users;
                        $monthData['traffic_used_tb'] = $savedStats->traffic_used_bytes ? round($savedStats->traffic_used_bytes / (1024 * 1024 * 1024 * 1024), 2) : null;
                        $monthData['traffic_limit_tb'] = $savedStats->traffic_limit_bytes ? round($savedStats->traffic_limit_bytes / (1024 * 1024 * 1024 * 1024), 2) : null;
                        $monthData['traffic_used_percent'] = $savedStats->traffic_used_percent;
                    }
                }
                
                $panelData['months'][] = $monthData;
            }
            
            $historicalData[] = $panelData;
        }
        
        return $historicalData;
    }

    /**
     * Пометить панель как имеющую ошибку
     * 
     * @param int $panelId
     * @param string $errorMessage
     * @return void
     */
    public function markPanelWithError(int $panelId, string $errorMessage): void
    {
        $panel = $this->findOrFail($panelId);
        
        // Проверяем, не помечена ли уже панель как проблемная
        if (!$panel->has_error) {
            $panel->has_error = true;
            $panel->error_message = $errorMessage;
            $panel->error_at = now();
            $panel->save();

            // Создаем запись в истории ошибок
            PanelErrorHistory::create([
                'panel_id' => $panelId,
                'error_message' => $errorMessage,
                'error_occurred_at' => now(),
            ]);

            Log::warning('Panel marked with error', [
                'panel_id' => $panelId,
                'error_message' => $errorMessage,
                'source' => 'panel',
            ]);
        } else {
            // Если панель уже помечена, обновляем только сообщение об ошибке
            $panel->error_message = $errorMessage;
            $panel->error_at = now();
            $panel->save();
        }
    }

    /**
     * Снять пометку об ошибке с панели
     * 
     * @param int $panelId
     * @param string $resolutionType 'manual' или 'automatic'
     * @param string|null $resolutionNote Примечание о решении проблемы
     * @return void
     */
    public function clearPanelError(int $panelId, string $resolutionType = 'manual', ?string $resolutionNote = null): void
    {
        $panel = $this->findOrFail($panelId);
        
        if ($panel->has_error) {
            // Обновляем последнюю нерешенную запись в истории
            $lastError = PanelErrorHistory::where('panel_id', $panelId)
                ->whereNull('resolved_at')
                ->orderBy('error_occurred_at', 'desc')
                ->first();

            if ($lastError) {
                $lastError->resolved_at = now();
                $lastError->resolution_type = $resolutionType;
                $lastError->resolution_note = $resolutionNote ?? ($resolutionType === 'automatic' ? 'Проблема решена автоматической системой проверки' : 'Проблема решена администратором');
                $lastError->save();
            }

            $panel->has_error = false;
            $panel->error_message = null;
            $panel->error_at = null;
            $panel->save();

            Log::info('Panel error cleared', [
                'panel_id' => $panelId,
                'resolution_type' => $resolutionType,
                'source' => 'panel',
            ]);
        }
    }

    /**
     * Получить все панели с ошибками
     * 
     * @return Collection
     */
    public function getPanelsWithErrors(): Collection
    {
        return $this->query()
            ->where('has_error', true)
            ->with('server.location')
            ->orderBy('error_at', 'desc')
            ->get();
    }
}
