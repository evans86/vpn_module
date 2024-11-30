<?php

namespace App\Services\Salesman;

use App\Dto\Salesman\SalesmanDto;
use App\Dto\Salesman\SalesmanFactory;
use App\Models\Salesman\Salesman;
use App\Logging\DatabaseLogger;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class SalesmanService
{
    /** @var DatabaseLogger */
    private $logger;

    public function __construct(DatabaseLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Добавление продавца (надо вызвать из слоя телеграмма)
     *
     * @param int $telegram_id
     * @param string $username
     * @return SalesmanDto
     * @throws Exception
     */
    public function create(int $telegram_id, string $username): SalesmanDto
    {
        try {
            $this->logger->info('Создание нового продавца', [
                'source' => 'salesman',
                'action' => 'create',
                'telegram_id' => $telegram_id,
                'username' => $username
            ]);

            $salesman = new Salesman();
            $salesman->telegram_id = $telegram_id;
            $salesman->username = $username;
            $salesman->save();

            $this->logger->info('Продавец успешно создан', [
                'source' => 'salesman',
                'action' => 'create_success',
                'salesman_id' => $salesman->id,
                'telegram_id' => $telegram_id,
                'username' => $username
            ]);

            return SalesmanFactory::fromEntity($salesman);
        } catch (Exception $e) {
            $this->logger->error('Ошибка при создании продавца', [
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
            /**
             * @var Salesman $salesman
             */
            $salesman = Salesman::query()->where('id', $salesmanDto->id)->firstOrFail();

            $this->logger->info('Обновление токена продавца', [
                'source' => 'salesman',
                'action' => 'update_token',
                'salesman_id' => $salesman->id,
                'username' => $salesman->username
            ]);

            $salesman->token = $salesmanDto->token;
            $salesman->save();

            $this->logger->info('Токен продавца успешно обновлен', [
                'source' => 'salesman',
                'action' => 'update_token_success',
                'salesman_id' => $salesman->id,
                'username' => $salesman->username
            ]);

            return SalesmanFactory::fromEntity($salesman);
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении токена продавца', [
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
            /** @var Salesman $salesman */
            $salesman = Salesman::query()->findOrFail($id);

            $this->logger->info('Обновление статуса продавца', [
                'source' => 'salesman',
                'action' => 'update_status',
                'salesman_id' => $salesman->id,
                'username' => $salesman->username,
                'old_status' => $salesman->status,
                'new_status' => $status ?? !$salesman->status
            ]);

            $salesman->status = $status ?? !$salesman->status;
            $salesman->save();

            $this->logger->info('Статус продавца успешно обновлен', [
                'source' => 'salesman',
                'action' => 'update_status_success',
                'salesman_id' => $salesman->id,
                'username' => $salesman->username,
                'status' => $salesman->status
            ]);

            return SalesmanFactory::fromEntity($salesman);
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении статуса продавца', [
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
            $this->logger->info('Поиск продавца по токену', [
                'source' => 'salesman',
                'action' => 'get_by_token'
            ]);

            $salesman = Salesman::where('token', $token)->first();

            if ($salesman) {
                $this->logger->info('Продавец найден по токену', [
                    'source' => 'salesman',
                    'action' => 'get_by_token_success',
                    'salesman_id' => $salesman->id
                ]);
                return SalesmanFactory::fromEntity($salesman);
            }

            $this->logger->warning('Продавец не найден по токену', [
                'source' => 'salesman',
                'action' => 'get_by_token_not_found'
            ]);
            return null;
        } catch (Exception $e) {
            $this->logger->error('Ошибка при поиске продавца по токену', [
                'source' => 'salesman',
                'action' => 'get_by_token_error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
