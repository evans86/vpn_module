<?php

namespace App\Http\Controllers\Api\v1;

use App\Dto\Bot\BotModuleFactory;
use App\Helpers\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\KeyActivate\KeyActivateRequest;
use App\Http\Requests\PackSalesman\PackSalesmanBuyKeyRequest;
use App\Http\Requests\PackSalesman\PackSalesmanFreeKeyRequest;
use App\Http\Requests\PackSalesman\PackSalesmanUserKeysRequest;
use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Services\External\BottApi;
use App\Services\Key\KeyActivateService;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KeyActivateController extends Controller
{
    private KeyActivateService $keyActivateService;

    public function __construct(KeyActivateService $keyActivateService)
    {
        $this->middleware('api');
        $this->keyActivateService = $keyActivateService;
    }

    /**
     * Покупка и активация ключа в боте продаж (активация ключа в системе)
     *
     * @param PackSalesmanBuyKeyRequest $request
     * @return array|string
     * @throws GuzzleException
     */
    public function buyKey(PackSalesmanBuyKeyRequest $request)
    {
        try {
            // Проверка существования модуля бота
            $botModule = BotModule::where('public_key', $request->public_key)->first();
            if (!$botModule) {
                throw new RuntimeException('Модуль бота не найден');
            }

            $botModuleDto = BotModuleFactory::fromEntity($botModule);

            $userCheck = BottApi::checkUser(
                $request->user_tg_id,
                $request->user_secret_key,
                $botModule->public_key,
                $botModule->private_key
            );
            if (!$userCheck['result']) {
                throw new RuntimeException($userCheck['message']);
            }
            if ($userCheck['data']['money'] == 0) {
                throw new RuntimeException('Пополните баланс в боте');
            }

            // Покупка ключа в боте продаж
            $key = $this->keyActivateService->buyKey($botModuleDto, $request->product_id, $userCheck['data']);

            // Активация ключа в системе
            $activatedKey = $this->keyActivateService->activateModuleKey($key, $request->user_tg_id);

            return ApiHelpers::success([
                'key' => $activatedKey->id,
                'config_url' => "https://vpn-telegram.com/config/{$activatedKey->id}",
                'traffic_limit' => $activatedKey->traffic_limit,
                'traffic_limit_gb' => round($activatedKey->traffic_limit / 1024 / 1024 / 1024, 1),
                'finish_at' => $activatedKey->finish_at,
                'activated_at' => $activatedKey->activated_at,
                'status' => $activatedKey->status,
                'status_text' => $activatedKey->getStatusText(),
            ]);
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при покупке ключа: ' . $e->getMessage(), [
                'exception' => $e,
                'user_tg_id' => $request->user_tg_id ?? null,
                'product_id' => $request->product_id ?? null
            ]);
            return ApiHelpers::error('Произошла ошибка при покупке ключа');
        }
    }

    /**
     * Получение бесплатного ключа на 5GB
     *
     * @param PackSalesmanFreeKeyRequest $request
     * @return array|string
     * @throws GuzzleException
     */
    public function getFreeKey(PackSalesmanFreeKeyRequest $request): array
    {
        try {
            $botModule = BotModule::where('public_key', $request->public_key)->first();
            if (!$botModule) {
                throw new RuntimeException('Модуль бота не найден');
            }

            // Проверка существующего ключа
            $currentMonth = Carbon::now()->startOfMonth();
            $nextMonth = Carbon::now()->addMonth()->startOfMonth();

            $hasExistingKey = KeyActivate::where('user_tg_id', $request->user_tg_id)
                ->where('traffic_limit', 5 * 1024 * 1024 * 1024)
                ->whereBetween('created_at', [$currentMonth, $nextMonth])
                ->whereNull('pack_salesman_id')
                ->exists();

            Log::debug('Free Key', [
                '$hasExistingKey' => $hasExistingKey,
            ]);

            if ($hasExistingKey) {
                return ApiHelpers::error('Вы уже получали бесплатный ключ в этом месяце. Повторная выдача возможна с '
                    . $nextMonth->format('d.m.Y'));
            }

            // Создание и активация ключа
            $key = $this->keyActivateService->create(5 * 1024 * 1024 * 1024, null, null, null);
            $activatedKey = $this->keyActivateService->activate($key, $request->user_tg_id);

            return ApiHelpers::success([
                'key' => $activatedKey->id,
                'config_url' => "https://vpn-telegram.com/config/{$activatedKey->id}",
                'traffic_limit' => $activatedKey->traffic_limit,
                'traffic_limit_gb' => 5,
                'finish_at' => $activatedKey->finish_at,
                'activated_at' => $activatedKey->activated_at,
                'status' => $activatedKey->status,
                'status_text' => $activatedKey->getStatusText(),
                'is_free' => true,
            ]);
        } catch (RuntimeException $e) {
            return ApiHelpers::error($e->getMessage());
        } catch (Exception $e) {
            Log::error('Free key error', [
                'user_tg_id' => $request->user_tg_id,
                'error' => $e->getMessage()
            ]);
            return ApiHelpers::error('Произошла ошибка при выдаче ключа');
        }
    }

    /**
     * Получение ключа
     *
     * @param KeyActivateRequest $request
     * @return array|string
     */
    public function getUserKey(KeyActivateRequest $request)
    {
        try {
            $key = KeyActivate::where('id', $request->key)->first();

            // Если ключ не найден
            if (!$key) {
                return ApiHelpers::error('Ключ не найден');
            }

            $resultKey = [
                'key' => $key->id,
                'config_url' => "https://vpn-telegram.com/config/{$key->id}",
                'traffic_limit' => $key->traffic_limit,
                'traffic_limit_gb' => round($key->traffic_limit / 1024 / 1024 / 1024, 1),
                'finish_at' => $key->finish_at,
                'status' => $key->status,
                'status_text' => $key->getStatusText(),
            ];

            return ApiHelpers::success([
                'key' => $resultKey,
            ]);
        } catch (RuntimeException $e) {
            return ApiHelpers::error($e->getMessage());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ApiHelpers::error('Ошибка при получении ключа');
        }
    }

    /**
     * Получение списка ключей пользователя
     *
     * @param PackSalesmanUserKeysRequest $request
     * @return array|string
     */
    public function getUserKeys(PackSalesmanUserKeysRequest $request)
    {
        try {
            $keys = KeyActivate::where('user_tg_id', $request->user_tg_id)
                ->where('status', '!=', KeyActivate::DELETED)
                ->with('packSalesman.pack') // Подгружаем связанные данные
                ->get()
                ->map(function ($key) {
                    return [
                        'key' => $key->id,
                        'config_url' => "https://vpn-telegram.com/config/{$key->id}",
                        'traffic_limit' => $key->traffic_limit,
                        'traffic_limit_gb' => round($key->traffic_limit / 1024 / 1024 / 1024, 1),
                        'finish_at' => $key->finish_at,
                        'status' => $key->status,
                        'status_text' => $key->getStatusText()
                    ];
                });

            // Если ключей нет, возвращаем пустой массив с пояснением
            if ($keys->isEmpty()) {
                return ApiHelpers::success([
                    'keys' => [],
                    'message' => 'У пользователя нет активных ключей',
                ]);
            }

            return ApiHelpers::success([
                'keys' => $keys,
            ]);
        } catch (RuntimeException $e) {
            return ApiHelpers::error($e->getMessage());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ApiHelpers::error('Ошибка при получении списка ключей');
        }
    }

    /**
     * Получение инструкций по работе с VPN
     *
     * @return array|string
     */
    public function getVpnInstructions()
    {
        try {
            $instructions = [
                'sections' => [
                    [
                        'title' => '🔹 Активация VPN',
                        'steps' => [
                            'Нажмите \'🔑 Активировать\'',
                            'Введите полученный ключ',
                            'Скопируйте конфигурацию и следуйте инструкциям для подключения'
                        ]
                    ],
                    [
                        'title' => '🔹 Проверка статуса',
                        'steps' => [
                            'Нажмите кнопку \'📊 Статус\'',
                            'Просмотрите информацию о вашем доступе и конфигурации'
                        ]
                    ],
                    [
                        'title' => '📁 Инструкции по настройке VPN',
                        'links' => [
                            [
                                'title' => 'Инструкция для Android 📱',
                                'url' => 'https://docs.google.com/document/d/1ma6QZjKgwLHdS2457I8C6k22gC2Cq3Yic8bLiMeXmeY/edit'
                            ],
                            [
                                'title' => 'Инструкция для iOS 🍏',
                                'url' => 'https://docs.google.com/document/d/1f3iS-V0kFVQEA3i1hYOEaAoNMucgF60XiDZZdhRl59Q/edit'
                            ],
                            [
                                'title' => 'Инструкция для Windows 🖥️',
                                'url' => 'https://docs.google.com/document/d/1jXNpuNY9eET1LXyVmRjHSoX6YRX9RlWGJQFSEJE_2Jg/edit'
                            ]
                        ]
                    ]
                ],
                'support_text' => '👨🏻‍💻 По всем вопросам обращайтесь к администратору бота.'
            ];

            return ApiHelpers::success([
                'structured' => $instructions
            ]);

        } catch (Exception $e) {
            Log::error('Ошибка при получении инструкций: ' . $e->getMessage());
            return ApiHelpers::error('Не удалось загрузить инструкции');
        }
    }
}
