<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use App\Models\OrderSetting\OrderSetting;
use App\Models\Pack\Pack;
use App\Models\PackOrderSetting\PackOrderSetting;
use App\Models\PackSalesman\PackSalesman;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\Salesman\Salesman;
use App\Services\Pack\PackSalesmanService;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Telegram\Bot\Api;

class OrderService
{
    private PackSalesmanService $packSalesmanService;

    public function __construct(PackSalesmanService $packSalesmanService)
    {
        $this->packSalesmanService = $packSalesmanService;
    }

    /**
     * Создать новый заказ
     *
     * @param int $packId
     * @param int $salesmanId
     * @param int $paymentMethodId
     * @return Order
     * @throws Exception
     */
    public function create(int $packId, int $salesmanId, int $paymentMethodId): Order
    {
        try {
            // Проверяем, включена ли система заказов
            if (!OrderSetting::isSystemEnabled()) {
                throw new RuntimeException('Система заказов временно отключена');
            }

            // Проверяем, доступен ли пакет для заказа
            $packSetting = PackOrderSetting::where('pack_id', $packId)
                ->where('is_available', true)
                ->first();

            if (!$packSetting) {
                throw new RuntimeException('Данный пакет недоступен для заказа');
            }

            $pack = Pack::findOrFail($packId);
            $salesman = Salesman::findOrFail($salesmanId);
            $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);

            if (!$paymentMethod->is_active) {
                throw new RuntimeException('Выбранный способ оплаты недоступен');
            }

            $order = new Order();
            $order->pack_id = $packId;
            $order->salesman_id = $salesmanId;
            $order->payment_method_id = $paymentMethodId;
            $order->status = Order::STATUS_PENDING;
            $order->amount = $pack->price;
            $order->save();

            Log::info('Order created', [
                'order_id' => $order->id,
                'pack_id' => $packId,
                'salesman_id' => $salesmanId,
                'amount' => $order->amount,
                'source' => 'order'
            ]);

            return $order;
        } catch (Exception $e) {
            Log::error('Failed to create order', [
                'error' => $e->getMessage(),
                'pack_id' => $packId,
                'salesman_id' => $salesmanId,
                'source' => 'order'
            ]);
            throw $e;
        }
    }

    /**
     * Отправить подтверждение оплаты
     *
     * @param int $orderId
     * @param string $paymentProofPath
     * @return Order
     * @throws Exception
     */
    public function submitPaymentProof(int $orderId, string $paymentProofPath): Order
    {
        try {
            $order = Order::findOrFail($orderId);

            if ($order->status !== Order::STATUS_PENDING) {
                throw new RuntimeException('Заказ уже обработан');
            }

            $order->payment_proof = $paymentProofPath;
            $order->status = Order::STATUS_AWAITING_CONFIRMATION;
            $order->save();

            Log::info('Payment proof submitted', [
                'order_id' => $orderId,
                'source' => 'order'
            ]);

            // Отправляем уведомление админу
            $this->notifyAdminAboutNewOrder($order);

            return $order;
        } catch (Exception $e) {
            Log::error('Failed to submit payment proof', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'source' => 'order'
            ]);
            throw $e;
        }
    }

    /**
     * Одобрить заказ и выдать пакет
     *
     * @param int $orderId
     * @return Order
     * @throws Exception
     */
    public function approve(int $orderId): Order
    {
        try {
            $order = Order::with(['pack', 'salesman'])->findOrFail($orderId);

            if (!$order->canBeApproved()) {
                throw new RuntimeException('Заказ не может быть одобрен в текущем статусе');
            }

            // Создаем пакет для продавца
            $packSalesmanDto = $this->packSalesmanService->create(
                $order->pack_id,
                $order->salesman_id,
                PackSalesman::PAID
            );

            // Получаем созданный пакет (используем ID из DTO)
            $packSalesman = PackSalesman::findOrFail($packSalesmanDto->id);

            // Вызываем success() для генерации ключей
            // Метод success() установит статус в PAID и вызовет successPaid() для генерации ключей
            // Это необходимо, так как при создании PackSalesman ключи не генерируются автоматически
            $this->packSalesmanService->success($packSalesman->id);
            
            // Обновляем packSalesman после вызова success() (на случай если статус изменился)
            $packSalesman->refresh();

            // Обновляем заказ
            $order->status = Order::STATUS_APPROVED;
            $order->pack_salesman_id = $packSalesman->id;
            $order->save();
            
            // Перезагружаем заказ для получения актуальных данных
            $order->refresh();

            Log::info('Order approved', [
                'order_id' => $orderId,
                'pack_salesman_id' => $packSalesman->id,
                'source' => 'order'
            ]);
            
            // Отправляем уведомление продавцу
            /** @var Order $order */
            $this->notifySalesmanAboutApproval($order);

            /** @var Order $order */
            return $order;
        } catch (Exception $e) {
            Log::error('Failed to approve order', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'source' => 'order'
            ]);
            throw $e;
        }
    }

    /**
     * Отклонить заказ
     *
     * @param int $orderId
     * @param string|null $comment
     * @return Order
     * @throws Exception
     */
    public function reject(int $orderId, ?string $comment = null): Order
    {
        try {
            /** @var Order $order */
            $order = Order::with(['salesman'])->findOrFail($orderId);

            if (!$order->canBeRejected()) {
                throw new RuntimeException('Заказ не может быть отклонен в текущем статусе');
            }

            $order->status = Order::STATUS_REJECTED;
            $order->admin_comment = $comment;
            $order->save();

            Log::info('Order rejected', [
                'order_id' => $orderId,
                'comment' => $comment,
                'source' => 'order'
            ]);

            // Перезагружаем заказ для получения актуальных данных
            $order->refresh();
            
            // Отправляем уведомление продавцу
            /** @var Order $order */
            $this->notifySalesmanAboutRejection($order);

            /** @var Order $order */
            return $order;
        } catch (Exception $e) {
            Log::error('Failed to reject order', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'source' => 'order'
            ]);
            throw $e;
        }
    }

    /**
     * Отменить заказ
     *
     * @param int $orderId
     * @return Order
     * @throws Exception
     */
    public function cancel(int $orderId): Order
    {
        try {
            $order = Order::findOrFail($orderId);

            if (!$order->canBeCancelled()) {
                throw new RuntimeException('Заказ не может быть отменен в текущем статусе');
            }

            $order->status = Order::STATUS_CANCELLED;
            $order->save();

            Log::info('Order cancelled', [
                'order_id' => $orderId,
                'source' => 'order'
            ]);

            return $order;
        } catch (Exception $e) {
            Log::error('Failed to cancel order', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'source' => 'order'
            ]);
            throw $e;
        }
    }

    /**
     * Получить доступные пакеты для заказа
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAvailablePacks()
    {
        return PackOrderSetting::with('pack')
            ->where('is_available', true)
            ->whereHas('pack', function($query) {
                $query->where('status', Pack::ACTIVE)
                      ->whereNull('deleted_at');
            })
            ->orderBy('sort_order')
            ->orderBy('id') // Вторичная сортировка для стабильного порядка при одинаковых sort_order
            ->get()
            ->pluck('pack')
            ->filter();
    }

    /**
     * Получить активные способы оплаты
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    public function getActivePaymentMethods()
    {
        return PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id') // Вторичная сортировка для стабильного порядка при одинаковых sort_order
            ->get();
    }

    /**
     * Уведомить админа о новом заказе
     */
    private function notifyAdminAboutNewOrder(Order $order): void
    {
        try {
            $adminTelegramId = OrderSetting::getValue('notification_telegram_id');
            if (!$adminTelegramId) {
                return;
            }

            $order->load(['pack', 'salesman', 'paymentMethod']);

            $message = "🆕 <b>Новый заказ на покупку пакета</b>\n\n";
            $message .= "📦 Пакет: {$order->pack->title}\n";
            $message .= "💰 Сумма: " . number_format($order->amount, 0, '.', ' ') . " ₽\n";
            $message .= "👤 Продавец: @{$order->salesman->username}\n";
            $message .= "💳 Способ оплаты: {$order->paymentMethod->name}\n";
            $message .= "🆔 ID заказа: #{$order->id}\n\n";
            $message .= "Проверьте заказ в админ-панели.";

            $telegram = new Api(config('telegram.father_bot.token'));
            $telegram->sendMessage([
                'chat_id' => $adminTelegramId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to notify admin about new order', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'source' => 'order'
            ]);
        }
    }

    /**
     * Уведомить продавца об одобрении заказа
     */
    private function notifySalesmanAboutApproval(Order $order): void
    {
        try {
            $order->load(['pack', 'packSalesman']);

            $message = "✅ <b>Ваш заказ одобрен!</b>\n\n";
            $message .= "📦 Пакет: {$order->pack->title}\n";
            $message .= "🔑 Количество ключей: {$order->pack->count}\n";
            $message .= "⏱ Период действия: {$order->pack->period} дней\n\n";
            $message .= "Ваш пакет готов к использованию!";

            $telegram = new Api(config('telegram.father_bot.token'));
            $telegram->sendMessage([
                'chat_id' => $order->salesman->telegram_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to notify salesman about approval', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'source' => 'order'
            ]);
        }
    }

    /**
     * Уведомить продавца об отклонении заказа
     */
    private function notifySalesmanAboutRejection(Order $order): void
    {
        try {
            $order->load(['pack']);

            $message = "❌ <b>Ваш заказ отклонен</b>\n\n";
            $message .= "📦 Пакет: {$order->pack->title}\n";
            if ($order->admin_comment) {
                $message .= "💬 Комментарий: {$order->admin_comment}\n\n";
            }
            $message .= "Если у вас есть вопросы, обратитесь к администратору.";

            $telegram = new Api(config('telegram.father_bot.token'));
            $telegram->sendMessage([
                'chat_id' => $order->salesman->telegram_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to notify salesman about rejection', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'source' => 'order'
            ]);
        }
    }
}

