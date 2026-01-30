<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\KeyActivate\KeyActivate;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use App\Services\External\BottApi;
use App\Services\Key\KeyActivateService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Bot\BotModuleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PersonalController extends Controller
{
    /**
     * @var BotModuleService
     */
    protected BotModuleService $botModuleService;

    public function __construct(
        BotModuleService $botModuleService
    )
    {
        $this->botModuleService = $botModuleService;
    }

    /**
     * @return Application|Factory|View
     * @throws GuzzleException
     */
    public function dashboard()
    {
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();
         $salesman = Auth::guard('salesman')->user();

        // Проверяем наличие бота и модуля
        $hasBot = $salesman->bot_link !== null;
        $hasModule = $salesman->botModule !== null;

        if ($hasModule) {
            $botModule = BottApi::getBot($salesman->botModule->public_key);
            if ($botModule['result']) {
                $botModuleLink = 'https://t.me/' . $botModule['data']['name'];
            } else {
                $botModuleLink = null;
            }
        } else {
            $botModuleLink = null;
        }

        // Ключи из модуля (прямая связь)
        $moduleKeysQuery = $salesman->moduleKeyActivates();

        // Ключи из бота (через pack_salesman)
        $botKeysQuery = $salesman->botKeyActivates();

        // Общее количество ключей (оба типа)
        $totalKeys = $botKeysQuery->count() + $moduleKeysQuery->count();

        // Активные ключи (оба типа)
        $activeBotKeys = $botKeysQuery->where('key_activate.status', KeyActivate::ACTIVE)
            ->where(function ($query) {
                $query->whereNull('key_activate.finish_at')
                    ->orWhere('key_activate.finish_at', '>', Carbon::now()->timestamp);
            })->count();

        $activeModuleKeys = $moduleKeysQuery->where('status', KeyActivate::ACTIVE)
            ->where(function ($query) {
                $query->whereNull('finish_at')
                    ->orWhere('finish_at', '>', Carbon::now()->timestamp);
            })->count();

        $activeKeys = $activeBotKeys + $activeModuleKeys;

        // Ключи проданные в боте (активированные)
        $botSoldKeys = $botKeysQuery->whereNotNull('key_activate.user_tg_id')->count();

        // Ключи в модуле (все, так как они уже проданы продавцу)
        $moduleSoldKeys = $moduleKeysQuery->count();

        // График продаж в боте за 7 дней - ИСПРАВЛЕННЫЙ ЗАПРОС
        $botSalesData = DB::table('key_activate')
            ->join('pack_salesman', 'pack_salesman.id', '=', 'key_activate.pack_salesman_id')
            ->where('pack_salesman.salesman_id', $salesman->id)
            ->whereNotNull('key_activate.user_tg_id')
            ->where('key_activate.updated_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('DATE(key_activate.updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // График покупок в модуле за 7 дней - ИСПРАВЛЕННЫЙ ЗАПРОС
        $moduleSalesData = DB::table('key_activate')
            ->where('module_salesman_id', $salesman->id)
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Заполняем пропущенные дни нулями для обоих графиков
        $botChartData = [];
        $moduleChartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $botChartData[$date] = $botSalesData[$date] ?? 0;
            $moduleChartData[$date] = $moduleSalesData[$date] ?? 0;
        }

        // Оптимизация: используем агрегацию БД вместо загрузки всех записей
        $totalEarnings = DB::table('pack_salesman')
            ->join('pack', 'pack_salesman.pack_id', '=', 'pack.id')
            ->where('pack_salesman.salesman_id', $salesman->id)
            ->where('pack_salesman.status', PackSalesman::PAID)
            ->sum('pack.price');

        // Ключи из бота
        $botSales = $botKeysQuery
            ->with(['packSalesman.pack'])
            ->whereIn('key_activate.status', [KeyActivate::ACTIVE, KeyActivate::PAID])
            ->where(function ($query) {
                $query->whereNull('key_activate.finish_at')
                    ->orWhere('key_activate.finish_at', '>', Carbon::now()->timestamp);
            })
            ->orderBy('key_activate.updated_at', 'desc')
            ->take(7)
            ->get()
            ->map(function ($sale) {
                $sale->operation_type = 'bot_sale';
                return $sale;
            });

        // Ключи из модуля
        $modulePurchases = $moduleKeysQuery
            ->with(['packSalesman.pack'])
            ->whereIn('status', [KeyActivate::ACTIVE, KeyActivate::PAID])
            ->where(function ($query) {
                $query->whereNull('finish_at')
                    ->orWhere('finish_at', '>', Carbon::now()->timestamp);
            })
            ->orderBy('updated_at', 'desc')
            ->take(7)
            ->get()
            ->map(function ($sale) {
                $sale->operation_type = 'module_purchase';
                return $sale;
            });

        // Объединяем и сортируем по дате, берем 7 последних
        $recentSales = $botSales->merge($modulePurchases)
            ->sortByDesc('updated_at')
            ->take(7);

        return view('module.personal.dashboard', compact(
            'salesman',
            'totalKeys',
            'activeKeys',
            'botSoldKeys',
            'moduleSoldKeys',
            'totalEarnings',
            'recentSales',
            'botChartData',
            'moduleChartData',
            'hasBot',
            'hasModule',
            'botModuleLink'
        ));
    }

    /**
     * @return Application|Factory|View
     */
    public function keys(Request $request)
    {
        $salesman = Auth::guard('salesman')->user();
//                $salesman = Salesman::where('telegram_id', 6715142449)->first();

        // Используем более эффективный подход с join вместо whereHas
        // Это избегает проблем с памятью и работает быстрее
        $query = KeyActivate::query()
            ->select([
                'key_activate.id',
                'key_activate.traffic_limit',
                'key_activate.pack_salesman_id',
                'key_activate.module_salesman_id',
                'key_activate.finish_at',
                'key_activate.user_tg_id',
                'key_activate.deleted_at',
                'key_activate.status',
                'key_activate.created_at',
                'key_activate.updated_at'
            ])
            ->leftJoin('pack_salesman', 'key_activate.pack_salesman_id', '=', 'pack_salesman.id')
            ->where(function ($q) use ($salesman) {
                // Ключи из модуля (прямая связь)
                $q->where('key_activate.module_salesman_id', $salesman->id)
                    // Ключи из бота (через pack_salesman)
                    ->orWhere('pack_salesman.salesman_id', $salesman->id);
            })
            ->distinct()
            ->with([
                'packSalesman' => function($q) {
                    $q->select('id', 'salesman_id', 'pack_id');
                },
                'packSalesman.pack' => function($q) {
                    $q->select('id', 'name', 'period', 'traffic_limit');
                },
                'user' => function($q) {
                    $q->select('telegram_id', 'username', 'first_name');
                }
            ]);

        // Применяем фильтры
        if ($request->has('key_search') && !empty($request->key_search)) {
            $query->where('id', 'like', '%' . $request->key_search . '%');
        }

        if ($request->has('telegram_search') && !empty($request->telegram_search)) {
            $query->where('user_tg_id', 'like', '%' . $request->telegram_search . '%');
        }

        if ($request->has('status_filter') && !empty($request->status_filter)) {
            $query->where('status', $request->status_filter);
        }

        if ($request->has('expiry_filter') && !empty($request->expiry_filter)) {
            if ($request->expiry_filter === 'active') {
                $query->where(function ($q) {
                    $q->whereNull('finish_at')
                        ->orWhere('finish_at', '>', Carbon::now()->timestamp);
                });
            } elseif ($request->expiry_filter === 'expired') {
                $query->where('finish_at', '<=', Carbon::now()->timestamp);
            }
        }

        if ($request->has('source_filter') && !empty($request->source_filter)) {
            if ($request->source_filter === 'module') {
                $query->whereNotNull('module_salesman_id');
            } elseif ($request->source_filter === 'bot') {
                $query->whereNotNull('pack_salesman_id');
            }
        }

        // Ограничиваем максимальное количество записей на странице для защиты от перегрузки памяти
        $perPage = min($request->get('per_page', 15), 50); // Максимум 50 записей на странице
        
        $keys = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $statuses = [
            '' => 'Все статусы',
            KeyActivate::PAID => 'Оплачен',
            KeyActivate::ACTIVE => 'Активирован',
            KeyActivate::EXPIRED => 'Просрочен',
            KeyActivate::DELETED => 'Удален'
        ];

        $sources = [
            '' => 'Все источники',
            'module' => 'Модуль VPN',
            'bot' => 'Telegram бот'
        ];

        return view('module.personal.keys', compact(
            'salesman',
            'keys',
            'statuses',
            'sources'
        ));
    }

//    /**
//     * @return Application|Factory|View
//     */
//    public function stats()
//    {
////        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();
//
//        // Статистика продаж по месяцам
////        $salesStats = $salesman->packSales()
////            ->where('status', PackSalesman::PAID)
////            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total, COUNT(*) as count')
////            ->groupBy('year', 'month')
////            ->orderBy('year', 'desc')
////            ->orderBy('month', 'desc')
////            ->get();
//
//        // Популярные пакеты
////        $popularPacks = $salesman->packSales()
////            ->where('status', PackSalesman::PAID)
////            ->with('pack')
////            ->selectRaw('pack_id, COUNT(*) as count, SUM(amount) as total')
////            ->groupBy('pack_id')
////            ->orderBy('count', 'desc')
////            ->limit(5)
////            ->get();
//
//        // Общая статистика
////        $totalSales = $salesman->packSales()
////            ->where('status', PackSalesman::PAID)
////            ->count();
////        $totalAmount = $salesman->packSales()
////            ->where('status', PackSalesman::PAID)
////            ->sum('amount');
//
//        return view('module.personal.stats', compact(
//            'salesman',
////            'salesStats',
////            'popularPacks',
////            'totalSales',
////            'totalAmount'
//        ));
//    }

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
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();
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
