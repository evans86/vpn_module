<?php

namespace App\Services\Pack;

use App\Dto\PackSalesman\PackSalesmanDto;
use App\Dto\PackSalesman\PackSalesmanFactory;
use App\Models\PackSalesman\PackSalesman;
use App\Repositories\Pack\PackRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Salesman\SalesmanRepository;
use App\Services\Key\KeyActivateService;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PackSalesmanService
{
    private PackRepository $packRepository;
    private SalesmanRepository $salesmanRepository;
    private PackSalesmanRepository $packSalesmanRepository;

    private KeyActivateService $keyActivateService;

    public function __construct(
        PackRepository $packRepository,
        SalesmanRepository $salesmanRepository,
        PackSalesmanRepository $packSalesmanRepository,
        KeyActivateService $keyActivateService
    )
    {
        $this->packRepository = $packRepository;
        $this->salesmanRepository = $salesmanRepository;
        $this->packSalesmanRepository = $packSalesmanRepository;
        $this->keyActivateService = $keyActivateService;
    }

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
            $pack = $this->packRepository->findByIdOrFail($pack_id);

            $salesman = $this->salesmanRepository->findByIdOrFail($salesman_id);

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
            $pack_salesman = $this->packSalesmanRepository->findByIdOrFail($pack_salesman_id);

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
        try {
            $pack_salesman = $this->packSalesmanRepository->findByIdOrFail($pack_salesman_id);

            $pack = $this->packRepository->findByIdOrFail($pack_salesman->pack_id);

            // Создаем ключи согласно параметрам пакета
            for ($i = 0; $i < $pack->count; $i++) {
                // Используем текущее время как базу
                $now = time();

                // Ограничиваем периоды до разумных значений
                $period_days = min($pack->period, 365 * 2); // максимум 2 года
                $activate_days = min($pack->activate_time, 90); // максимум 90 дней на активацию

                // Вычисляем временные метки относительно текущего времени
                $finish_at = $now + ($period_days * 24 * 60 * 60);
                $deleted_at = $now + ($activate_days * 24 * 60 * 60);

                try {
                    $this->keyActivateService->create(
                        $pack->traffic_limit,  // Лимит трафика из пакета
                        $pack_salesman->id,    // ID связи пакет-продавец
                        $finish_at,            // Дата окончания действия ключа
                        $deleted_at,            // Дата, до которой нужно активировать ключ
                        null                  // Время начала (null = начнется при активации)
                    );
                } catch (\Exception $e) {
                    Log::error("Ошибка при создании ключа: " . $e->getMessage());
                    throw new RuntimeException('Ошибка при создании ключа: ' . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            throw new Exception('Ошибка при создании ключей: ' . $e->getMessage());
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
