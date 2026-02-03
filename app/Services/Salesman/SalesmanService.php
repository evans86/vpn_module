<?php

namespace App\Services\Salesman;

use App\Dto\Salesman\SalesmanDto;
use App\Dto\Salesman\SalesmanFactory;
use App\Models\Salesman\Salesman;
use App\Repositories\Salesman\SalesmanRepository;
use App\Logging\DatabaseLogger;
use Exception;
use RuntimeException;
use Telegram\Bot\Api;

class SalesmanService
{
    private SalesmanRepository $salesmanRepository;
    private DatabaseLogger $logger;

    public function __construct(
        SalesmanRepository $salesmanRepository,
        DatabaseLogger     $logger
    )
    {
        $this->salesmanRepository = $salesmanRepository;
        $this->logger = $logger;
    }

    /**
     * Get all salesmen
     * @return array
     * @throws Exception
     */
    public function getAll(): array
    {
        try {
            $this->logger->info('Getting all salesmen', [
                'source' => 'salesman',
                'action' => 'get_all'
            ]);

            $salesmen = $this->salesmanRepository->getAll();
            return $salesmen->map(fn(Salesman $salesman) => SalesmanFactory::fromEntity($salesman))->toArray();
        } catch (Exception $e) {
            $this->logger->error('Failed to get all salesmen', [
                'source' => 'salesman',
                'action' => 'get_all_error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get all active salesmen
     * @return array
     * @throws Exception
     */
    public function getAllActive(): array
    {
        try {
            $this->logger->info('Getting all active salesmen', [
                'source' => 'salesman',
                'action' => 'get_active'
            ]);

            $salesmen = $this->salesmanRepository->getAllActive();
            return $salesmen->map(fn(Salesman $salesman) => SalesmanFactory::fromEntity($salesman))->toArray();
        } catch (Exception $e) {
            $this->logger->error('Failed to get active salesmen', [
                'source' => 'salesman',
                'action' => 'get_active_error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Добавление продавца (надо вызвать из слоя телеграмма)
     *
     * @param int $telegram_id
     * @param string|null $username
     * @return SalesmanDto
     * @throws Exception
     */
    public function create(int $telegram_id, ?string $username): SalesmanDto
    {
        try {
            $this->logger->info('Creating new salesman', [
                'source' => 'salesman',
                'action' => 'create',
                'telegram_id' => $telegram_id,
                'username' => $username
            ]);

            // Check if salesman already exists
            $existingSalesman = $this->salesmanRepository->findByTelegramId($telegram_id);
            if ($existingSalesman) {
                throw new Exception('Salesman with this Telegram ID already exists');
            }


            $salesman = $this->salesmanRepository->create([
                'telegram_id' => $telegram_id,
                'username' => $username,
                'status' => true,
                'token' => null
            ]);

            $this->logger->info('Salesman created successfully', [
                'source' => 'salesman',
                'action' => 'create_success',
                'salesman_id' => $salesman->id,
                'telegram_id' => $telegram_id,
                'username' => $username,
                'token' => null
            ]);

            return SalesmanFactory::fromEntity($salesman);
        } catch (Exception $e) {
            $this->logger->error('Failed to create salesman', [
                'source' => 'salesman',
                'action' => 'create_error',
                'telegram_id' => $telegram_id,
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function assignPanel(int $salesmanId, int $panelId): void
    {
        try {
            $this->logger->info('Assigning panel to salesman', [
                'source' => 'salesman',
                'action' => 'assign_panel',
                'salesman_id' => $salesmanId,
                'panel_id' => $panelId
            ]);

            $salesman = $this->salesmanRepository->findByIdOrFail($salesmanId);
            $salesman->panel_id = $panelId;
            
            if (!$salesman->save()) {
                throw new RuntimeException('Failed to assign panel to salesman');
            }

            $this->logger->info('Panel assigned successfully', [
                'source' => 'salesman',
                'action' => 'assign_panel_success',
                'salesman_id' => $salesmanId,
                'panel_id' => $panelId
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to assign panel to salesman', [
                'source' => 'salesman',
                'action' => 'assign_panel_error',
                'salesman_id' => $salesmanId,
                'panel_id' => $panelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function resetPanel(int $salesmanId): void
    {
        try {
            $this->logger->info('Resetting panel for salesman', [
                'source' => 'salesman',
                'action' => 'reset_panel',
                'salesman_id' => $salesmanId
            ]);

            $salesman = $this->salesmanRepository->findByIdOrFail($salesmanId);
            $salesman->panel_id = null;
            
            if (!$salesman->save()) {
                throw new RuntimeException('Failed to reset panel for salesman');
            }

            $this->logger->info('Panel reset successfully', [
                'source' => 'salesman',
                'action' => 'reset_panel_success',
                'salesman_id' => $salesmanId
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to reset panel for salesman', [
                'source' => 'salesman',
                'action' => 'reset_panel_error',
                'salesman_id' => $salesmanId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Обновление токена бота продавца
     *
     * @param SalesmanDto $salesmanDto
     * @return SalesmanDto
     * @throws Exception
     */
    public function updateToken(SalesmanDto $salesmanDto): SalesmanDto
    {
        try {
            $this->logger->info('Updating salesman token', [
                'source' => 'salesman',
                'action' => 'update_token',
                'salesman_id' => $salesmanDto->id
            ]);

            $salesman = $this->salesmanRepository->findByIdOrFail($salesmanDto->id);
            $salesman = $this->salesmanRepository->updateToken($salesman, $salesmanDto->token);

            $this->logger->info('Salesman token updated successfully', [
                'source' => 'salesman',
                'action' => 'update_token_success',
                'salesman_id' => $salesman->id,
                'username' => $salesman->username
            ]);

            return SalesmanFactory::fromEntity($salesman);
        } catch (Exception $e) {
            $this->logger->error('Failed to update salesman token', [
                'source' => 'salesman',
                'action' => 'update_token_error',
                'salesman_id' => $salesmanDto->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Обновление или переключение статуса продавца
     *
     * @param int $id
     * @param bool|null $status
     * @return SalesmanDto
     * @throws Exception
     */
    public function updateStatus(int $id, ?bool $status = null): SalesmanDto
    {
        try {
            $this->logger->info('Updating salesman status', [
                'source' => 'salesman',
                'action' => 'update_status',
                'salesman_id' => $id,
                'new_status' => $status
            ]);

            $salesman = $this->salesmanRepository->findByIdOrFail($id);
            $newStatus = $status ?? !$salesman->status;

            $salesman = $this->salesmanRepository->updateStatus($salesman, $newStatus);

            $this->logger->info('Salesman status updated successfully', [
                'source' => 'salesman',
                'action' => 'update_status_success',
                'salesman_id' => $salesman->id,
                'username' => $salesman->username,
                'status' => $salesman->status
            ]);

            return SalesmanFactory::fromEntity($salesman);
        } catch (Exception $e) {
            $this->logger->error('Failed to update salesman status', [
                'source' => 'salesman',
                'action' => 'update_status_error',
                'salesman_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Получение продавца по токену
     *
     * @param string $token
     * @return SalesmanDto|null
     * @throws Exception
     */
    public function getByToken(string $token): ?SalesmanDto
    {
        try {
            $this->logger->info('Finding salesman by token', [
                'source' => 'salesman',
                'action' => 'get_by_token'
            ]);

            $salesman = $this->salesmanRepository->findByToken($token);

            if ($salesman) {
                $this->logger->info('Salesman found by token', [
                    'source' => 'salesman',
                    'action' => 'get_by_token_success',
                    'salesman_id' => $salesman->id,
                    'username' => $salesman->username
                ]);
                return SalesmanFactory::fromEntity($salesman);
            }

            $this->logger->warning('Salesman not found by token', [
                'source' => 'salesman',
                'action' => 'get_by_token_not_found'
            ]);
            return null;
        } catch (Exception $e) {
            $this->logger->error('Error finding salesman by token', [
                'source' => 'salesman',
                'action' => 'get_by_token_error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Обновить токен бота продавца
     * 
     * @param int $id
     * @param string $token
     * @return SalesmanDto
     * @throws Exception
     */
    public function updateBotToken(int $id, string $token): SalesmanDto
    {
        try {
            $this->logger->info('Updating bot token for salesman', [
                'source' => 'salesman',
                'action' => 'update_bot_token',
                'salesman_id' => $id
            ]);

            $salesman = $this->salesmanRepository->findById($id);

            // Проверяем токен через Telegram API
            $telegram = new Api($token);
            $botInfo = $telegram->getMe();

            // Обновляем токен и ссылку на бота
            $salesman->token = $token;
            $salesman->bot_link = 'https://t.me/' . $botInfo->getUsername();
            $salesman->save();

            // Устанавливаем вебхук для бота продавца
            $webhookUrl = rtrim('https://vpn-telegram.com', '/') . '/api/telegram/salesman-bot/' . $token . '/init';
            $telegram->setWebhook(['url' => $webhookUrl]);

            $this->logger->info('Bot token updated successfully', [
                'source' => 'salesman',
                'action' => 'update_bot_token_success',
                'salesman_id' => $id,
                'bot_username' => $botInfo->getUsername()
            ]);

            return SalesmanFactory::fromEntity($salesman);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update bot token', [
                'source' => 'salesman',
                'action' => 'update_bot_token_error',
                'salesman_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Не удалось обновить токен бота: ' . $e->getMessage());
        }
    }
}
