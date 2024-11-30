<?php

namespace App\Http\Controllers\Module;

use App\Dto\Pack\PackFactory;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Pack\Pack;
use App\Services\Cloudflare\CloudflareService;
use App\Services\Key\KeyActivateService;
use App\Services\Pack\PackSalesmanService;
use App\Services\Pack\PackService;
use App\Services\Salesman\SalesmanService;
use App\Services\Server\vdsina\VdsinaService;
use App\Logging\DatabaseLogger;

class TestController
{
    /** @var DatabaseLogger */
    private $logger;

    public function __construct(DatabaseLogger $logger)
    {
        $this->logger = $logger;
    }

    public function salesman()
    {
        try {
            $salesmanService = new SalesmanService();
            $salesmanService->create(123456778, 'FirstSalesman', 'TGToken', '@bot_salesman');

            $this->logger->info('Тестовый продавец успешно создан', [
                'source' => 'test',
                'action' => 'create_salesman',
                'user_id' => auth()->id(),
                'salesman_data' => [
                    'id' => 123456778,
                    'name' => 'FirstSalesman',
                    'username' => '@bot_salesman'
                ]
            ]);

            return view('module.test.salesman');
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при создании тестового продавца', [
                'source' => 'test',
                'action' => 'create_salesman',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function pack()
    {
        try {
            $packSalesmanService = new PackSalesmanService();
            $packSalesmanService->success(1);

            $this->logger->info('Тестовая операция с пакетом выполнена успешно', [
                'source' => 'test',
                'action' => 'pack_success',
                'user_id' => auth()->id(),
                'pack_id' => 1
            ]);

            return view('module.test.pack');
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при выполнении тестовой операции с пакетом', [
                'source' => 'test',
                'action' => 'pack_success',
                'user_id' => auth()->id(),
                'pack_id' => 1,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function packSalesman()
    {
        try {
            $packSalesmanService = new PackSalesmanService();
            $packSalesmanService->create(1, 1);

            $this->logger->info('Тестовая связь пакет-продавец создана', [
                'source' => 'test',
                'action' => 'create_pack_salesman',
                'user_id' => auth()->id(),
                'pack_id' => 1,
                'salesman_id' => 1
            ]);

            return view('module.test.pack-salesman');
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при создании тестовой связи пакет-продавец', [
                'source' => 'test',
                'action' => 'create_pack_salesman',
                'user_id' => auth()->id(),
                'pack_id' => 1,
                'salesman_id' => 1,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function keyActivate()
    {
        try {
            $key_activate_service = new KeyActivateService();
            $key_activate_service->activation('05640a53-e567-493d-b28c-b11d423a127d', 987654321);

            $this->logger->info('Тестовая активация ключа выполнена успешно', [
                'source' => 'test',
                'action' => 'key_activate',
                'user_id' => auth()->id(),
                'key' => '05640a53-e567-493d-b28c-b11d423a127d',
                'user_id_activate' => 987654321
            ]);

            return view('module.test.key-activate');
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при тестовой активации ключа', [
                'source' => 'test',
                'action' => 'key_activate',
                'user_id' => auth()->id(),
                'key' => '05640a53-e567-493d-b28c-b11d423a127d',
                'user_id_activate' => 987654321,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function keyUser()
    {
        $this->logger->info('Доступ к тестовой странице ключ-пользователь', [
            'source' => 'test',
            'action' => 'view_key_user',
            'user_id' => auth()->id()
        ]);
        return view('module.test.key-user');
    }
}
