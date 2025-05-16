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
use Telegram\Bot\Api;

class PackSalesmanService
{
    private PackRepository $packRepository;
    private SalesmanRepository $salesmanRepository;
    private PackSalesmanRepository $packSalesmanRepository;

    private KeyActivateService $keyActivateService;

    public function __construct(
        PackRepository         $packRepository,
        SalesmanRepository     $salesmanRepository,
        PackSalesmanRepository $packSalesmanRepository,
        KeyActivateService     $keyActivateService
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

            //выполнение действий после успешной оплаты
            self::successPaid($pack_salesman->id);
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
            $salesman = $this->salesmanRepository->findByIdOrFail($pack_salesman->salesman_id);

            // Создаем ключи согласно параметрам пакета
            for ($i = 0; $i < $pack->count; $i++) {
                $now = time();
                $period_days = min($pack->period, 365 * 2);
                $activate_days = $pack->activate_time / 86400;
//                $finish_at = $now + ($period_days * 24 * 60 * 60);
//                $deleted_at = $now + ($activate_days * 24 * 60 * 60);

                try {
                    $this->keyActivateService->create(
                        $pack->traffic_limit,
                        $pack_salesman->id,
                        null,
                        null,
                        null
                    );
                } catch (Exception $e) {
                    Log::error("Ошибка при создании ключа: " . $e->getMessage());
                    throw new RuntimeException('Ошибка при создании ключа: ' . $e->getMessage());
                }
            }

            // Отправляем сообщение через FatherBot
            $message = "✅ Ваш пакет на \"{$pack->count}\" ключей успешно активирован!\n\n";
            $message .= "📦 Количество ключей: {$pack->count}\n";
            $message .= "⏱ Период действия: {$pack->period} дней\n";
            $message .= "💾 Лимит трафика: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
            $message .= "⚡️ Время на активацию: " . intval($pack->activate_time / 86400) . " день(ей)\n\n";

            try {
                $telegram = new Api(config('telegram.father_bot.token'));
                $telegram->sendMessage([
                    'chat_id' => $salesman->telegram_id,
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ]);
            } catch (Exception $e) {
                Log::error('Ошибка при отправке сообщения через FatherBot', [
                    'error' => $e->getMessage(),
                    'salesman_id' => $salesman->id,
                    'telegram_id' => $salesman->telegram_id
                ]);
            }

        } catch (Exception $e) {
            throw new Exception('Ошибка при создании ключей: ' . $e->getMessage());
        }
    }

    //проверка всех купленных пакетов по статусу
    //@TODO
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
