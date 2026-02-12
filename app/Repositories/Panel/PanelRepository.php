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
        
        return $query->get();
    }

    /**
     * Получить настроенную панель с минимальной нагрузкой
     * 
     * @param string|null $panelType Тип панели (по умолчанию Panel::MARZBAN для обратной совместимости)
     * @return Panel|null
     */
    public function getConfiguredMarzbanPanel(?string $panelType = null): ?Panel
    {
        // Используем Panel::MARZBAN по умолчанию для обратной совместимости
        $panelType = $panelType ?? Panel::MARZBAN;
        
        // Получаем все настроенные панели указанного типа (исключаем панели с ошибками)
        $panels = $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', $panelType)
            ->where('has_error', false) // Исключаем панели с ошибками
            ->whereNotIn('id', function ($query) {
                // Исключаем панели, которые привязаны к продавцам
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->with('server.location')
            ->get();

        // Исключаем панели по различным критериям
        $excludedLocations = array_map('intval', array_filter(config('panel.excluded_locations', [])));
        $excludedServerIPs = array_filter(config('panel.excluded_server_ips', []));
        $excludedServerIDs = array_map('intval', array_filter(config('panel.excluded_server_ids', [])));
        
        if (!empty($excludedLocations) || !empty($excludedServerIPs) || !empty($excludedServerIDs)) {
            $panels = $panels->filter(function ($panel) use ($excludedLocations, $excludedServerIPs, $excludedServerIDs) {
                // Проверяем локацию
                if (!empty($excludedLocations) && $panel->server && $panel->server->location_id) {
                    if (in_array($panel->server->location_id, $excludedLocations)) {
                        return false;
                    }
                }
                
                // Проверяем IP-адрес сервера
                if (!empty($excludedServerIPs) && $panel->server && $panel->server->ip) {
                    $serverIP = is_numeric($panel->server->ip) ? long2ip($panel->server->ip) : $panel->server->ip;
                    if (in_array($serverIP, $excludedServerIPs)) {
                        return false;
                    }
                }
                
                // Проверяем ID сервера
                if (!empty($excludedServerIDs) && $panel->server && $panel->server->id) {
                    if (in_array($panel->server->id, $excludedServerIDs)) {
                        return false;
                    }
                }
                
                return true;
            });
            
            if ($panels->isEmpty()) {
                Log::warning('PANEL_SELECTION [OLD]: No available panels after filtering', [
                    'excluded_locations' => $excludedLocations,
                    'excluded_server_ips' => $excludedServerIPs,
                    'excluded_server_ids' => $excludedServerIDs,
                    'source' => 'panel'
                ]);
                return null;
            }
        }

        if ($panels->isEmpty()) {
            Log::info('PANEL_SELECTION: No configured panels available', ['source' => 'panel']);
            return null;
        }

        // Считаем количество ключей для каждой панели
        $panelsWithKeyCount = $panels->map(function ($panel) {
            $keyCount = $this->getKeyCountForPanel($panel->id);
            return [
                'panel' => $panel,
                'key_count' => $keyCount,
            ];
        });

        // Если на всех панелях нет ключей, выбираем случайную панель
        if ($panelsWithKeyCount->sum('key_count') === 0) {
            $selectedPanel = $panels->random();
            Log::info('PANEL_SELECTION [OLD]: Random selection - Panel ID: ' . $selectedPanel->id . ' (no keys on any panel)', ['source' => 'panel']);
            return $selectedPanel;
        }

        // Иначе выбираем панель с минимальным количеством ключей
        $selectedPanel = $panelsWithKeyCount->sortBy('key_count')->first()['panel'];

        Log::info('PANEL_SELECTION [OLD]: Least loaded panel - Panel ID: ' . $selectedPanel->id .
            ', Keys: ' . $panelsWithKeyCount->sortBy('key_count')->first()['key_count'], ['source' => 'panel']);

        return $selectedPanel;
    }

    /**
     * Получаем количество ключей, привязанных к панели
     *
     * @param int $panelId
     * @return int
     */
    private function getKeyCountForPanel(int $panelId): int
    {
        return DB::table('server_user')
            ->where('panel_id', $panelId)
            ->count();
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

    // ==================== НОВЫЕ МЕТОДЫ ДЛЯ ИНТЕЛЛЕКТУАЛЬНОГО ВЫБОРА ====================

    /**
     * НОВАЯ СИСТЕМА: Получение оптимальной панели с интеллектуальным распределением
     * @return Panel|null
     */
    /**
     * Получить оптимизированную панель с минимальной нагрузкой
     * 
     * @param string|null $panelType Тип панели (по умолчанию Panel::MARZBAN для обратной совместимости)
     * @param bool $useCacheOnly Если true, использует только кэшированные данные (для быстрой активации ключей)
     * @return Panel|null
     */
    public function getOptimizedMarzbanPanel(?string $panelType = null, bool $useCacheOnly = false): ?Panel
    {
        // Используем Panel::MARZBAN по умолчанию для обратной совместимости
        $panelType = $panelType ?? Panel::MARZBAN;
        
        // Получаем панели с исключением привязанных к продавцам (как в оригинальном методе)
        // Оптимизация: добавляем eager loading для сервера
        $panels = $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', $panelType)
            ->where('has_error', false) // Исключаем панели с ошибками
            ->whereNotIn('id', function ($query) {
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->with('server.location')
            ->get();

        // Исключаем панели по различным критериям
        $excludedLocations = array_map('intval', array_filter(config('panel.excluded_locations', [])));
        $excludedServerIPs = array_filter(config('panel.excluded_server_ips', []));
        $excludedServerIDs = array_map('intval', array_filter(config('panel.excluded_server_ids', [])));
        
        if (!empty($excludedLocations) || !empty($excludedServerIPs) || !empty($excludedServerIDs)) {
            $panels = $panels->filter(function ($panel) use ($excludedLocations, $excludedServerIPs, $excludedServerIDs) {
                // Проверяем локацию
                if (!empty($excludedLocations) && $panel->server && $panel->server->location_id) {
                    if (in_array($panel->server->location_id, $excludedLocations)) {
                        return false;
                    }
                }
                
                // Проверяем IP-адрес сервера
                if (!empty($excludedServerIPs) && $panel->server && $panel->server->ip) {
                    $serverIP = is_numeric($panel->server->ip) ? long2ip($panel->server->ip) : $panel->server->ip;
                    if (in_array($serverIP, $excludedServerIPs)) {
                        return false;
                    }
                }
                
                // Проверяем ID сервера
                if (!empty($excludedServerIDs) && $panel->server && $panel->server->id) {
                    if (in_array($panel->server->id, $excludedServerIDs)) {
                        return false;
                    }
                }
                
                return true;
            });
            
            if ($panels->isEmpty()) {
                Log::warning('PANEL_SELECTION: No available panels after filtering', [
                    'excluded_locations' => $excludedLocations,
                    'excluded_server_ips' => $excludedServerIPs,
                    'excluded_server_ids' => $excludedServerIDs,
                    'source' => 'panel'
                ]);
                return null;
            }
            
            Log::info('PANEL_SELECTION: Filtered panels by exclusion rules', [
                'excluded_locations' => $excludedLocations,
                'excluded_server_ips' => $excludedServerIPs,
                'excluded_server_ids' => $excludedServerIDs,
                'panels_after_filter' => $panels->count(),
                'source' => 'panel'
            ]);
        }

        if ($panels->isEmpty()) {
            Log::warning('PANEL_SELECTION: No available panels after filtering', ['source' => 'panel']);
            return null;
        }

        // Получаем стратегию выбора из конфига
        $strategy = config('panel.selection_strategy', 'intelligent');
        
        // Используем выбранную стратегию даже в режиме useCacheOnly
        // Методы selectPanelByTraffic и selectOptimalPanelIntelligent уже поддерживают useCacheOnly
        // и будут использовать кэшированные данные для быстрой работы
        if ($useCacheOnly) {
            Log::info('PANEL_SELECTION: Using selected strategy with cache-only mode for fast key activation', [
                'strategy' => $strategy,
                'source' => 'panel'
            ]);
        }

        // Уменьшено время кэширования до 15 секунд для лучшей актуальности
        $cacheKey = "optimized_marzban_panel_{$strategy}";
        return Cache::remember($cacheKey, 15, function () use ($panels, $strategy, $useCacheOnly) {
            return $this->selectPanelByStrategy($panels, $strategy, $useCacheOnly);
        });
    }

    /**
     * Выбор панели в зависимости от стратегии
     * 
     * @param Collection $panels
     * @param string $strategy
     * @param bool $useCacheOnly Если true, использует только кэшированные данные (не делает запросы к API)
     * @return Panel|null
     */
    private function selectPanelByStrategy(Collection $panels, string $strategy, bool $useCacheOnly = false): ?Panel
    {
        switch ($strategy) {
            case 'balanced':
                // Старая система - равномерное распределение (быстро, не требует API)
                return $this->getConfiguredMarzbanPanel();
                
            case 'traffic_based':
                // Новая система - на основе трафика сервера
                return $this->selectPanelByTraffic($panels, $useCacheOnly);
                
            case 'intelligent':
            default:
                // Интеллектуальная система - комплексный анализ
                $panel = $this->selectOptimalPanelIntelligent($panels, $useCacheOnly);
                
                // Используем выбранную панель даже если статистика устарела
                // Метод selectOptimalPanelIntelligent уже использует fallback на данные из БД
                // если статистика недоступна, поэтому не нужно дополнительно переключаться на balanced
                if ($panel) {
                    $hasFreshStats = $this->isStatsFresh($panel->id);
                    if (!$hasFreshStats) {
                        Log::info('PANEL_SELECTION [INTELLIGENT]: Using panel with stale/DB stats (selected strategy preserved)', [
                            'source' => 'panel',
                            'panel_id' => $panel->id
                        ]);
                    }
                    return $panel;
                }
                
                // Fallback на balanced только если вообще не удалось выбрать панель
                Log::warning('PANEL_SELECTION [INTELLIGENT]: No panel selected, falling back to balanced', [
                    'source' => 'panel'
                ]);
                return $this->getConfiguredMarzbanPanel();
        }
    }

    /**
     * Сравнение старой и новой системы выбора
     * @return array
     */
    public function comparePanelSelection(): array
    {
        $panels = $this->getAllConfiguredPanels();

        if ($panels->isEmpty()) {
            return ['error' => 'No panels available'];
        }

        $oldSelection = $this->getConfiguredMarzbanPanel();
        $newSelection = $this->getOptimizedMarzbanPanel();

        $panelsWithInfo = $panels->map(function ($panel) use ($oldSelection, $newSelection) {
            $totalUsers = $this->getTotalUsersCount($panel->id);
            $activeUsersFromStats = $this->getActiveUsersFromStats($panel->id);

            return [
                'id' => $panel->id,
                'address' => $panel->panel_adress,
                'active_users_db' => $this->getActiveUsersCount($panel->id), // Старый метод (для отладки)
                'active_users_stats' => $activeUsersFromStats, // Новый метод из статистики
                'total_users' => $totalUsers,
                'last_activity' => $this->getLastUserCreationTime($panel->id),
                'server_stats' => $this->getLatestPanelStats($panel->id),
                'optimized_score' => $this->calculatePanelScore($panel, $activeUsersFromStats),
                'is_old_selected' => $oldSelection && $oldSelection->id === $panel->id,
                'is_new_selected' => $newSelection && $newSelection->id === $panel->id,
            ];
        });

        return [
            'old_system_selected' => $oldSelection ? $oldSelection->id : null,
            'new_system_selected' => $newSelection ? $newSelection->id : null,
            'panels' => $panelsWithInfo,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    /**
     * Сравнение всех стратегий выбора панели
     * @return array
     */
    /**
     * Сравнить все стратегии выбора панели
     * 
     * @param string|null $panelType Тип панели (по умолчанию Panel::MARZBAN для обратной совместимости)
     * @return array
     */
    public function compareAllStrategies(?string $panelType = null): array
    {
        // Используем Panel::MARZBAN по умолчанию для обратной совместимости
        $panelType = $panelType ?? Panel::MARZBAN;
        
        try {
            $panels = $this->query()
                ->where('panel_status', Panel::PANEL_CONFIGURED)
                ->where('panel', $panelType)
                ->where('has_error', false) // Исключаем панели с ошибками
                ->whereNotIn('id', function ($query) {
                    $query->select('panel_id')
                        ->from('salesman')
                        ->whereNotNull('panel_id');
                })
                ->with('server.location')
                ->get();

            if ($panels->isEmpty()) {
                return ['error' => 'Нет доступных панелей'];
            }

            // Получаем выбор каждой стратегии
            // Используем кэш для ускорения, но с коротким временем жизни
            $balancedPanel = $this->getConfiguredMarzbanPanel();
            
            // Для traffic_based получаем панели, но с обработкой ошибок rate limit
            $trafficPanel = null;
            try {
                $trafficPanel = $this->selectPanelByTraffic($panels);
            } catch (\Exception $e) {
                Log::warning('Failed to select panel by traffic in comparison', [
                    'error' => $e->getMessage(),
                    'source' => 'panel'
                ]);
                // Fallback на balanced при ошибке
                $trafficPanel = $balancedPanel;
            }
            
            // Для intelligent также получаем с обработкой ошибок
            $intelligentPanel = null;
            try {
                $intelligentPanel = $this->selectOptimalPanelIntelligent($panels);
            } catch (\Exception $e) {
                Log::warning('Failed to select panel intelligently in comparison', [
                    'error' => $e->getMessage(),
                    'source' => 'panel'
                ]);
                // Fallback на balanced при ошибке
                $intelligentPanel = $balancedPanel;
            }

            // Собираем детальную информацию по каждой панели
            // Оборачиваем в try-catch для каждой панели, чтобы ошибки не блокировали всю страницу
            $panelsInfo = $panels->map(function ($panel) use ($balancedPanel, $trafficPanel, $intelligentPanel) {
                try {
                    $totalUsers = $this->getTotalUsersCount($panel->id);
                    $activeUsers = $this->getActiveUsersFromStats($panel->id);
                    $latestStats = $this->getLatestPanelStats($panel->id);
                    
                    // Получаем данные о трафике с обработкой ошибок
                    $trafficData = null;
                    try {
                        $trafficData = $this->getServerTrafficData($panel);
                    } catch (\Exception $e) {
                        // Логируем ошибку, но продолжаем работу
                        Log::warning('Failed to get traffic data for panel in comparison', [
                            'panel_id' => $panel->id,
                            'error' => $e->getMessage(),
                            'source' => 'panel'
                        ]);
                    }
                    
                    $cpuUsage = $latestStats['cpu_usage'] ?? 0;
                    $memoryUsed = $latestStats['mem_used'] ?? 0;
                    $memoryTotal = $latestStats['mem_total'] ?? 1;
                    $memoryUsage = ($memoryTotal > 0) ? ($memoryUsed / $memoryTotal) * 100 : 0;

                    // Score для интеллектуальной системы
                    $intelligentScore = $this->calculatePanelScore($panel, $activeUsers, $latestStats);

                    return [
                        'id' => $panel->id,
                        'address' => $panel->panel_adress,
                        'server_name' => $panel->server->name ?? 'N/A',
                        'server_id' => $panel->server_id,
                        'total_users' => $totalUsers,
                        'active_users' => $activeUsers,
                        'cpu_usage' => round($cpuUsage, 1),
                        'memory_usage' => round($memoryUsage, 1),
                        'traffic_used_percent' => $trafficData['used_percent'] ?? null,
                        'traffic_used_gb' => $trafficData ? round($trafficData['used'] / (1024 * 1024 * 1024), 2) : null,
                        'traffic_limit_gb' => $trafficData ? round($trafficData['limit'] / (1024 * 1024 * 1024), 2) : null,
                        'traffic_remaining_percent' => $trafficData['remaining_percent'] ?? null,
                        'intelligent_score' => round($intelligentScore, 1),
                        'last_activity' => $this->getLastUserCreationTime($panel->id),
                        'is_balanced_selected' => $balancedPanel && $balancedPanel->id === $panel->id,
                        'is_traffic_selected' => $trafficPanel && $trafficPanel->id === $panel->id,
                        'is_intelligent_selected' => $intelligentPanel && $intelligentPanel->id === $panel->id,
                        'has_fresh_stats' => $this->isStatsFresh($panel->id),
                        'has_traffic_data' => $trafficData !== null,
                    ];
                } catch (\Exception $e) {
                    // Если произошла ошибка при обработке панели, возвращаем минимальные данные
                    Log::warning('Error processing panel in comparison', [
                        'panel_id' => $panel->id,
                        'error' => $e->getMessage(),
                        'source' => 'panel'
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
                        'is_balanced_selected' => false,
                        'is_traffic_selected' => false,
                        'is_intelligent_selected' => false,
                        'has_fresh_stats' => false,
                        'has_traffic_data' => false,
                    ];
                }
            });

            // Статистика по стратегиям
            $strategyStats = [
                'balanced' => [
                    'selected_panel_id' => $balancedPanel ? $balancedPanel->id : null,
                    'selected_panel_info' => $balancedPanel ? $panelsInfo->firstWhere('id', $balancedPanel->id) : null,
                ],
                'traffic_based' => [
                    'selected_panel_id' => $trafficPanel ? $trafficPanel->id : null,
                    'selected_panel_info' => $trafficPanel ? $panelsInfo->firstWhere('id', $trafficPanel->id) : null,
                ],
                'intelligent' => [
                    'selected_panel_id' => $intelligentPanel ? $intelligentPanel->id : null,
                    'selected_panel_info' => $intelligentPanel ? $panelsInfo->firstWhere('id', $intelligentPanel->id) : null,
                ],
            ];

            return [
                'strategies' => $strategyStats,
                'panels' => $panelsInfo->values(),
                'summary' => [
                    'total_panels' => $panels->count(),
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
     * Интеллектуальный выбор панели
     * 
     * @param Collection $panels
     * @param bool $useCacheOnly Если true, использует только кэшированные данные (не делает запросы к API)
     * @return Panel|null
     */
    private function selectOptimalPanelIntelligent(Collection $panels, bool $useCacheOnly = false): ?Panel
    {
        $scoredPanels = $panels->map(function ($panel) {
            // Проверяем наличие актуальной статистики
            $latestStats = $this->getLatestPanelStats($panel->id);
            
            // Если статистики нет или она устарела, используем данные из БД
            if (!$latestStats || !$this->isStatsFresh($panel->id)) {
                // Fallback: используем количество пользователей из БД
                $activeUsers = $this->getActiveUsersCount($panel->id);
            } else {
                $activeUsers = $this->getActiveUsersFromStats($panel->id);
            }
            
            $score = $this->calculatePanelScore($panel, $activeUsers, $latestStats);

            return [
                'panel' => $panel,
                'score' => $score,
                'active_users' => $activeUsers,
                'has_fresh_stats' => $latestStats && $this->isStatsFresh($panel->id),
                'details' => $this->getPanelSelectionDetails($panel, $activeUsers, $score)
            ];
        })->filter(function ($item) {
            // Фильтруем панели с валидным score
            return $item['score'] >= 0;
        });

        if ($scoredPanels->isEmpty()) {
            Log::warning('PANEL_SELECTION [NEW]: No panels with valid scores', ['source' => 'panel']);
            return null;
        }

        $selectedPanelData = $scoredPanels->sortByDesc('score')->first();
        $selectedPanel = $selectedPanelData['panel'];

        // Логируем детали выбора
        $this->logPanelSelection($selectedPanelData);

        return $selectedPanel;
    }

    /**
     * Логирование выбора панели
     */
    private function logPanelSelection(array $selectedPanelData): void
    {
        $panel = $selectedPanelData['panel'];
        $score = $selectedPanelData['score'];
        $activeUsers = $selectedPanelData['active_users'];
        $hasFreshStats = $selectedPanelData['has_fresh_stats'] ?? false;
        $details = $selectedPanelData['details'];

        $statsStatus = $hasFreshStats ? 'FRESH' : 'STALE/DB';
        $logMessage = sprintf(
            "PANEL_SELECTION [NEW]: Selected Panel ID: %d | Score: %.1f | Active Users: %d | Stats: %s | CPU: %.1f%% | Memory: %.1f%% | Last Activity: %s",
            $panel->id,
            $score,
            $activeUsers,
            $statsStatus,
            $details['cpu_usage'],
            $details['memory_usage'],
            $details['last_activity_human']
        );

        Log::info($logMessage, [
            'panel_id' => $panel->id,
            'panel_address' => $panel->panel_adress,
            'source' => 'panel',
            'score' => $score,
            'active_users' => $activeUsers,
            'total_users' => $this->getTotalUsersCount($panel->id),
            'has_fresh_stats' => $hasFreshStats,
            'cpu_usage' => $details['cpu_usage'],
            'memory_usage' => $details['memory_usage'],
            'last_activity' => $details['last_activity'],
            'selection_reason' => $details['selection_reason']
        ]);
    }

    /**
     * Получение деталей для логирования
     */
    private function getPanelSelectionDetails(Panel $panel, int $activeUsers, float $score): array
    {
        $latestStats = $this->getLatestPanelStats($panel->id);
        $lastActivity = $this->getLastUserCreationTime($panel->id);

        $cpuUsage = $latestStats['cpu_usage'] ?? 0;
        $memoryUsed = $latestStats['mem_used'] ?? 0;
        $memoryTotal = $latestStats['mem_total'] ?? 1;
        $memoryUsage = ($memoryTotal > 0) ? ($memoryUsed / $memoryTotal) * 100 : 0;

        // Определяем причину выбора
        $selectionReason = $this->getSelectionReason($activeUsers, $cpuUsage, $memoryUsage, $lastActivity);

        return [
            'cpu_usage' => round($cpuUsage, 1),
            'memory_usage' => round($memoryUsage, 1),
            'last_activity' => $lastActivity,
            'last_activity_human' => $lastActivity ? $lastActivity->diffForHumans() : 'never',
            'selection_reason' => $selectionReason
        ];
    }

    /**
     * Определение причины выбора панели
     */
    private function getSelectionReason(int $activeUsers, float $cpuUsage, float $memoryUsage, ?Carbon $lastActivity): string
    {
        $reasons = [];

        if ($activeUsers < 480) {
            $reasons[] = 'low users';
        }

        if ($cpuUsage < 40) {
            $reasons[] = 'low cpu';
        }

        if ($memoryUsage < 50) {
            $reasons[] = 'low memory';
        }

        if ($lastActivity && $lastActivity->diffInMinutes(now()) > 30) {
            $reasons[] = 'inactive period';
        }

        if (empty($reasons)) {
            return 'balanced load';
        }

        return implode(', ', $reasons);
    }

    /**
     * Расчет комплексного score для панели
     */
    private function calculatePanelScore(Panel $panel, int $activeUsers, ?array $latestStats = null): float
    {
        $score = 100.0;

        // 1. Score на основе количества активных пользователей (50% веса)
        $userScore = $this->calculateUserBasedScore($activeUsers);
        $score += $userScore * 50;

        // 2. Score на основе нагрузки сервера (40% веса)
        // Используем переданную статистику или получаем заново
        $loadScore = $this->calculateLoadBasedScore($panel, $latestStats);
        $score += $loadScore * 40;

        // 3. Score на основе времени последней активности (5% веса - уменьшено с 10%)
        $timeScore = $this->calculateTimeBasedScore($panel);
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
        $lastUserTime = $this->getLastUserCreationTime($panel->id);

        if (!$lastUserTime) {
            return 1.0; // Панель никогда не использовалась
        }

        $minutesSinceLast = Carbon::now()->diffInMinutes($lastUserTime);

        if ($minutesSinceLast < 5) return 0.0;     // Только что использовалась
        if ($minutesSinceLast < 15) return 0.3;    // Недавно
        if ($minutesSinceLast < 30) return 0.6;    // Некоторое время назад
        if ($minutesSinceLast < 60) return 0.8;    // Давно
        return 1.0;                                // Очень давно
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
     * Получение общего количества пользователей панели
     */
    private function getTotalUsersCount(int $panelId): int
    {
        return ServerUser::where('panel_id', $panelId)->count();
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
     * Проверка, есть ли хотя бы одна панель с актуальной статистикой
     * 
     * @param Collection $panels
     * @return bool
     */
    private function hasAnyFreshStats(Collection $panels): bool
    {
        return $panels->contains(function ($panel) {
            return $this->isStatsFresh($panel->id);
        });
    }

    /**
     * Выбор панели на основе нагрузки трафика сервера
     * Выбирает панель на сервере с наименьшим процентом использования трафика
     * 
     * @param Collection $panels
     * @param bool $useCacheOnly Если true, использует только кэшированные данные (не делает запросы к API)
     * @return Panel|null
     */
    private function selectPanelByTraffic(Collection $panels, bool $useCacheOnly = false): ?Panel
    {
        // Проверяем, заблокирован ли API из-за rate limit (403 Blacklisted)
        // Согласно поддержке VDSina, блокировка снимается автоматически через 4 часа
        $rateLimitBlocked = Cache::get('vdsina_api_rate_limit_blocked', false);
        $rateLimitBlockedUntil = Cache::get('vdsina_api_rate_limit_blocked_until', 0);
        
        // Если блокировка активна, используем только кэш (не делаем запросы к API)
        if ($rateLimitBlocked && $rateLimitBlockedUntil > time()) {
            $useCacheOnly = true; // Принудительно используем только кэш
            $remainingTime = $rateLimitBlockedUntil - time();
            $remainingHours = round($remainingTime / 3600, 1);
            
            Log::info('PANEL_SELECTION [TRAFFIC]: API blocked (403 Blacklisted), using cache only mode', [
                'source' => 'panel',
                'blocked_until' => date('Y-m-d H:i:s', $rateLimitBlockedUntil),
                'remaining_hours' => $remainingHours,
                'message' => 'VDSina API will auto-unblock after 4 hours from last problematic request'
            ]);
        }
        
        $panelsWithTraffic = $panels->map(function ($panel) use ($useCacheOnly) {
            // Если режим "только кэш", используем специальный метод, который не делает запросы к API
            $trafficData = $useCacheOnly 
                ? $this->getServerTrafficDataFromCache($panel)
                : $this->getServerTrafficData($panel);
            
            return [
                'panel' => $panel,
                'traffic_used_percent' => $trafficData['used_percent'] ?? null,
                'traffic_remaining_percent' => $trafficData['remaining_percent'] ?? null,
                'traffic_data' => $trafficData,
                'has_traffic_data' => $trafficData !== null
            ];
        });
        
        // Сначала пытаемся найти панели с валидными данными о трафике (не критическая загрузка)
        $validPanels = $panelsWithTraffic->filter(function ($item) {
            return $item['has_traffic_data'] 
                && $item['traffic_used_percent'] !== null 
                && $item['traffic_used_percent'] < 95;
        });
        
        // Если есть панели с валидными данными - выбираем из них
        if ($validPanels->isNotEmpty()) {
            $selectedPanelData = $validPanels->sortBy('traffic_used_percent')->first();
            $selectedPanel = $selectedPanelData['panel'];
            
            Log::info('PANEL_SELECTION [TRAFFIC]: Selected panel based on traffic load', [
                'panel_id' => $selectedPanel->id,
                'traffic_used_percent' => $selectedPanelData['traffic_used_percent'],
                'traffic_remaining_percent' => $selectedPanelData['traffic_remaining_percent'],
                'server_id' => $selectedPanel->server_id,
                'server_name' => $selectedPanel->server->name ?? 'N/A'
            ]);
            
            return $selectedPanel;
        }
        
        // Если нет валидных панелей, но есть панели с данными (даже с высокой загрузкой) - используем их
        $panelsWithAnyData = $panelsWithTraffic->filter(function ($item) {
            return $item['has_traffic_data'] && $item['traffic_used_percent'] !== null;
        });
        
        if ($panelsWithAnyData->isNotEmpty()) {
            $selectedPanelData = $panelsWithAnyData->sortBy('traffic_used_percent')->first();
            $selectedPanel = $selectedPanelData['panel'];
            
            Log::warning('PANEL_SELECTION [TRAFFIC]: Using panel with high traffic load (no better options)', [
                'panel_id' => $selectedPanel->id,
                'traffic_used_percent' => $selectedPanelData['traffic_used_percent'],
                'server_id' => $selectedPanel->server_id,
                'server_name' => $selectedPanel->server->name ?? 'N/A',
                'source' => 'panel'
            ]);
            
            return $selectedPanel;
        }
        
        // Если данных о трафике нет вообще, используем альтернативный метод выбора
        // на основе количества активных пользователей из БД (сохраняем выбранную стратегию)
        Log::warning('PANEL_SELECTION [TRAFFIC]: No traffic data available, using fallback based on active users count', [
            'panels_count' => $panels->count(),
            'rate_limit_blocked' => $rateLimitBlocked,
            'use_cache_only' => $useCacheOnly,
            'source' => 'panel'
        ]);
        
        // Выбираем панель с минимальным количеством активных пользователей
        $panelsWithUsers = $panels->map(function ($panel) {
            return [
                'panel' => $panel,
                'active_users' => $this->getActiveUsersCount($panel->id),
                'total_users' => $this->getTotalUsersCount($panel->id)
            ];
        });
        
        $selectedPanelData = $panelsWithUsers->sortBy('active_users')->first();
        
        if ($selectedPanelData) {
            Log::info('PANEL_SELECTION [TRAFFIC]: Selected panel using fallback (active users count)', [
                'panel_id' => $selectedPanelData['panel']->id,
                'active_users' => $selectedPanelData['active_users'],
                'total_users' => $selectedPanelData['total_users'],
                'source' => 'panel'
            ]);
            return $selectedPanelData['panel'];
        }
        
        // Только в крайнем случае используем balanced
        Log::warning('PANEL_SELECTION [TRAFFIC]: No panels available, falling back to balanced', [
            'source' => 'panel'
        ]);
        return $this->getConfiguredMarzbanPanel();
    }

    /**
     * Получение данных о трафике сервера
     * 
     * @param Panel $panel
     * @return array|null
     */
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
        // Используем Server::VDSINA по умолчанию для обратной совместимости
        $provider = $provider ?? Server::VDSINA;
        
        if (!$panel->server || $panel->server->provider !== $provider) {
            return null;
        }

        if (!$panel->server->provider_id) {
            return null;
        }

        $cacheKey = "server_traffic_{$panel->server->provider_id}";
        
        // Только читаем из кэша, не делаем запросов к API
        return Cache::get($cacheKey);
    }

    /**
     * Получить данные о трафике сервера
     * 
     * @param Panel $panel Панель
     * @param string|null $provider Провайдер сервера (по умолчанию Server::VDSINA для обратной совместимости)
     * @return array|null
     */
    public function getServerTrafficData(Panel $panel, ?string $provider = null): ?array
    {
        // Используем Server::VDSINA по умолчанию для обратной совместимости
        $provider = $provider ?? Server::VDSINA;
        
        if (!$panel->server || $panel->server->provider !== $provider) {
            return null;
        }

        if (!$panel->server->provider_id) {
            return null;
        }

        $cacheKey = "server_traffic_{$panel->server->provider_id}";
        // Увеличиваем время кэширования для уменьшения количества запросов к API
        $cacheTtl = config('panel.traffic_cache_ttl', 1800); // 30 минут вместо 10

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
            if ($trafficData && isset($trafficData['current_month'])) {
                $limitBytes = $trafficData['limit'];
                $currentTrafficBytes = $trafficData['current_month'];
                
                $currentTraffic = [
                    'used_tb' => round($currentTrafficBytes / (1024 * 1024 * 1024 * 1024), 2),
                    'limit_tb' => round($limitBytes / (1024 * 1024 * 1024 * 1024), 2),
                    'used_percent' => $limitBytes > 0 ? round(($currentTrafficBytes / $limitBytes) * 100, 2) : 0,
                ];
            }
            
            // Трафик за прошлый месяц (из API last_month или из сохраненных данных)
            $lastTraffic = null;
            if ($trafficData && isset($trafficData['last_month']) && $trafficData['last_month'] !== null && $trafficData['last_month'] > 0) {
                // Используем данные из API
                $limitBytes = $trafficData['limit'];
                $lastTrafficBytes = $trafficData['last_month'];
                
                $lastTraffic = [
                    'used_tb' => round($lastTrafficBytes / (1024 * 1024 * 1024 * 1024), 2),
                    'limit_tb' => round($limitBytes / (1024 * 1024 * 1024 * 1024), 2),
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
