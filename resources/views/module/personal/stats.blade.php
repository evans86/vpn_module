@extends('module.personal.layouts.app')

@section('title', 'Статистика продаж')

@section('content')

    <div class="px-4 py-6 sm:px-0">
        <div class="flex flex-col items-center justify-center min-h-[60vh]">
            <div class="text-center max-w-2xl mx-auto">
                <!-- Большая надпись "Скоро!" -->
                <h1 class="text-5xl md:text-6xl font-bold text-indigo-600 mb-6 animate-pulse">
                    Скоро!
                </h1>

                <!-- Описание -->
                <p class="text-xl text-gray-600 mb-8">
                    Раздел статистики продаж находится в разработке и будет доступен в ближайшее время
                </p>

                <!-- Кнопка возврата (опционально) -->
                <a href="{{ url()->previous() }}"
                   class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Вернуться назад
                </a>
            </div>
        </div>
    </div>


{{--    <div class="px-4 py-6 sm:px-0">--}}
{{--        <div class="mb-6">--}}
{{--            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">--}}
{{--                Статистика продаж--}}
{{--            </h2>--}}
{{--            <p class="mt-2 text-sm text-gray-500">--}}
{{--                Анализ ваших продаж и ключевые показатели--}}
{{--            </p>--}}
{{--        </div>--}}

{{--        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">--}}
{{--            <div class="bg-white shadow rounded-lg p-6">--}}
{{--                <h3 class="text-lg font-medium text-gray-900 mb-4">Продажи по месяцам</h3>--}}
{{--                <div class="h-64">--}}
{{--                    <!-- График продаж по месяцам -->--}}
{{--                    <div class="flex items-center justify-center h-full bg-gray-50 rounded">--}}
{{--                        <p class="text-gray-500">График продаж по месяцам</p>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}

{{--            <div class="bg-white shadow rounded-lg p-6">--}}
{{--                <h3 class="text-lg font-medium text-gray-900 mb-4">Популярные пакеты</h3>--}}
{{--                <div class="space-y-4">--}}
{{--                    @foreach($popularPacks as $pack)--}}
{{--                        <div>--}}
{{--                            <div class="flex justify-between text-sm mb-1">--}}
{{--                                <span class="font-medium">Пакет {{ $pack->pack_id }}</span>--}}
{{--                                <span>{{ $pack->count }} продаж</span>--}}
{{--                            </div>--}}
{{--                            <div class="w-full bg-gray-200 rounded-full h-2">--}}
{{--                                <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ ($pack->count / max($popularPacks->max('count'), 1)) * 100 }}%"></div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    @endforeach--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}

{{--        <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">--}}
{{--            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">--}}
{{--                <h3 class="text-lg leading-6 font-medium text-gray-900">--}}
{{--                    История продаж--}}
{{--                </h3>--}}
{{--                <p class="mt-1 max-w-2xl text-sm text-gray-500">--}}
{{--                    Подробная информация о всех продажах--}}
{{--                </p>--}}
{{--            </div>--}}
{{--            <div class="overflow-x-auto">--}}
{{--                <table class="min-w-full divide-y divide-gray-200">--}}
{{--                    <thead class="bg-gray-50">--}}
{{--                    <tr>--}}
{{--                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>--}}
{{--                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пакет</th>--}}
{{--                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ключ</th>--}}
{{--                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>--}}
{{--                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>--}}
{{--                    </tr>--}}
{{--                    </thead>--}}
{{--                    <tbody class="bg-white divide-y divide-gray-200">--}}
{{--                    @foreach($salesStats as $sale)--}}
{{--                        <tr>--}}
{{--                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">--}}
{{--                                {{ Carbon\Carbon::create($sale->year, $sale->month)->format('m.Y') }}--}}
{{--                            </td>--}}
{{--                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Пакет {{ $sale->pack_id ?? 'N/A' }}</td>--}}
{{--                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">VPN-XXXX-YYYY-ZZZZ</td>--}}
{{--                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($sale->total, 2) }} ₽</td>--}}
{{--                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">--}}
{{--                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">--}}
{{--                                    Оплачено--}}
{{--                                </span>--}}
{{--                            </td>--}}
{{--                        </tr>--}}
{{--                    @endforeach--}}
{{--                    </tbody>--}}
{{--                </table>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
@endsection
