<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\OrderSetting\OrderSetting;
use App\Models\Pack\Pack;
use App\Models\PackOrderSetting\PackOrderSetting;
use App\Logging\DatabaseLogger;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderSettingsController extends Controller
{
    private DatabaseLogger $logger;

    public function __construct(DatabaseLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Настройки системы заказов (редирект на главную страницу с вкладками)
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.module.order.index', ['tab' => 'settings']);
    }

    /**
     * Обновить настройки
     */
    public function update(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'system_enabled' => 'boolean',
                'notification_telegram_id' => 'nullable|string|max:255',
                'pack_availability' => 'nullable|array',
                'pack_sort_order' => 'nullable|array'
            ]);

            // Обновляем статус системы
            OrderSetting::setSystemEnabled($request->has('system_enabled'));

            // Обновляем Telegram ID для уведомлений (всегда обновляем, даже если пустое, чтобы можно было очистить)
            $telegramId = $request->input('notification_telegram_id', '');
            OrderSetting::setValue('notification_telegram_id', $telegramId ?: null);

            // Обновляем доступность пакетов
            if ($request->has('pack_availability')) {
                foreach ($request->pack_availability as $packId => $isAvailable) {
                    PackOrderSetting::updateOrCreate(
                        ['pack_id' => $packId],
                        [
                            'is_available' => (bool)$isAvailable,
                            'sort_order' => $request->pack_sort_order[$packId] ?? 0
                        ]
                    );
                }
            }

            $this->logger->info('Order settings updated', [
                'source' => 'admin'
            ]);

            $tab = $request->get('tab', 'settings');
            return redirect()->route('admin.module.order.index', ['tab' => $tab])
                ->with('success', 'Настройки сохранены');
        } catch (Exception $e) {
            $this->logger->error('Error updating order settings', [
                'error' => $e->getMessage(),
                'source' => 'admin'
            ]);

            return redirect()->route('admin.module.order.index', ['tab' => 'settings'])
                ->with('error', 'Ошибка при сохранении настроек: ' . $e->getMessage());
        }
    }
}

