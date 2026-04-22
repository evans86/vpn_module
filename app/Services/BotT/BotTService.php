<?php

namespace App\Services\BotT;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use App\Repositories\Pack\PackRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Salesman\SalesmanRepository;
use App\Services\Key\KeyActivateService;
use App\Services\Pack\PackSalesmanService;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class BotTService
{
    private PackRepository $packRepository;
    private SalesmanRepository $salesmanRepository;
    private PackSalesmanRepository $packSalesmanRepository;
    private KeyActivateService $keyActivateService;
    private PackSalesmanService $packSalesmanService;

    public function __construct(
        PackRepository $packRepository,
        SalesmanRepository $salesmanRepository,
        PackSalesmanRepository $packSalesmanRepository,
        KeyActivateService $keyActivateService,
        PackSalesmanService $packSalesmanService
    ) {
        $this->packRepository = $packRepository;
        $this->salesmanRepository = $salesmanRepository;
        $this->packSalesmanRepository = $packSalesmanRepository;
        $this->keyActivateService = $keyActivateService;
        $this->packSalesmanService = $packSalesmanService;
    }

    /**
     * Обработка заказа из BOT-T
     * 
     * @param array $orderData Данные заказа из BOT-T
     * @return array Результат обработки
     */
    public function processOrder(array $orderData): array
    {
        try {
            $orderId = $orderData['id'];
            
            // Логируем только основную информацию
            Log::info('BOT-T: Order processing started', [
                'source' => 'bott',
                'order_id' => $orderId,
                'order_count' => (int) ($orderData['count'] ?? 1),
                'category_id' => $orderData['category']['id'] ?? null,
                'category_api_id' => $orderData['category']['api_id'] ?? null,
                'user_telegram_id' => $orderData['user']['telegram_id'] ?? null,
            ]);
            
            // Согласно документации BOT-T и реальным данным:
            // - category содержит api_id в корне объекта (не category.api_id)
            // - category.id - это ID товара (2719564)
            // - category.api_id - это API ID, установленный в категории/товаре
            // - product содержит только type и data, без id
            
            // Пробуем получить api_id из разных мест в структуре category
            $categoryApiId = $orderData['category']['api_id'] 
                          ?? $orderData['api_id'] 
                          ?? null;
            
            // Также пробуем использовать ID товара напрямую (category.id)
            $productId = $orderData['category']['id'] 
                      ?? $orderData['id'] 
                      ?? null;
            $userTelegramId = $orderData['user']['telegram_id'] ?? null;
            $orderCount = max(1, (int) ($orderData['count'] ?? 1));
            $maxOrderUnits = max(1, (int) config('bott.max_order_units', 500));
            if ($orderCount > $maxOrderUnits) {
                Log::warning('BOT-T: order count exceeds max_order_units, capping', [
                    'source' => 'bott',
                    'order_id' => $orderId,
                    'requested' => $orderCount,
                    'max' => $maxOrderUnits,
                ]);
                $orderCount = $maxOrderUnits;
            }

            // Приоритет поиска: productId (ID товара) > categoryApiId (API ID категории)
            $pack = null;
            
            // Вариант 1: Используем ID товара напрямую (ПРИОРИТЕТ #1)
            // Это ID товара из BOT-T (например, 2719564)
            if ($productId) {
                $pack = $this->findPackByApiId((int)$productId);
            }
            
            // Вариант 2: Используем API ID категории (ПРИОРИТЕТ #2)
            // Это api_id, установленный в категории/товаре BOT-T
            if (!$pack && $categoryApiId) {
                $pack = $this->findPackByApiId((int)$categoryApiId);
            }

            if (!$pack) {
                Log::error('BOT-T: Pack not found', [
                    'source' => 'bott',
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'category_api_id' => $categoryApiId,
                    'category' => $orderData['category'] ?? null,
                    'available_packs' => Pack::select('id', 'api_id', 'title')->get()->toArray()
                ]);

                return [
                    'success' => false,
                    'error' => "Pack not found. Tried product_id={$productId}, category_api_id={$categoryApiId}. Please ensure pack api_id matches one of these values."
                ];
            }

            if (!$userTelegramId) {
                return [
                    'success' => false,
                    'error' => 'User Telegram ID is required'
                ];
            }

            // Находим или создаем salesman по telegram_id
            $salesman = $this->findOrCreateSalesman($userTelegramId, $orderData['user'] ?? []);
            if (!$salesman) {
                Log::error('BOT-T: Failed to find or create salesman', [
                    'source' => 'bott',
                    'order_id' => $orderId,
                    'telegram_id' => $userTelegramId
                ]);
                return [
                    'success' => false,
                    'error' => 'Failed to find or create salesman'
                ];
            }

            // count в заказе BOT-T — число купленных единиц товара; на каждую — свой PackSalesman.
            // В каждом пакете создаётся столько ключей, сколько задано в шаблоне пакета (pack.count).
            $keysPerUnit = max(1, (int) ($pack->count ?? 1));
            $keysCreated = 0;
            $packSalesmanIds = [];

            for ($unit = 0; $unit < $orderCount; $unit++) {
                $packSalesmanDto = $this->packSalesmanService->create(
                    $pack->id,
                    $salesman->id,
                    PackSalesman::PAID
                );
                $packSalesmanIds[] = $packSalesmanDto->id;

                for ($k = 0; $k < $keysPerUnit; $k++) {
                    try {
                        $this->keyActivateService->create(
                            $pack->traffic_limit,
                            $packSalesmanDto->id,
                            null,
                            null
                        );
                        $keysCreated++;
                    } catch (Exception $e) {
                        Log::error('BOT-T: Error creating key', [
                            'source' => 'bott',
                            'order_id' => $orderId,
                            'order_unit' => $unit + 1,
                            'order_count' => $orderCount,
                            'pack_salesman_id' => $packSalesmanDto->id,
                            'key_index' => $k + 1,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if ($keysCreated > 0) {
                $this->sendOrderConfirmation(
                    $userTelegramId,
                    $pack,
                    $keysCreated,
                    $orderId,
                    $orderCount
                );
            }

            Log::info('BOT-T: Order processed successfully', [
                'source' => 'bott',
                'order_id' => $orderId,
                'pack_id' => $pack->id,
                'salesman_id' => $salesman->id,
                'order_count' => $orderCount,
                'keys_per_unit' => $keysPerUnit,
                'pack_salesman_ids' => $packSalesmanIds,
                'keys_created' => $keysCreated,
            ]);

            return [
                'success' => true,
                'pack_salesman_id' => $packSalesmanIds !== [] ? $packSalesmanIds[count($packSalesmanIds) - 1] : null,
                'pack_salesman_ids' => $packSalesmanIds,
                'keys_created' => $keysCreated,
                'order_units' => $orderCount,
            ];
        } catch (Exception $e) {
            Log::error('BOT-T: Exception during order processing', [
                'source' => 'bott',
                'order_id' => $orderData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Проверка товара (для уникальных товаров)
     * 
     * @param string $product Содержимое товара (ключ активации)
     * @return bool true если товар валиден, false если уже использован
     */
    public function validateProduct(string $product): bool
    {
        try {
            // Проверяем, не использован ли уже этот ключ
            // Предполагаем, что product - это UUID ключа активации
            $key = KeyActivate::where('id', $product)->first();

            if (!$key) {
                // Ключ не найден - считаем валидным (новый ключ)
                return true;
            }

            // Если ключ уже активирован или использован - невалиден
            if ($key->status === KeyActivate::ACTIVE || $key->user_tg_id) {
                return false;
            }

            // Ключ существует, но не использован - валиден
            return true;
        } catch (Exception $e) {
            Log::error('BOT-T: Exception during product validation', [
                'source' => 'bott',
                'product' => substr($product, 0, 50),
                'error' => $e->getMessage()
            ]);

            // При ошибке считаем невалидным для безопасности
            return false;
        }
    }

    /**
     * Найти пакет по API ID
     * 
     * @param int $apiId API ID из категории BOT-T или ID товара
     * @return Pack|null
     */
    private function findPackByApiId(int $apiId): ?Pack
    {
        // Вариант 1: Если есть поле api_id в таблице pack (прямое совпадение)
        $pack = Pack::where('api_id', $apiId)->first();
        if ($pack) {
            return $pack;
        }

        // Вариант 2: Используем конфигурацию для маппинга
        // Формат 1: category.api_id => pack_id
        // Формат 2: category.api_id => pack_api_id (если значение начинается с "api:")
        $packMapping = config('bott.pack_mapping', []);
        if (isset($packMapping[$apiId])) {
            $mappedValue = $packMapping[$apiId];
            
            // Если значение начинается с "api:", ищем по api_id пакета
            if (is_string($mappedValue) && strpos($mappedValue, 'api:') === 0) {
                $packApiId = (int) substr($mappedValue, 4);
                $pack = Pack::where('api_id', $packApiId)->first();
                if ($pack) {
                    return $pack;
                }
            } else {
                // Иначе ищем по ID пакета
                $packId = (int) $mappedValue;
                $pack = $this->packRepository->findById($packId);
                if ($pack) {
                    return $pack;
                }
            }
        }

        // Вариант 3: Если api_id совпадает с id пакета (fallback)
        return $this->packRepository->findById($apiId);
    }

    /**
     * Найти или создать salesman по telegram_id
     * 
     * @param int $telegramId Telegram ID пользователя
     * @param array $userData Данные пользователя из BOT-T
     * @return Salesman|null
     */
    private function findOrCreateSalesman(int $telegramId, array $userData = []): ?Salesman
    {
        // Ищем существующего salesman
        $salesman = $this->salesmanRepository->findByTelegramId($telegramId);

        if ($salesman) {
            return $salesman;
        }

        // Проверяем, разрешено ли автоматическое создание salesman
        if (!config('bott.auto_create_salesman', true)) {
            Log::warning('BOT-T: Auto create salesman disabled', [
                'source' => 'bott',
                'telegram_id' => $telegramId
            ]);
            return null;
        }

        // Создаем нового salesman для пользователя из BOT-T
        // Используем системного бота для отправки ключей
        try {
            $salesman = new Salesman();
            $salesman->telegram_id = $telegramId;
            $salesman->username = $userData['username'] ?? null;
            $salesman->status = true;
            
            // Используем токен из конфигурации или токен Father Bot
            $defaultToken = config('bott.default_salesman_token', '');
            if (empty($defaultToken)) {
                $defaultToken = config('telegram.father_bot.token', '');
            }
            $salesman->token = $defaultToken;
            
            if (!$salesman->save()) {
                Log::error('BOT-T: Failed to create salesman', [
                    'source' => 'bott',
                    'telegram_id' => $telegramId
                ]);
                return null;
            }

            Log::info('BOT-T: Created new salesman for user', [
                'source' => 'bott',
                'salesman_id' => $salesman->id,
                'telegram_id' => $telegramId
            ]);

            return $salesman;
        } catch (Exception $e) {
            Log::error('BOT-T: Exception creating salesman', [
                'source' => 'bott',
                'telegram_id' => $telegramId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Отправка уведомления пользователю о успешной активации пакета
     *
     * @param int $telegramId Telegram ID пользователя
     * @param Pack $pack Пакет
     * @param int $keysCount Количество созданных ключей
     * @param int $orderId ID заказа из BOT-T
     * @param int $orderUnits Число купленных единиц (поле count в заказе)
     * @return void
     */
    private function sendOrderConfirmation(int $telegramId, Pack $pack, int $keysCount, int $orderId, int $orderUnits = 1): void
    {
        try {
            $telegram = new Api(config('telegram.father_bot.token'));

            if ($orderUnits > 1) {
                $message = "✅ Оплата заказа принята: <b>{$orderUnits}</b> пакетов, выдано ключей: <b>{$keysCount}</b>.\n\n";
            } else {
                $message = "✅ Пакет активирован, ключей: <b>{$keysCount}</b>.\n\n";
            }
            $message .= "⏱ Период действия (на ключ): {$pack->period} дней";

            $telegram->sendMessage([
                'chat_id' => $telegramId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            Log::info('BOT-T: Order confirmation sent to user', [
                'source' => 'bott',
                'telegram_id' => $telegramId,
                'order_id' => $orderId,
                'keys_count' => $keysCount
            ]);
        } catch (Exception $e) {
            Log::error('BOT-T: Failed to send order confirmation', [
                'source' => 'bott',
                'telegram_id' => $telegramId,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }
}

