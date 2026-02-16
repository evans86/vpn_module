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
            
            // Логируем всю структуру данных для отладки
            Log::info('BOT-T: Full order data structure', [
                'source' => 'bott',
                'order_id' => $orderId,
                'full_data' => $orderData,
                'category' => $orderData['category'] ?? null,
                'product' => $orderData['product'] ?? null,
                'product_id_field' => $orderData['product_id'] ?? null,
                'all_keys' => array_keys($orderData),
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
            $count = $orderData['count'] ?? 1;

            // Приоритет поиска: productId (ID товара) > categoryApiId (API ID категории)
            $pack = null;
            
            // Вариант 1: Используем ID товара напрямую (ПРИОРИТЕТ #1)
            // Это ID товара из BOT-T (например, 2719564)
            if ($productId) {
                $pack = $this->findPackByApiId((int)$productId);
                if ($pack) {
                    Log::info('BOT-T: Pack found by product ID (category.id)', [
                        'source' => 'bott',
                        'product_id' => $productId,
                        'pack_id' => $pack->id,
                        'pack_title' => $pack->title
                    ]);
                }
            }
            
            // Вариант 2: Используем API ID категории (ПРИОРИТЕТ #2)
            // Это api_id, установленный в категории/товаре BOT-T
            if (!$pack && $categoryApiId) {
                $pack = $this->findPackByApiId((int)$categoryApiId);
                if ($pack) {
                    Log::info('BOT-T: Pack found by category API ID', [
                        'source' => 'bott',
                        'category_api_id' => $categoryApiId,
                        'pack_id' => $pack->id,
                        'pack_title' => $pack->title
                    ]);
                }
            }

            if (!$pack) {
                Log::error('BOT-T: Pack not found', [
                    'source' => 'bott',
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'category_api_id' => $categoryApiId,
                    'category' => $orderData['category'] ?? null
                ]);

                return [
                    'success' => false,
                    'error' => "Pack not found. Tried product_id={$productId}, category_api_id={$categoryApiId}. Please ensure pack api_id matches one of these values."
                ];
            }

            if (!$pack) {
                Log::warning('BOT-T: Pack not found by category API ID', [
                    'source' => 'bott',
                    'category_api_id' => $categoryApiId,
                    'order_id' => $orderId,
                    'category' => $orderData['category'] ?? null,
                    'available_packs' => Pack::select('id', 'api_id', 'title')->get()->toArray()
                ]);

                return [
                    'success' => false,
                    'error' => "Pack not found for category API ID: {$categoryApiId}. Please ensure that the category API ID in BOT-T matches the pack api_id in VPN system."
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
                return [
                    'success' => false,
                    'error' => 'Failed to find or create salesman'
                ];
            }

            // Создаем PackSalesman
            $packSalesman = $this->packSalesmanService->create(
                $pack->id,
                $salesman->id,
                PackSalesman::PAID
            );

            // Создаем ключи согласно количеству в заказе
            $keysCreated = 0;
            $keys = [];

            for ($i = 0; $i < $count; $i++) {
                try {
                    $key = $this->keyActivateService->create(
                        $pack->traffic_limit,
                        $packSalesman->id,
                        null,
                        null,
                        null
                    );
                    $keys[] = $key;
                    $keysCreated++;
                } catch (Exception $e) {
                    Log::error('BOT-T: Error creating key', [
                        'source' => 'bott',
                        'order_id' => $orderId,
                        'pack_salesman_id' => $packSalesman->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Отправляем ключи пользователю через FatherBot
            $this->sendKeysToUser($userTelegramId, $keys, $pack, $orderId);

            Log::info('BOT-T: Order processed successfully', [
                'source' => 'bott',
                'order_id' => $orderId,
                'pack_id' => $pack->id,
                'salesman_id' => $salesman->id,
                'pack_salesman_id' => $packSalesman->id,
                'keys_created' => $keysCreated
            ]);

            return [
                'success' => true,
                'pack_salesman_id' => $packSalesman->id,
                'keys_created' => $keysCreated
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
        Log::info('BOT-T: Searching pack by API ID', [
            'source' => 'bott',
            'api_id' => $apiId
        ]);

        // Вариант 1: Если есть поле api_id в таблице pack (прямое совпадение)
        $pack = Pack::where('api_id', $apiId)->first();
        
        if ($pack) {
            Log::info('BOT-T: Pack found by direct api_id match', [
                'source' => 'bott',
                'api_id' => $apiId,
                'pack_id' => $pack->id,
                'pack_title' => $pack->title
            ]);
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
                    Log::info('BOT-T: Pack found by config mapping (api_id)', [
                        'source' => 'bott',
                        'category_api_id' => $apiId,
                        'mapped_pack_api_id' => $packApiId,
                        'pack_id' => $pack->id,
                        'pack_title' => $pack->title
                    ]);
                    return $pack;
                }
            } else {
                // Иначе ищем по ID пакета
                $packId = (int) $mappedValue;
                $pack = $this->packRepository->findById($packId);
                if ($pack) {
                    Log::info('BOT-T: Pack found by config mapping (pack_id)', [
                        'source' => 'bott',
                        'category_api_id' => $apiId,
                        'mapped_pack_id' => $packId,
                        'pack_title' => $pack->title
                    ]);
                    return $pack;
                }
            }
        }

        // Вариант 3: Если api_id совпадает с id пакета (fallback)
        $pack = $this->packRepository->findById($apiId);
        if ($pack) {
            Log::info('BOT-T: Pack found by ID match (fallback)', [
                'source' => 'bott',
                'api_id' => $apiId,
                'pack_id' => $pack->id,
                'pack_title' => $pack->title
            ]);
            return $pack;
        }

        Log::warning('BOT-T: Pack not found by any method', [
            'source' => 'bott',
            'api_id' => $apiId,
            'available_packs' => Pack::select('id', 'api_id', 'title')->get()->toArray()
        ]);

        return null;
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
     * Отправка ключей пользователю через FatherBot
     * 
     * @param int $telegramId Telegram ID пользователя
     * @param array $keys Массив созданных ключей
     * @param Pack $pack Пакет
     * @param int $orderId ID заказа из BOT-T
     * @return void
     */
    private function sendKeysToUser(int $telegramId, array $keys, Pack $pack, int $orderId): void
    {
        try {
            $telegram = new Api(config('telegram.father_bot.token'));

            $message = "✅ Ваш заказ #{$orderId} успешно оплачен!\n\n";
            $message .= "📦 Пакет: {$pack->title}\n";
            $message .= "🔑 Количество ключей: " . count($keys) . "\n";
            $message .= "⏱ Период действия: {$pack->period} дней\n";
            
            if ($pack->traffic_limit > 0) {
                $trafficGb = round($pack->traffic_limit / (1024 * 1024 * 1024), 1);
                $message .= "💾 Лимит трафика: {$trafficGb} GB\n";
            }
            
            $message .= "\n🔑 Ваши ключи активации:\n\n";

            foreach ($keys as $index => $key) {
                $keyNumber = $index + 1;
                $message .= "{$keyNumber}. <code>{$key->id}</code>\n";
                $message .= "   🔗 https://vpn-telegram.com/config/{$key->id}\n\n";
            }

            $message .= "💡 Для активации ключа отправьте его боту или перейдите по ссылке выше.";

            $telegram->sendMessage([
                'chat_id' => $telegramId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            Log::info('BOT-T: Keys sent to user', [
                'source' => 'bott',
                'telegram_id' => $telegramId,
                'order_id' => $orderId,
                'keys_count' => count($keys)
            ]);
        } catch (Exception $e) {
            Log::error('BOT-T: Failed to send keys to user', [
                'source' => 'bott',
                'telegram_id' => $telegramId,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }
}

