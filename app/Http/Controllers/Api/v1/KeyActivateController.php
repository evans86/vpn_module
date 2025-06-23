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
                'config_url' => "https://vpn-telegram.com/config/$activatedKey->id",
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
    public function getFreeKey(PackSalesmanFreeKeyRequest $request)
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
                ->whereNull('pack_salesman_id')->first();

            if ($hasExistingKey) {
                return ApiHelpers::success([
                    'key' => $hasExistingKey->id,
                    'config_url' => "https://vpn-telegram.com/config/{$hasExistingKey->id}",
                    'traffic_limit' => $hasExistingKey->traffic_limit,
                    'traffic_limit_gb' => 5,
                    'finish_at' => $hasExistingKey->finish_at,
                    'activated_at' => $hasExistingKey->activated_at,
                    'status' => $hasExistingKey->status,
                    'status_text' => $hasExistingKey->getStatusText(),
                    'is_free' => true,
                ]);
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
            $query = KeyActivate::where('user_tg_id', $request->user_tg_id)
                ->where('status', '!=', KeyActivate::DELETED);

            $total = $query->count();

//            if ($request->has('limit')) {
//                $limit = (int)$request->input('limit', 10);
//                $offset = (int)$request->input('offset', 0);
//
//                $query->limit($limit)->offset($offset);
//            }

            $keys = $query->get()
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
                    'total' => $total,
                    'message' => 'У пользователя нет активных ключей',
                ]);
            }

            return ApiHelpers::success([
                'keys' => $keys,
                'total' => $total, // Возвращаем общее количество ключей
//                'limit' => $request->input('limit', null),
//                'offset' => $request->input('offset', 0),
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
                        'title' => '🔐 Инструкция по настройке VPN',
                        'steps' => [
                            '1️⃣ Нажмите кнопку <strong>«Купить»</strong> и приобретите VPN-ключ',
                            '2️⃣ Скопируйте конфигурацию полученного 🔑 ключа',
                            '3️⃣ Вставьте конфигурацию в приложение <strong><a class="app-link q-hoverable bordered" href="https://play.google.com/store/apps/details?id=app.hiddify.com&hl=ru"><span class="q-focus-helper"></span>Hiddify</a></strong> или <strong><a class="app-link q-hoverable bordered" href="https://apps.apple.com/ru/app/streisand/id6450534064"><span class="q-focus-helper"></span>Streisand</a></strong>'
                        ]
                    ],
                    [
                        'title' => '📁 Пошаговые инструкции по установке:',
                        'links' => [
                            [
                                'title' => 'Инструкция для Android 📱',
                                'url' => 'https://teletype.in/@bott_manager/UPSEXs-nn66'
                            ],
                            [
                                'title' => 'Инструкция для iOS 🍏',
                                'url' => 'https://teletype.in/@bott_manager/nau_zbkFsdo'
                            ],
                            [
                                'title' => 'Инструкция для Windows 🖥️',
                                'url' => 'https://teletype.in/@bott_manager/HhKafGko3sO'
                            ]
                        ]
                    ],
                    [
                        'title' => '❓ Что делать, если VPN не подключается?',
                        'steps' => [
                            '✅ Убедитесь, что используете <strong>актуальный конфиг</strong> (ключ не просрочен)',
                            '🔁 Попробуйте <strong>другой протокол</strong>: VLESS / VMess / Shadowsocks / Trojan',
                            '📲 Смените приложение на <strong>Hiddify</strong> или <strong>Streisand</strong> (другие не рекомендуются)',
                            '🔄 Перезагрузите устройство',
                            '💬 Обратитесь в поддержку бота'
                        ]
                    ],
                ],
                'support_text' => '👨🏻‍💻 По всем вопросам — пишите администратору через бота.'
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
