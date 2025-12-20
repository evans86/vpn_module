<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Repositories\Panel\PanelRepository;
use App\Services\External\MarzbanAPI;
use App\Services\Panel\PanelStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckPanelsWithErrors extends Command
{
    protected $signature = 'panels:check-errors';
    protected $description = 'Проверка панелей с ошибками и автоматическое восстановление';

    protected PanelRepository $panelRepository;

    public function __construct(PanelRepository $panelRepository)
    {
        parent::__construct();
        $this->panelRepository = $panelRepository;
    }

    public function handle(): int
    {
        $this->info('Начинаем проверку панелей с ошибками...');

        $panelsWithErrors = $this->panelRepository->getPanelsWithErrors();
        
        if ($panelsWithErrors->isEmpty()) {
            $this->info('Панелей с ошибками не найдено.');
            return 0;
        }

        $this->info("Найдено панелей с ошибками: {$panelsWithErrors->count()}");

        $restored = 0;
        $stillBroken = 0;

        foreach ($panelsWithErrors as $panel) {
            $this->line("Проверяем панель ID-{$panel->id} ({$panel->panel_adress})...");

            try {
                // Пробуем создать тестового пользователя для проверки работоспособности
                if ($this->testPanelHealth($panel)) {
                    // Панель работает, возвращаем в ротацию
                    $this->panelRepository->clearPanelError(
                        $panel->id,
                        'automatic',
                        'Панель проверена автоматической системой и работает корректно'
                    );

                    $this->info("✓ Панель ID-{$panel->id} восстановлена и возвращена в ротацию");
                    $restored++;
                } else {
                    $this->warn("✗ Панель ID-{$panel->id} все еще имеет проблемы");
                    $stillBroken++;
                }
            } catch (\Exception $e) {
                $this->error("Ошибка при проверке панели ID-{$panel->id}: {$e->getMessage()}");
                $stillBroken++;
                
                Log::error('Error checking panel health', [
                    'source' => 'cron',
                    'panel_id' => $panel->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\nРезультаты проверки:");
        $this->info("Восстановлено панелей: {$restored}");
        $this->info("Все еще с ошибками: {$stillBroken}");

        return 0;
    }

    /**
     * Проверка работоспособности панели
     * Пытается создать тестового пользователя и сразу удалить его
     * 
     * @param Panel $panel
     * @return bool
     */
    private function testPanelHealth(Panel $panel): bool
    {
        try {
            // Используем стратегию для работы с панелью
            $panelStrategyFactory = new \App\Services\Panel\PanelStrategyFactory();
            $panelStrategy = $panelStrategyFactory->create($panel->panel);
            
            // Обновляем токен через стратегию
            $panel = $panelStrategy->updateToken($panel->id);

            if (!$panel->auth_token) {
                return false;
            }

            // Для проверки здоровья создаем тестового пользователя через стратегию
            // ВАЖНО: Это специфичная логика для проверки, но используем стратегию
            $testUserId = 'test-' . Str::uuid();
            
            try {
                // Создаем тестового пользователя через стратегию
                $testUser = $panelStrategy->addServerUser(
                    $panel->id,
                    0, // userTgId не важен для теста
                    100 * 1024 * 1024, // 100 MB лимит
                    time() + 60, // Истекает через минуту
                    'test-key-' . $testUserId, // Временный key_activate_id
                    ['proxies' => ['vless'], 'inbounds' => []] // Минимальные опции
                );

                if (!$testUser) {
                    return false;
                }

                // Если создание успешно, удаляем тестового пользователя
                try {
                    $panelStrategy->deleteServerUser($panel->id, $testUserId);
                } catch (\Exception $e) {
                    // Игнорируем ошибки удаления тестового пользователя
                    Log::warning('Failed to delete test user', [
                        'source' => 'cron',
                        'panel_id' => $panel->id,
                        'test_user_id' => $testUserId,
                        'error' => $e->getMessage(),
                    ]);
                }

                return true;
            } catch (\Exception $e) {
                Log::warning('Failed to create test user for health check', [
                    'source' => 'cron',
                    'panel_id' => $panel->id,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Panel health check failed', [
                'source' => 'cron',
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
