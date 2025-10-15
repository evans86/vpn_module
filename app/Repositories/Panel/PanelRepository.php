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
            $scoreDetails = $this->calculatePanelScoreDetailed($panel);

            return [
                'id' => $panel->id,
                'address' => $panel->panel_adress,
                'active_users' => $this->getActiveUsersCount($panel->id),
                'total_users' => $this->getTotalUsersCount($panel->id),
                'last_activity' => $this->getLastUserCreationTime($panel->id),
                'server_stats' => $this->getLatestPanelStats($panel->id),
                'optimized_score' => $scoreDetails['total'],
                'score_details' => $scoreDetails,
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
            $scoreDetails = $this->calculatePanelScoreDetailed($panel);
            return [
                'panel' => $panel,
                'score' => $scoreDetails['total']
            ];
        });

        return $scoredPanels->sortByDesc('score')->first()['panel'];
    }

    /**
     * Расчет комплексного score для панели с детализацией
     */
    private function calculatePanelScoreDetailed(Panel $panel): array
    {
        $activeUsers = $this->getActiveUsersCount($panel->id);
        $latestStats = $this->getLatestPanelStats($panel->id);
        $lastActivity = $this->getLastUserCreationTime($panel->id);

        // 1. Score на основе количества активных пользователей (40% веса)
        $userScore = $this->calculateUserBasedScore($activeUsers);
        $userContribution = $userScore * 40;

        // 2. Score на основе нагрузки сервера (40% веса)
        $loadScore = $this->calculateLoadBasedScore($latestStats);
        $loadContribution = $loadScore * 40;

        // 3. Score на основе времени последней активности (15% веса)
        $timeScore = $this->calculateTimeBasedScore($lastActivity);
        $timeContribution = $timeScore * 15;

        // 4. Случайность для распределения (5% веса)
        $randomScore = $this->calculateRandomScore($panel);
        $randomContribution = $randomScore * 5;

        $totalScore = $userContribution + $loadContribution + $timeContribution + $randomContribution;

        return [
            'total' => $totalScore,
            'user_score' => $userScore,
            'user_contribution' => $userContribution,
            'load_score' => $loadScore,
            'load_contribution' => $loadContribution,
            'time_score' => $timeScore,
            'time_contribution' => $timeContribution,
            'random_score' => $randomScore,
            'random_contribution' => $randomContribution,
        ];
    }

    /**
     * Score на основе количества пользователей (адаптировано под большие числа)
     */
    private function calculateUserBasedScore(int $activeUsersCount): float
    {
        // Адаптивная шкала для больших объемов пользователей
        // Чем меньше пользователей относительно других панелей, тем выше score

        if ($activeUsersCount < 100) return 1.0;      // Очень мало
        if ($activeUsersCount < 500) return 0.8;      // Мало
        if ($activeUsersCount < 2000) return 0.6;     // Средне
        if ($activeUsersCount < 3000) return 0.4;     // Много
        if ($activeUsersCount < 4000) return 0.2;     // Очень много
        return 0.0;                                   // Перегружена
    }

    /**
     * Score на основе нагрузки сервера
     */
    private function calculateLoadBasedScore(?array $latestStats): float
    {
        if (!$latestStats) {
            return 0.5;
        }

        $cpuUsage = $latestStats['cpu_usage'] ?? 100;
        $memoryUsed = $latestStats['mem_used'] ?? 0;
        $memoryTotal = $latestStats['mem_total'] ?? 1;
        $memoryUsage = ($memoryTotal > 0) ? ($memoryUsed / $memoryTotal) * 100 : 100;

        // Берем максимальную нагрузку из CPU и памяти
        $combinedLoad = max($cpuUsage, $memoryUsage);

        if ($combinedLoad < 20) return 1.0;    // Отлично
        if ($combinedLoad < 40) return 0.8;    // Хорошо
        if ($combinedLoad < 60) return 0.6;    // Нормально
        if ($combinedLoad < 80) return 0.4;    // Повышенная
        if ($combinedLoad < 90) return 0.2;    // Высокая
        return 0.0;                            // Критическая
    }

    /**
     * Score на основе времени последней активности
     */
    private function calculateTimeBasedScore(?Carbon $lastUserTime): float
    {
        if (!$lastUserTime) {
            return 1.0; // Панель никогда не использовалась
        }

        $minutesSinceLast = Carbon::now()->diffInMinutes($lastUserTime);

        // Для больших систем время между созданиями пользователей маленькое
        if ($minutesSinceLast < 5) return 0.0;     // Только что использовалась
        if ($minutesSinceLast < 15) return 0.3;    // Недавно
        if ($minutesSinceLast < 30) return 0.6;    // Некоторое время назад
        if ($minutesSinceLast < 60) return 0.8;    // Давно
        return 1.0;                                // Очень давно
    }

    /**
     * Случайный компонент для равномерного распределения
     */
    private function calculateRandomScore(Panel $panel): float
    {
        $hash = crc32($panel->id . date('Y-m-d-H'));
        return (($hash % 100) / 100) - 0.5; // От -0.5 до +0.5
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
