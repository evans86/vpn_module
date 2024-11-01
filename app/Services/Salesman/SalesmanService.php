<?php

namespace App\Services\Salesman;

use App\Dto\Salesman\SalesmanDto;
use App\Dto\Salesman\SalesmanFactory;
use App\Models\Salesman\Salesman;
use RuntimeException;
use Exception;

class SalesmanService
{
    /**
     * Добавление продавца (надо вызвать из слоя телеграмма)
     *
     * @param int $telegram_id
     * @param string $username
     * @param string $token
     * @param string $bot_link
     * @param bool $status активный или неактивный
     * @return SalesmanDto
     * @throws Exception
     */
    public function create(int $telegram_id, string $username, string $token, string $bot_link, bool $status = Salesman::ACTIVE): SalesmanDto
    {
        try {
            $salesman = new Salesman();

            $salesman->telegram_id = $telegram_id;
            $salesman->username = $username;
            $salesman->token = $token;
            $salesman->bot_link = $bot_link;
            $salesman->status = $status;

            if (!$salesman->save())
                throw new RuntimeException('Salesman dont create');

            return SalesmanFactory::fromEntity($salesman);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
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

            $salesman->token = $salesmanDto->token;

            if (!$salesman->save())
                throw new RuntimeException('Salesman dont update token');

            return SalesmanFactory::fromEntity($salesman);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Обновление статуса продавца (активный/не активный)
     *
     * @param SalesmanDto $salesmanDto
     * @return SalesmanDto
     * @throws Exception
     */
    public function updateStatus(SalesmanDto $salesmanDto): SalesmanDto
    {
        try {
            /**
             * @var Salesman $salesman
             */
            $salesman = Salesman::query()->where('id', $salesmanDto->id)->firstOrFail();

            $salesman->status = $salesmanDto->status;

            if (!$salesman->save())
                throw new \RuntimeException('Salesman dont update status');

            return SalesmanFactory::fromEntity($salesman);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
