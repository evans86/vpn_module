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
            
            // Пробуем получить api_id из разных мест
            $categoryApiId = $orderData['category']['api_id'] ?? null;
            $productApiId = $orderData['product']['api_id'] ?? null;
            // Пробуем разные варианты получения ID товара
            $productId = $orderData['product']['id'] 
                      ?? $orderData['product_id'] 
                      ?? $orderData['productId'] 
                      ?? $orderData['product']['product_id']
                      ?? null;
            
            $userTelegramId = $orderData['user']['telegram_id'] ?? null;
            $count = $orderData['count'] ?? 1;

            // Пробуем найти пакет по разным ID (приоритет: product.id > product.api_id > category.api_id)
            $pack = null;
            $usedApiId = null;
            
            // Вариант 1: ID товара напрямую (ПРИОРИТЕТ #1)
            if ($productId) {
                $pack = $this->findPackByApiId($productId);
                if ($pack) {
                    $usedApiId = $productId;
                    Log::info('BOT-T: Pack found by product ID', [
                        'source' => 'bott',
                        'product_id' => $productId,
                        'pack_id' => $pack->id,
                        'pack_title' => $pack->title
                    ]);
                }
            }
            
            // Вариант 2: api_id из товара (ПРИОРИТЕТ #2)
            if (!$pack && $productApiId) {
                $pack = $this->findPackByApiId($productApiId);
                if ($pack) {
                    $usedApiId = $productApiId;
                    Log::info('BOT-T: Pack found by product API ID', [
                        'source' => 'bott',
                        'api_id' => $productApiId,
                        'pack_id' => $pack->id,
                        'pack_title' => $pack->title
                    ]);
                }
            }
            
            // Вариант 3: api_id из категории (ПРИОРИТЕТ #3 - fallback)
            if (!$pack && $categoryApiId) {
                $pack = $this->findPackByApiId($categoryApiId);
                if ($pack) {
                    $usedApiId = $categoryApiId;
                    Log::info('BOT-T: Pack found by category API ID', [
                        'source' => 'bott',
                        'api_id' => $categoryApiId,
                        'pack_id' => $pack->id,
                        'pack_title' => $pack->title
                    ]);
                }
            }

            if (!$pack) {
                Log::warning('BOT-T: Pack not found by any API ID', [
                    'source' => 'bott',
                    'category_api_id' => $categoryApiId,
                    'product_api_id' => $productApiId,
                    'product_id' => $productId,
                    'order_id' => $orderId,
                    'full_order_data' => $orderData,
                    'available_packs' => Pack::select('id', 'api_id', 'title')->get()->toArray()
                ]);

                $errorMessage = "Pack not found. ";
                $errorMessage .= "Tried: product_id={$productId}, product_api_id={$productApiId}, category_api_id={$categoryApiId}. ";
                $errorMessage .= "Please check logs for full request structure.";

                return [
                    'success' => false,
                    'error' => $errorMessage
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
     * @param int $apiId API ID из категории BOT-T
     * @return Pack|null
     */
    private function findPackByApiId(int $apiId): ?Pack
    {
        // Вариант 1: Если есть поле api_id в таблице pack
        $pack = Pack::where('api_id', $apiId)->first();
        
        if ($pack) {
            return $pack;
        }

        // Вариант 2: Используем конфигурацию для маппинга
        $packMapping = config('bott.pack_mapping', []);
        if (isset($packMapping[$apiId])) {
            $packId = $packMapping[$apiId];
            return $this->packRepository->findById($packId);
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

