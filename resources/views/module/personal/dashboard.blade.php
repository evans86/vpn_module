@extends('module.personal.layouts.app')

@section('title', 'Главная')

@section('content')
    <div class="px-4 py-6 sm:px-0">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Карточка общих ключей -->
            <div class="bg-white overflow-hidden shadow rounded-lg transition-all duration-200 hover:shadow-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-indigo-100 rounded-lg p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 truncate">Всего ключей</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalKeys }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Карточка активных ключей -->
            <div class="bg-white overflow-hidden shadow rounded-lg transition-all duration-200 hover:shadow-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 truncate">Активные ключи</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $activeKeys }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Карточка проданных ключей -->
            <div class="bg-white overflow-hidden shadow rounded-lg transition-all duration-200 hover:shadow-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 truncate">Проданные ключи</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $soldKeys }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Карточка заработка -->
{{--            <div class="bg-white overflow-hidden shadow rounded-lg transition-all duration-200 hover:shadow-md">--}}
{{--                <div class="px-4 py-5 sm:p-6">--}}
{{--                    <div class="flex items-center">--}}
{{--                        <div class="flex-shrink-0 bg-yellow-100 rounded-lg p-3">--}}
{{--                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">--}}
{{--                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />--}}
{{--                            </svg>--}}
{{--                        </div>--}}
{{--                        <div class="ml-4">--}}
{{--                            <dt class="text-sm font-medium text-gray-500 truncate">Заработок за месяц</dt>--}}
{{--                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($monthlyEarnings, 2) }} ₽</dd>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
        </div>

        <!-- Графики и дополнительная информация -->
        <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Продажи за последние 7 дней</h3>
                <div class="h-64">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Последние продажи</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ключ</th>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">

                        @foreach($recentSales as $recentSale)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $recentSale->updated_at->format('d.m.Y H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $recentSale->id }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Подключаем Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const chartData = @json($chartData);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: Object.keys(chartData),
                    datasets: [{
                        label: 'Продажи за день',
                        data: Object.values(chartData),
                        backgroundColor: 'rgba(79, 70, 229, 0.7)',
                        borderColor: 'rgba(79, 70, 229, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Продажи: ' + context.raw;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endsection
