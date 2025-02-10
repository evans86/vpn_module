@extends('layouts.app', ['page' => __('Мониторинг нагрузки'), 'pageSlug' => 'server-monitoring'])

@section('content')
    <div class="container mx-auto px-6 py-12">
        <div class="bg-white rounded-lg shadow-lg p-6">
{{--            <h1 class="text-2xl font-bold mb-4">Мониторинг нагрузки сервера</h1>--}}

            @if(!empty($systemData))
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Версия сервера -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-gray-600">Версия сервера:</span>
                        <span class="ml-2 font-semibold">{{ $systemData['version'] }}</span>
                    </div>

                    <!-- Использование памяти -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-gray-600">Использование памяти:</span>
                        <span class="ml-2 font-semibold">{{ number_format($systemData['mem_used'] / 1024 / 1024, 2) }} MB / {{ number_format($systemData['mem_total'] / 1024 / 1024, 2) }} MB</span>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                            @php
                                $memoryUsagePercentage = ($systemData['mem_used'] / $systemData['mem_total']) * 100;
                            @endphp
                            <div class="bg-blue-600 h-2.5 rounded-full"
                                 style="width: {{ $memoryUsagePercentage }}%"></div>
                        </div>
                    </div>

                    <!-- Использование CPU -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-gray-600">Использование CPU:</span>
                        <span class="ml-2 font-semibold">{{ $systemData['cpu_usage'] }}%</span>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                            <div class="bg-blue-600 h-2.5 rounded-full"
                                 style="width: {{ $systemData['cpu_usage'] }}%"></div>
                        </div>
                    </div>

                    <!-- Количество пользователей -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-gray-600">Пользователи:</span>
                        <span
                            class="ml-2 font-semibold">{{ $systemData['users_active'] }} / {{ $systemData['total_user'] }}</span>
                    </div>

                    <!-- Входящий трафик -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-gray-600">Входящий трафик:</span>
                        <span class="ml-2 font-semibold">{{ number_format($systemData['incoming_bandwidth'] / 1024 / 1024, 2) }} MB</span>
                        <span class="text-sm text-gray-500">(Скорость: {{ number_format($systemData['incoming_bandwidth_speed'] / 1024, 2) }} KB/s)</span>
                    </div>

                    <!-- Исходящий трафик -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-gray-600">Исходящий трафик:</span>
                        <span class="ml-2 font-semibold">{{ number_format($systemData['outgoing_bandwidth'] / 1024 / 1024, 2) }} MB</span>
                        <span class="text-sm text-gray-500">(Скорость: {{ number_format($systemData['outgoing_bandwidth_speed'] / 1024, 2) }} KB/s)</span>
                    </div>
                </div>
            @else
                <div class="text-center text-gray-600 py-6">
                    Не удалось загрузить данные мониторинга.
                </div>
            @endif
        </div>
    </div>
@endsection
