<?php

namespace App\Services\Pack;

use App\Dto\PackSalesman\PackSalesmanDto;
use App\Dto\PackSalesman\PackSalesmanFactory;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use App\Services\Key\KeyActivateService;
use Exception;
use RuntimeException;

class PackSalesmanService
{
    /**
     * Пакет ключей, купленный продавцом
     *
     * @param int $pack_id
     * @param int $salesman_id
     * @param int $status оплачен/не оплачен/вышел срок
     * @return PackSalesmanDto
     * @throws Exception
     */
    public function create(int $pack_id, int $salesman_id, int $status = PackSalesman::NOT_PAID): PackSalesmanDto
    {
        try {
            /**
             * @var Pack $pack
             */
            $pack = Pack::query()->where('id', $pack_id)->firstOrFail();
            /**
             * @var Salesman $salesman
             */
            $salesman = Salesman::query()->where('id', $salesman_id)->firstOrFail();

            $pack_salesman = new PackSalesman();

            $pack_salesman->pack_id = $pack->id;
            $pack_salesman->salesman_id = $salesman->id;
            if ($pack->price == 0)
                $pack_salesman->status = PackSalesman::PAID;
            else
                $pack_salesman->status = $status;

            if (!$pack_salesman->save())
                throw new RuntimeException('Pack Salesman dont create');

            return PackSalesmanFactory::fromEntity($pack_salesman);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param int $pack_salesman_id
     * @return void
     * @throws Exception
     */
    public function success(int $pack_salesman_id): void
    {
        try {
            /**
             * @var PackSalesman $pack_salesman
             */
            $pack_salesman = PackSalesman::query()->where('id', $pack_salesman_id)->firstOrFail();

            //Должна появиться логика оплаты и после этого статус PAID, пока сразу для тестов

            $pack_salesman->status = PackSalesman::PAID;

            if (!$pack_salesman->save())
                throw new RuntimeException('Pack Salesman dont update status');

            self::successPaid($pack_salesman->id);//выполнение действий после успешной оплаты
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param int $pack_salesman_id
     * @return void
     * @throws Exception
     */
    private function successPaid(int $pack_salesman_id): void
    {
        /**
         * @var PackSalesman $pack_salesman
         */
        $pack_salesman = PackSalesman::query()->where('id', $pack_salesman_id)->firstOrFail();
        /**
         * @var Pack $pack
         */
        $pack = Pack::query()->where('id', $pack_salesman->pack_id)->firstOrFail();
        $keyActivateService = new KeyActivateService();

        /**
         * @var $traffic_limit - лимит трафика 10 GB
         * @var $finish_at - срок действия 7 дней (604800 секунд)
         * @var $deleted_at - нужно активировать за 1 месяц (2629743 секунд)
         */
        for ($n = 0; $n < $pack->count; $n++) {
            $keyActivateService->create(10737418240, $pack_salesman->id, 604800, null, time() + 2629743);
        }
    }

    //проверка всех купленных пакетов по статусу
    public function checkStatus()
    {
        /**
         * @var PackSalesman[] $packs_salesman
         */
        $packs_salesman = PackSalesman::query()->where('status', PackSalesman::EXPIRED)->get();

        foreach ($packs_salesman as $pack_salesman) {

        }
    }
}
