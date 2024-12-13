<?php

namespace App\Services\Salesman;

use App\Dto\Salesman\SalesmanDto;
use App\Dto\Salesman\SalesmanFactory;
use App\Models\Salesman\Salesman;
use App\Repositories\Salesman\SalesmanRepository;
use App\Logging\DatabaseLogger;
use Exception;

class SalesmanService
{
    private SalesmanRepository $salesmanRepository;
    private DatabaseLogger $logger;

    public function __construct(
        SalesmanRepository $salesmanRepository,
        DatabaseLogger $logger
    ) {
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
                'status' => true
            ]);

            $this->logger->info('Salesman created successfully', [
                'source' => 'salesman',
                'action' => 'create_success',
                'salesman_id' => $salesman->id,
                'telegram_id' => $telegram_id,
                'username' => $username
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
}
