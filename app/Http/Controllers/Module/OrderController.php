<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\OrderSetting\OrderSetting;
use App\Models\Pack\Pack;
use App\Models\PackOrderSetting\PackOrderSetting;
use App\Models\PaymentMethod\PaymentMethod;
use App\Services\Order\OrderService;
use App\Logging\DatabaseLogger;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    private OrderService $orderService;
    private DatabaseLogger $logger;

    public function __construct(OrderService $orderService, DatabaseLogger $logger)
    {
        $this->orderService = $orderService;
        $this->logger = $logger;
    }

    /**
     * Список заказов (главная страница с вкладками)
     */
    public function index(Request $request): View
    {
        try {
            $currentTab = $request->get('tab', 'orders');
            
            // Данные для вкладки "Заказы"
            $orders = collect();
            if ($currentTab === 'orders') {
                $query = Order::with([
                    'pack' => function($q) {
                        $q->withTrashed(); // Включаем удаленные пакеты
                    },
                    'salesman', 
                    'paymentMethod', 
                    'packSalesman'
                ])
                    ->orderBy('created_at', 'desc');

                // Фильтры
                if ($request->filled('status')) {
                    $query->where('status', $request->status);
                }

                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function($q) use ($search) {
                        $q->where('id', 'LIKE', "%{$search}%")
                          ->orWhereHas('salesman', function($q) use ($search) {
                              $q->where('username', 'LIKE', "%{$search}%")
                                ->orWhere('telegram_id', 'LIKE', "%{$search}%");
                          })
                          ->orWhereHas('pack', function($q) use ($search) {
                              $q->where('title', 'LIKE', "%{$search}%");
                          });
                    });
                }

                $orders = $query->paginate(20)->withQueryString();
            }

            // Данные для вкладки "Способы оплаты"
            $paymentMethods = collect();
            if ($currentTab === 'payment-methods') {
                $paymentMethods = PaymentMethod::orderBy('sort_order')
                    ->orderBy('id') // Вторичная сортировка для стабильного порядка при одинаковых sort_order
                    ->get();
            }

            // Данные для вкладки "Настройки"
            $systemEnabled = false;
            $notificationTelegramId = null;
            $packs = collect();
            $packSettings = collect();
            if ($currentTab === 'settings') {
                $systemEnabled = OrderSetting::isSystemEnabled();
                $notificationTelegramId = OrderSetting::getValue('notification_telegram_id');

                // Получаем все пакеты с настройками доступности
                $packs = Pack::where('status', Pack::ACTIVE)
                    ->whereNull('deleted_at')
                    ->orderBy('title')
                    ->get();

                $packSettings = PackOrderSetting::whereIn('pack_id', $packs->pluck('id'))
                    ->get()
                    ->keyBy('pack_id');
            }

            return view('module.order.index', compact(
                'currentTab',
                'orders',
                'paymentMethods',
                'systemEnabled',
                'notificationTelegramId',
                'packs',
                'packSettings'
            ));
        } catch (Exception $e) {
            $this->logger->error('Error loading orders', [
                'error' => $e->getMessage(),
                'source' => 'admin'
            ]);
            return view('module.order.index', [
                'currentTab' => 'orders',
                'orders' => collect(),
                'paymentMethods' => collect(),
                'systemEnabled' => false,
                'notificationTelegramId' => null,
                'packs' => collect(),
                'packSettings' => collect()
            ]);
        }
    }

    /**
     * Просмотр заказа
     * 
     * @param int $id
     * @return View|RedirectResponse
     */
    public function show(int $id)
    {
        try {
            $order = Order::with([
                'pack' => function($q) {
                    $q->withTrashed(); // Включаем удаленные пакеты
                },
                'salesman', 
                'paymentMethod', 
                'packSalesman'
            ])
                ->findOrFail($id);

            return view('module.order.show', compact('order'));
        } catch (Exception $e) {
            $this->logger->error('Error loading order', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'source' => 'admin'
            ]);
            return redirect()->route('admin.module.order.index')
                ->with('error', 'Заказ не найден');
        }
    }

    /**
     * Одобрить заказ
     */
    public function approve(int $id): RedirectResponse
    {
        try {
            $this->orderService->approve($id);

            $this->logger->info('Order approved', [
                'order_id' => $id,
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.show', $id)
                ->with('success', 'Заказ одобрен, пакет выдан продавцу');
        } catch (Exception $e) {
            $this->logger->error('Error approving order', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.show', $id)
                ->with('error', 'Ошибка при одобрении заказа: ' . $e->getMessage());
        }
    }

    /**
     * Отклонить заказ
     */
    public function reject(Request $request, int $id): RedirectResponse
    {
        try {
            $request->validate([
                'comment' => 'nullable|string|max:1000'
            ]);

            $this->orderService->reject($id, $request->comment);

            $this->logger->info('Order rejected', [
                'order_id' => $id,
                'comment' => $request->comment,
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.show', $id)
                ->with('success', 'Заказ отклонен');
        } catch (Exception $e) {
            $this->logger->error('Error rejecting order', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.show', $id)
                ->with('error', 'Ошибка при отклонении заказа: ' . $e->getMessage());
        }
    }

    /**
     * Удалить заказ
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $order = Order::findOrFail($id);

            // Проверяем, можно ли удалить заказ
            // Нельзя удалять одобренные заказы, так как они уже связаны с пакетами
            if ($order->status == Order::STATUS_APPROVED) {
                return redirect()->route('admin.module.order.show', $id)
                    ->with('error', 'Нельзя удалить одобренный заказ, так как пакет уже выдан продавцу.');
            }

            $orderId = $order->id;
            $order->delete();

            $this->logger->info('Order deleted', [
                'order_id' => $orderId,
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.index')
                ->with('success', 'Заказ #' . $orderId . ' удален');
        } catch (Exception $e) {
            $this->logger->error('Error deleting order', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.show', $id)
                ->with('error', 'Ошибка при удалении заказа: ' . $e->getMessage());
        }
    }
}

