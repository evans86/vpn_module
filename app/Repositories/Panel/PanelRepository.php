<?php

namespace App\Repositories\Panel;

use App\Models\Panel\Panel;
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
     * @return Collection
     */
    public function getAllConfiguredPanels(): Collection
    {
        // Получаем все настроенные панели Marzban
        // Оптимизация: добавляем eager loading для сервера
        return $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', Panel::MARZBAN)
            ->where('id', '!=', 26) // ВРЕМЕННО ИСКЛЮЧАЕМ ПАНЕЛЬ С ID 26
            ->with('server.location')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * @return Panel|null
     */
    public function getConfiguredMarzbanPanel(): ?Panel
    {
        // Получаем все настроенные панели Marzban
        $panels = $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', Panel::MARZBAN)
            ->where('id', '!=', 26) // ВРЕМЕННО ИСКЛЮЧАЕМ ПАНЕЛЬ С ID 26
            ->whereNotIn('id', function ($query) {
                // Исключаем панели, которые привязаны к продавцам
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->with('server.location')
            ->get();

        if ($panels->isEmpty()) {
            Log::info('PANEL_SELECTION: No configured panels available');
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
            Log::info('PANEL_SELECTION [OLD]: Random selection - Panel ID: ' . $selectedPanel->id . ' (no keys on any panel)');
            return $selectedPanel;
        }

        // Иначе выбираем панель с минимальным количеством ключей
        $selectedPanel = $panelsWithKeyCount->sortBy('key_count')->first()['panel'];

        Log::info('PANEL_SELECTION [OLD]: Least loaded panel - Panel ID: ' . $selectedPanel->id .
            ', Keys: ' . $panelsWithKeyCount->sortBy('key_count')->first()['key_count']);

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
    public function getOptimizedMarzbanPanel(): ?Panel
    {
        // Получаем панели с исключением привязанных к продавцам (как в оригинальном методе)
        // Оптимизация: добавляем eager loading для сервера
        $panels = $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', Panel::MARZBAN)
            ->where('id', '!=', 26) // ВРЕМЕННО ИСКЛЮЧАЕМ ПАНЕЛЬ С ID 26
            ->whereNotIn('id', function ($query) {
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->with('server.location')
            ->get();

        if ($panels->isEmpty()) {
            Log::warning('PANEL_SELECTION: No available panels after filtering');
            return null;
        }

        // Получаем стратегию выбора из конфига
        $strategy = config('panel.selection_strategy', 'intelligent');

        // Уменьшено время кэширования до 15 секунд для лучшей актуальности
        $cacheKey = "optimized_marzban_panel_{$strategy}";
        return Cache::remember($cacheKey, 15, function () use ($panels, $strategy) {
            return $this->selectPanelByStrategy($panels, $strategy);
        });
    }

    /**
     * Выбор панели в зависимости от стратегии
     */
    private function selectPanelByStrategy(Collection $panels, string $strategy): ?Panel
    {
        switch ($strategy) {
            case 'balanced':
                // Старая система - равномерное распределение
                return $this->getConfiguredMarzbanPanel();
                
            case 'traffic_based':
                // Новая система - на основе трафика сервера
                return $this->selectPanelByTraffic($panels);
                
            case 'intelligent':
            default:
                // Интеллектуальная система - комплексный анализ
                $panel = $this->selectOptimalPanelIntelligent($panels);
                
                // Если статистика устарела или отсутствует - используем fallback
                if ($panel && !$this->isStatsFresh($panel->id)) {
                    Log::warning('PANEL_SELECTION [INTELLIGENT]: Statistics outdated, falling back to balanced', [
                        'panel_id' => $panel->id
                    ]);
                    return $this->getConfiguredMarzbanPanel();
                }
                
                return $panel;
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
    public function compareAllStrategies(): array
    {
        try {
            $panels = $this->query()
                ->where('panel_status', Panel::PANEL_CONFIGURED)
                ->where('panel', Panel::MARZBAN)
                ->where('id', '!=', 26) // ВРЕМЕННО ИСКЛЮЧАЕМ ПАНЕЛЬ С ID 26
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

            // Получаем выбор каждой стратегии (без кэширования для актуальности)
            $balancedPanel = $this->getConfiguredMarzbanPanel();
            
            // Для traffic_based получаем панели напрямую, минуя кэш
            $trafficPanel = $this->selectPanelByTraffic($panels);
            
            // Для intelligent также получаем напрямую
            $intelligentPanel = $this->selectOptimalPanelIntelligent($panels);

            // Собираем детальную информацию по каждой панели
            $panelsInfo = $panels->map(function ($panel) use ($balancedPanel, $trafficPanel, $intelligentPanel) {
            $totalUsers = $this->getTotalUsersCount($panel->id);
            $activeUsers = $this->getActiveUsersFromStats($panel->id);
            $latestStats = $this->getLatestPanelStats($panel->id);
            $trafficData = $this->getServerTrafficData($panel);
            
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
                'trace' => $e->getTraceAsString()
            ]);
            return ['error' => 'Ошибка при получении данных: ' . $e->getMessage()];
        }
    }

    // ==================== ПРИВАТНЫЕ МЕТОДЫ НОВОЙ СИСТЕМЫ ====================

    /**
     * Интеллектуальный выбор панели
     */
    private function selectOptimalPanelIntelligent(Collection $panels): ?Panel
    {
        $scoredPanels = $panels->map(function ($panel) {
            // Проверяем наличие актуальной статистики
            $latestStats = $this->getLatestPanelStats($panel->id);
            
            // Если статистики нет или она устарела, используем данные из БД
            if (!$latestStats || !$this->isStatsFresh($panel->id)) {
                // Fallback: используем количество пользователей из БД
                $activeUsers = $this->getActiveUsersCount($panel->id);
                Log::debug('PANEL_SELECTION: Using DB data for panel', [
                    'panel_id' => $panel->id,
                    'active_users' => $activeUsers
                ]);
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
            Log::warning('PANEL_SELECTION [NEW]: No panels with valid scores');
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
     * @return Panel|null
     */
    private function selectPanelByTraffic(Collection $panels): ?Panel
    {
        $panelsWithTraffic = $panels->map(function ($panel) {
            $trafficData = $this->getServerTrafficData($panel);
            
            return [
                'panel' => $panel,
                'traffic_used_percent' => $trafficData['used_percent'] ?? 100,
                'traffic_remaining_percent' => $trafficData['remaining_percent'] ?? 0,
                'traffic_data' => $trafficData
            ];
        })->filter(function ($item) {
            // Исключаем панели без данных о трафике или с критической загрузкой (>95%)
            return isset($item['traffic_data']) && $item['traffic_used_percent'] < 95;
        });

        if ($panelsWithTraffic->isEmpty()) {
            Log::warning('PANEL_SELECTION [TRAFFIC]: No panels with valid traffic data, falling back to balanced');
            return $this->getConfiguredMarzbanPanel();
        }

        // Выбираем панель с наименьшим процентом использования трафика
        $selectedPanelData = $panelsWithTraffic->sortBy('traffic_used_percent')->first();
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

    /**
     * Получение данных о трафике сервера
     * 
     * @param Panel $panel
     * @return array|null
     */
    private function getServerTrafficData(Panel $panel): ?array
    {
        if (!$panel->server || $panel->server->provider !== Server::VDSINA) {
            return null;
        }

        if (!$panel->server->provider_id) {
            return null;
        }

        $cacheKey = "server_traffic_{$panel->server->provider_id}";
        $cacheTtl = config('panel.traffic_cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($panel) {
            try {
                $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
                $trafficData = $vdsinaApi->getServerTraffic((int)$panel->server->provider_id);

                if ($trafficData) {
                    Log::debug('PANEL_SELECTION [TRAFFIC]: Retrieved traffic data from VDSINA', [
                        'server_id' => $panel->server->id,
                        'provider_id' => $panel->server->provider_id,
                        'used_percent' => $trafficData['used_percent']
                    ]);
                }

                return $trafficData;
            } catch (\Exception $e) {
                Log::error('PANEL_SELECTION [TRAFFIC]: Failed to get traffic data', [
                    'server_id' => $panel->server->id,
                    'provider_id' => $panel->server->provider_id,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
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
            
            // Трафик за прошлый месяц (из API last_month)
            $lastTraffic = null;
            if ($trafficData && isset($trafficData['last_month'])) {
                $limitBytes = $trafficData['limit'];
                $lastTrafficBytes = $trafficData['last_month'];
                
                $lastTraffic = [
                    'used_tb' => round($lastTrafficBytes / (1024 * 1024 * 1024 * 1024), 2),
                    'limit_tb' => round($limitBytes / (1024 * 1024 * 1024 * 1024), 2),
                    'used_percent' => $limitBytes > 0 ? round(($lastTrafficBytes / $limitBytes) * 100, 2) : 0,
                ];
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
    private function getPanelStatsForPeriod(Panel $panel, Carbon $startDate, Carbon $endDate): array
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
}
