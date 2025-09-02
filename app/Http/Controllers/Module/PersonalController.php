<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\KeyActivate\KeyActivate;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Bot\BotModuleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PersonalController extends Controller
{
    /**
     * @var BotModuleService
     */
    protected BotModuleService $botModuleService;

    public function __construct(BotModuleService $botModuleService)
    {
        $this->botModuleService = $botModuleService;
    }

    /**
     * @return Application|Factory|View
     */
    public function dashboard()
    {
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        // Получаем все pack_salesman_id для данного продавца
        $packSalesmanIds = $salesman->packSales()->pluck('id');

        //Общее количество ключей
        $totalKeys = KeyActivate::whereIn('pack_salesman_id', $packSalesmanIds)->count();

        //Активные ключи
        $activeKeys = KeyActivate::whereIn('pack_salesman_id', $packSalesmanIds)
            ->where('status', KeyActivate::ACTIVE)
            ->where(function ($query) {
                $query->whereNull('finish_at')
                    ->orWhere('finish_at', '>', Carbon::now()->timestamp);
            })
            ->count();

        //Проданные ключи
        $soldKeys = KeyActivate::whereIn('pack_salesman_id', $packSalesmanIds)
            ->whereNotNull('user_tg_id')
            ->count();

        // График продаж за 7 дней
        $salesData = KeyActivate::whereIn('pack_salesman_id', $packSalesmanIds)
            ->whereNotNull('user_tg_id')
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Заполняем пропущенные дни нулями
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $chartData[$date] = $salesData[$date] ?? 0;
        }

        $totalEarnings = $salesman->packSales()
            ->where('status', PackSalesman::PAID)
            ->with('pack')
            ->get()
            ->sum(function ($packSales) {
                return $packSales->pack->price ?? 0;
            });

        $recentSales = KeyActivate::whereIn('pack_salesman_id', $packSalesmanIds)
            ->where('status', KeyActivate::PAID)
            ->where(function ($query) {
                $query->whereNull('finish_at')
                    ->orWhere('finish_at', '>', Carbon::now()->timestamp);
            })
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get();

        return view('module.personal.dashboard', compact(
            'salesman',
            'totalKeys',
            'activeKeys',
            'soldKeys',
            'totalEarnings',
            'recentSales',
            'chartData'
        ));
    }

    /**
     * @return Application|Factory|View
     */
    public function keys(Request $request)
    {
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        // Получаем ключи, где текущий продавец является продавцом
        $salesmanKeysQuery = $salesman->keyActivates();

        // Получаем ключи где текущий продавец является покупателем и pack имеет module_key = 1
        $buyerKeysQuery = KeyActivate::where('key_activate.user_tg_id', $salesman->telegram_id)
            ->whereHas('packSalesman.pack', function($q) {
                $q->where('pack.module_key', 1);
            });

        // Объединяем два запроса
        $query = KeyActivate::query()
            ->with([
                'packSalesman.pack',
                'packSalesman.salesman', // продавец этого ключа
                'keyActivateUser.serverUser.panel',
                'user'
            ])
            ->where(function($q) use ($salesmanKeysQuery, $buyerKeysQuery) {
                $q->whereIn('key_activate.id', $salesmanKeysQuery->select('key_activate.id'))
                    ->orWhereIn('key_activate.id', $buyerKeysQuery->select('key_activate.id'));
            });

//        $query = $salesman->keyActivates()
//            ->with([
//                'packSalesman.pack',
//                'keyActivateUser.serverUser.panel',
//                'user'
//            ]);

        // Применяем фильтры с указанием таблицы для status
        if ($request->has('key_search') && !empty($request->key_search)) {
            $query->where('key_activate.id', 'like', '%' . $request->key_search . '%');
        }

        if ($request->has('telegram_search') && !empty($request->telegram_search)) {
            $query->where('key_activate.user_tg_id', 'like', '%' . $request->telegram_search . '%');
        }

        if ($request->has('status_filter') && !empty($request->status_filter)) {
            $query->where('key_activate.status', $request->status_filter);
        }

        if ($request->has('expiry_filter') && !empty($request->expiry_filter)) {
            if ($request->expiry_filter === 'active') {
                $query->where(function($q) {
                    $q->whereNull('key_activate.finish_at')
                        ->orWhere('key_activate.finish_at', '>', Carbon::now()->timestamp);
                });
            } elseif ($request->expiry_filter === 'expired') {
                $query->where('key_activate.finish_at', '<=', Carbon::now()->timestamp);
            }
        }

        $keys = $query->orderBy('key_activate.created_at', 'desc')->paginate(15);

        $statuses = [
            '' => 'Все статусы',
            KeyActivate::PAID => 'Оплачен',
            KeyActivate::ACTIVE => 'Активирован',
            KeyActivate::EXPIRED => 'Просрочен',
            KeyActivate::DELETED => 'Удален'
        ];

        return view('module.personal.keys', compact(
            'salesman',
            'keys',
            'statuses'
        ));
    }

    /**
     * @return Application|Factory|View
     */
    public function stats()
    {
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        // Статистика продаж по месяцам
//        $salesStats = $salesman->packSales()
//            ->where('status', PackSalesman::PAID)
//            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total, COUNT(*) as count')
//            ->groupBy('year', 'month')
//            ->orderBy('year', 'desc')
//            ->orderBy('month', 'desc')
//            ->get();

        // Популярные пакеты
//        $popularPacks = $salesman->packSales()
//            ->where('status', PackSalesman::PAID)
//            ->with('pack')
//            ->selectRaw('pack_id, COUNT(*) as count, SUM(amount) as total')
//            ->groupBy('pack_id')
//            ->orderBy('count', 'desc')
//            ->limit(5)
//            ->get();

        // Общая статистика
//        $totalSales = $salesman->packSales()
//            ->where('status', PackSalesman::PAID)
//            ->count();
//        $totalAmount = $salesman->packSales()
//            ->where('status', PackSalesman::PAID)
//            ->sum('amount');

        return view('module.personal.stats', compact(
            'salesman',
//            'salesStats',
//            'popularPacks',
//            'totalSales',
//            'totalAmount'
        ));
    }

    /**
     * @return Application|Factory|View
     */
    public function packages()
    {
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        // История покупок пакетов
        $purchasedPacks = $salesman->packSales()
            ->with('pack')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('module.personal.packages', compact(
            'salesman',
            'purchasedPacks'
        ));
    }

    /**
     * @return Application|Factory|View
     */
    public function faq()
    {
//        $salesman = Auth::guard('salesman')->user();
        $salesman = Salesman::where('telegram_id', 6715142449)->first();
        $module = $salesman->botModule;
        $bot = $salesman->bot_link;

        return view('module.personal.faq', [
            'salesman' => $salesman,
            'module' => $module,
            'currentInstructions' => $module->vpn_instructions ?? $this->botModuleService->getDefaultVpnInstructions(),
            'defaultInstructions' => $this->botModuleService->getDefaultVpnInstructions(),
            'hasModule' => $module !== null,
            'hasBot' => $bot !== null
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateVpnInstructions(Request $request)
    {
        $request->validate(['instructions' => 'required|string']);

        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        $salesman->botModule->update(['vpn_instructions' => $request->instructions]);

        return redirect()->back()->with('success', 'Инструкции успешно обновлены!');
    }

    /**
     * @return RedirectResponse
     */
    public function resetVpnInstructions()
    {
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        $salesman->botModule->update([
            'vpn_instructions' => $this->botModuleService->getDefaultVpnInstructions()
        ]);

        return redirect()->back()->with('success', 'Инструкции сброшены к стандартным!');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateFaq(Request $request)
    {
        $request->validate([
            'help_text' => 'required|string|max:4000'
        ]);

        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        $salesman->update([
            'custom_help_text' => $request->help_text
        ]);

        return redirect()->route('personal.faq')->with('success', 'Текст FAQ успешно обновлен!');
    }

    /**
     * @return RedirectResponse
     */
    public function resetFaq()
    {
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        $salesman->update([
            'custom_help_text' => null
        ]);

        return redirect()->route('personal.faq')->with('success', 'Текст FAQ сброшен к стандартному!');
    }
}
