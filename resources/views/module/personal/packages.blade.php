@extends('module.personal.layouts.app')

@section('title', 'Пакеты ключей')

@section('content')
    <div class="px-4 py-6 sm:px-0">
{{--        <div class="mb-6">--}}
{{--            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">--}}
{{--                Пакеты ключей--}}
{{--            </h2>--}}
{{--            <p class="mt-2 text-sm text-gray-500">--}}
{{--                Доступные пакеты для покупки и их стоимость--}}
{{--            </p>--}}
{{--        </div>--}}

{{--        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">--}}
{{--            <!-- Пакет на 1 месяц -->--}}
{{--            <div class="bg-white overflow-hidden shadow rounded-lg">--}}
{{--                <div class="px-4 py-5 sm:p-6">--}}
{{--                    <div class="flex items-center">--}}
{{--                        <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">--}}
{{--                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />--}}
{{--                            </svg>--}}
{{--                        </div>--}}
{{--                        <div class="ml-5 w-0 flex-1">--}}
{{--                            <dl>--}}
{{--                                <dt class="text-sm font-medium text-gray-500 truncate">1 месяц</dt>--}}
{{--                                <dd class="flex items-baseline">--}}
{{--                                    <div class="text-2xl font-semibold text-gray-900">299 ₽</div>--}}
{{--                                </dd>--}}
{{--                            </dl>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <div class="bg-gray-50 px-4 py-4 sm:px-6">--}}
{{--                    <div class="text-sm">--}}
{{--                        <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">--}}
{{--                            Купить пакет<span class="sr-only"> 1 месяц</span>--}}
{{--                        </a>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}

{{--            <!-- Пакет на 3 месяца -->--}}
{{--            <div class="bg-white overflow-hidden shadow rounded-lg">--}}
{{--                <div class="px-4 py-5 sm:p-6">--}}
{{--                    <div class="flex items-center">--}}
{{--                        <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">--}}
{{--                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />--}}
{{--                            </svg>--}}
{{--                        </div>--}}
{{--                        <div class="ml-5 w-0 flex-1">--}}
{{--                            <dl>--}}
{{--                                <dt class="text-sm font-medium text-gray-500 truncate">3 месяца</dt>--}}
{{--                                <dd class="flex items-baseline">--}}
{{--                                    <div class="text-2xl font-semibold text-gray-900">799 ₽</div>--}}
{{--                                    <div class="ml-2 text-xs font-semibold text-green-800">-10%</div>--}}
{{--                                </dd>--}}
{{--                            </dl>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <div class="bg-gray-50 px-4 py-4 sm:px-6">--}}
{{--                    <div class="text-sm">--}}
{{--                        <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">--}}
{{--                            Купить пакет<span class="sr-only"> 3 месяца</span>--}}
{{--                        </a>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}

{{--            <!-- Пакет на 6 месяцев -->--}}
{{--            <div class="bg-white overflow-hidden shadow rounded-lg">--}}
{{--                <div class="px-4 py-5 sm:p-6">--}}
{{--                    <div class="flex items-center">--}}
{{--                        <div class="flex-shrink-0 bg-pink-500 rounded-md p-3">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">--}}
{{--                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />--}}
{{--                            </svg>--}}
{{--                        </div>--}}
{{--                        <div class="ml-5 w-0 flex-1">--}}
{{--                            <dl>--}}
{{--                                <dt class="text-sm font-medium text-gray-500 truncate">6 месяцев</dt>--}}
{{--                                <dd class="flex items-baseline">--}}
{{--                                    <div class="text-2xl font-semibold text-gray-900">1,499 ₽</div>--}}
{{--                                    <div class="ml-2 text-xs font-semibold text-green-800">-16%</div>--}}
{{--                                </dd>--}}
{{--                            </dl>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <div class="bg-gray-50 px-4 py-4 sm:px-6">--}}
{{--                    <div class="text-sm">--}}
{{--                        <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">--}}
{{--                            Купить пакет<span class="sr-only"> 6 месяцев</span>--}}
{{--                        </a>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}

{{--            <!-- Пакет на 12 месяцев -->--}}
{{--            <div class="bg-white overflow-hidden shadow rounded-lg">--}}
{{--                <div class="px-4 py-5 sm:p-6">--}}
{{--                    <div class="flex items-center">--}}
{{--                        <div class="flex-shrink-0 bg-red-500 rounded-md p-3">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">--}}
{{--                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />--}}
{{--                            </svg>--}}
{{--                        </div>--}}
{{--                        <div class="ml-5 w-0 flex-1">--}}
{{--                            <dl>--}}
{{--                                <dt class="text-sm font-medium text-gray-500 truncate">12 месяцев</dt>--}}
{{--                                <dd class="flex items-baseline">--}}
{{--                                    <div class="text-2xl font-semibold text-gray-900">2,699 ₽</div>--}}
{{--                                    <div class="ml-2 text-xs font-semibold text-green-800">-25%</div>--}}
{{--                                </dd>--}}
{{--                            </dl>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <div class="bg-gray-50 px-4 py-4 sm:px-6">--}}
{{--                    <div class="text-sm">--}}
{{--                        <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">--}}
{{--                            Купить пакет<span class="sr-only"> 12 месяцев</span>--}}
{{--                        </a>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}

        <div class="mt-8 bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    История покупок пакетов
                </h3>

            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пакет</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Количество ключей</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($purchasedPacks as $package)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $package->created_at->format('d.m.Y H:i') }}</td>
{{--                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $package->pack->name ?? 'Пакет' }}</td>--}}
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $package->quantity }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($package->amount, 2) }} ₽</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $package->is_paid ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $package->is_paid ? 'Оплачено' : 'Ожидает оплаты' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
{{--            <div class="px-4 py-4 bg-gray-50 border-t border-gray-200">--}}
{{--                {{ $purchasedPacks->links() }}--}}
{{--            </div>--}}
        </div>
    </div>
@endsection
