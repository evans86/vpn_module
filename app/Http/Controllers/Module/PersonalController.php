<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PersonalController extends Controller
{
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
            ->where(function($query) {
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
            ->sum(function($packSales) {
                return $packSales->pack->price ?? 0;
            });

        $recentSales = KeyActivate::whereIn('pack_salesman_id', $packSalesmanIds)
            ->where('status', KeyActivate::PAID)
            ->where(function($query) {
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

    public function keys()
    {
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        $keys = $salesman->keyActivates()
            ->with(['packSalesman.pack', 'keyActivateUser.serverUser.panel'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $statuses = [
            KeyActivate::EXPIRED => 'Просрочен',
            KeyActivate::ACTIVE => 'Активирован',
            KeyActivate::PAID => 'Оплачен',
            KeyActivate::DELETED => 'Удален'
        ];

        return view('module.personal.keys', compact(
            'salesman',
            'keys',
            'statuses'
        ));
    }

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

    public function faq()
    {
        $salesman = Auth::guard('salesman')->user();
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();

        return view('module.personal.faq', compact('salesman'));
    }
}
