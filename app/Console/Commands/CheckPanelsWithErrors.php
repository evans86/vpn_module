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
            // Обновляем токен панели
            $marzbanService = app(\App\Services\Panel\marzban\MarzbanService::class);
            $panel = $marzbanService->updateMarzbanToken($panel->id);

            if (!$panel->auth_token) {
                return false;
            }

            $marzbanApi = new MarzbanAPI($panel->api_address);
            $testUserId = 'test-' . Str::uuid();
            
            // Пробуем создать тестового пользователя с минимальными параметрами
            $userData = $marzbanApi->createUser(
                $panel->auth_token,
                $testUserId,
                100 * 1024 * 1024, // 100 MB лимит
                time() + 60, // Истекает через минуту
                1 // 1 подключение
            );

            if (empty($userData)) {
                return false;
            }

            // Если создание успешно, удаляем тестового пользователя
            try {
                $marzbanApi->deleteUser($panel->auth_token, $testUserId);
            } catch (\Exception $e) {
                // Игнорируем ошибки удаления тестового пользователя
                Log::warning('Failed to delete test user', [
                    'panel_id' => $panel->id,
                    'test_user_id' => $testUserId,
                    'error' => $e->getMessage(),
                ]);
            }

            return true;

        } catch (\Exception $e) {
            Log::debug('Panel health check failed', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
