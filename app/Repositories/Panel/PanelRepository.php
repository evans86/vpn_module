<?php

namespace App\Repositories\Panel;

use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Models\ServerMonitoring\ServerMonitoring;
use App\Models\ServerUser\ServerUser;
use App\Models\KeyActivate\KeyActivate;
use App\Repositories\BaseRepository;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PanelRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return Panel::class;
    }

    // ==================== СУЩЕСТВУЮЩИЕ МЕТОДЫ (без изменений) ====================

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
        return $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', Panel::MARZBAN)
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
            ->whereNotIn('id', function ($query) {
                // Исключаем панели, которые привязаны к продавцам
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->get();

        if ($panels->isEmpty()) {
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
            return $panels->random();
        }

        // Иначе выбираем панель с минимальным количеством ключей
        return $panelsWithKeyCount->sortBy('key_count')->first()['panel'];
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
        $panels = $this->query()
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('panel', Panel::MARZBAN)
            ->whereNotIn('id', function ($query) {
                $query->select('panel_id')
                    ->from('salesman')
                    ->whereNotNull('panel_id');
            })
            ->get();

        if ($panels->isEmpty()) {
            return null;
        }

        // Кэшируем результат на 2 минуты
        return Cache::remember('optimized_marzban_panel', 120, function () use ($panels) {
            return $this->selectOptimalPanelIntelligent($panels);
        });
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
            return [
                'id' => $panel->id,
                'address' => $panel->panel_adress,
                'active_users' => $this->getActiveUsersCount($panel->id),
                'total_users' => $this->getTotalUsersCount($panel->id),
                'last_activity' => $this->getLastUserCreationTime($panel->id),
                'server_stats' => $this->getLatestPanelStats($panel->id),
                'optimized_score' => $this->calculatePanelScore($panel),
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

    // ==================== ПРИВАТНЫЕ МЕТОДЫ НОВОЙ СИСТЕМЫ ====================

    /**
     * Интеллектуальный выбор панели
     */
    private function selectOptimalPanelIntelligent(Collection $panels): Panel
    {
        $scoredPanels = $panels->map(function ($panel) {
            return [
                'panel' => $panel,
                'score' => $this->calculatePanelScore($panel)
            ];
        });

        return $scoredPanels->sortByDesc('score')->first()['panel'];
    }

    /**
     * Расчет комплексного score для панели
     */
    private function calculatePanelScore(Panel $panel): float
    {
        $score = 100.0;

        // 1. Количество активных пользователей (35% веса)
        $userScore = $this->calculateUserBasedScore($panel);
        $score += $userScore * 35;

        // 2. Нагрузка сервера из мониторинга (35% веса)
        $loadScore = $this->calculateLoadBasedScore($panel);
        $score += $loadScore * 35;

        // 3. Время с последней активности (20% веса)
        $timeScore = $this->calculateTimeBasedScore($panel);
        $score += $timeScore * 20;

        // 4. Случайность для распределения (10% веса)
        $randomScore = $this->calculateRandomScore($panel);
        $score += $randomScore * 10;

        return max($score, 0);
    }

    /**
     * Score на основе количества пользователей
     */
    private function calculateUserBasedScore(Panel $panel): float
    {
        $activeUsersCount = $this->getActiveUsersCount($panel->id);

        // Шкала: чем меньше пользователей, тем выше score
        if ($activeUsersCount < 10) return 1.0;
        if ($activeUsersCount < 30) return 0.8;
        if ($activeUsersCount < 50) return 0.6;
        if ($activeUsersCount < 100) return 0.4;
        if ($activeUsersCount < 200) return 0.2;
        return 0.0;
    }

    /**
     * Score на основе нагрузки сервера
     */
    private function calculateLoadBasedScore(Panel $panel): float
    {
        $latestStats = $this->getLatestPanelStats($panel->id);

        if (!$latestStats) {
            return 0.5;
        }

        $cpuUsage = $latestStats['cpu_usage'] ?? 100;
        $memoryUsed = $latestStats['mem_used'] ?? 0;
        $memoryTotal = $latestStats['mem_total'] ?? 1;
        $memoryUsage = ($memoryTotal > 0) ? ($memoryUsed / $memoryTotal) * 100 : 100;

        $combinedLoad = max($cpuUsage, $memoryUsage);

        if ($combinedLoad < 30) return 1.0;
        if ($combinedLoad < 50) return 0.8;
        if ($combinedLoad < 70) return 0.5;
        if ($combinedLoad < 85) return 0.2;
        return 0.0;
    }

    /**
     * Score на основе времени последней активности
     */
    private function calculateTimeBasedScore(Panel $panel): float
    {
        $lastUserTime = $this->getLastUserCreationTime($panel->id);

        if (!$lastUserTime) {
            return 1.0;
        }

        $hoursSinceLast = Carbon::now()->diffInHours($lastUserTime);

        if ($hoursSinceLast < 1) return 0.0;
        if ($hoursSinceLast < 3) return 0.3;
        if ($hoursSinceLast < 6) return 0.6;
        if ($hoursSinceLast < 12) return 0.8;
        return 1.0;
    }

    /**
     * Случайный компонент для равномерного распределения
     */
    private function calculateRandomScore(Panel $panel): float
    {
        $hash = crc32($panel->id . date('Y-m-d-H'));
        return (($hash % 100) / 100) - 0.5;
    }

    /**
     * Получение количества активных пользователей панели
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
}
