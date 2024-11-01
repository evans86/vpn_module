<?php

namespace App\Services\Pack;

use App\Dto\Pack\PackDto;
use App\Dto\Pack\PackFactory;
use App\Models\Pack\Pack;
use Exception;
use RuntimeException;

class PackService
{
    /**
     * Создание пакета ключей
     *
     * @param int $price цена пакета
     * @param int $period сколько дней действует ключ
     * @param int $traffic_limit объем трафика
     * @param int $count количество ключей в пакете
     * @param int $activate_time время, за которое надо активировать ключи
     * @param bool $status активный или архивный
     * @return PackDto
     * @throws Exception
     */
    public function create(int $price, int $period, int $traffic_limit, int $count, int $activate_time, bool $status = Pack::ACTIVE): PackDto
    {
        try {
            $pack = new Pack();

            $pack->price = $price;
            $pack->period = $period;
            $pack->traffic_limit = $traffic_limit;
            $pack->count = $count;
            $pack->activate_time = $activate_time;
            $pack->status = $status;

            if (!$pack->save())
                throw new \RuntimeException('Pack dont create');

            return PackFactory::fromEntity($pack);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Обновление статуса пакета
     *
     * @param PackDto $packDto
     * @return PackDto
     * @throws Exception
     */
    public function updateStatus(PackDto $packDto): PackDto
    {
        try {
            /**
             * @var Pack $pack
             */
            $pack = Pack::query()->where('id', $packDto->id)->firstOrFail();

            $pack->status = $packDto->status;

            if (!$pack->save())
                throw new \RuntimeException('Pack dont update status');

            return PackFactory::fromEntity($pack);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Обновление настроек пакета
     *
     * @param PackDto $packDto
     * @return PackDto
     * @throws Exception
     */
    public function updateSettings(PackDto $packDto): PackDto
    {
        try {
            /**
             * @var Pack $pack
             */
            $pack = Pack::query()->where('id', $packDto->id)->firstOrFail();

            $pack->price = $packDto->price;
            $pack->period = $packDto->period;
            $pack->traffic_limit = $packDto->traffic_limit;
            $pack->count = $packDto->count;
            $pack->activate_time = $packDto->activate_time;

            if (!$pack->save())
                throw new \RuntimeException('Pack dont update settings');

            return PackFactory::fromEntity($pack);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
