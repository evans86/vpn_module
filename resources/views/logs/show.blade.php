@extends('layouts.admin')

@section('title', 'Детали лога')
@section('page-title', 'Детали лога #' . $log->id)

@section('content')
    <div class="space-y-6">
        <x-admin.card>
            <x-slot name="title">
                Детали лога #{{ $log->id }}
            </x-slot>
            <x-slot name="tools">
                <a href="{{ route('admin.logs.index') }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-arrow-left mr-2"></i> Назад к списку
                </a>
            </x-slot>

            <!-- Основная информация о логе -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                            <i class="fas fa-clock text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Время</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $log->created_at->format('Y-m-d H:i:s') }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-lg p-3
                            {{ $log->getLevelColorClass() === 'danger' ? 'bg-red-100' : '' }}
                            {{ $log->getLevelColorClass() === 'warning' ? 'bg-yellow-100' : '' }}
                            {{ $log->getLevelColorClass() === 'info' ? 'bg-blue-100' : '' }}
                            {{ $log->getLevelColorClass() === 'secondary' ? 'bg-gray-100' : '' }}">
                            <i class="fas {{ $log->getLevelIcon() }} 
                                {{ $log->getLevelColorClass() === 'danger' ? 'text-red-600' : '' }}
                                {{ $log->getLevelColorClass() === 'warning' ? 'text-yellow-600' : '' }}
                                {{ $log->getLevelColorClass() === 'info' ? 'text-blue-600' : '' }}
                                {{ $log->getLevelColorClass() === 'secondary' ? 'text-gray-600' : '' }} text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Уровень</p>
                            <p class="text-lg font-semibold text-gray-900">{{ ucfirst($log->level) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-indigo-100 rounded-lg p-3">
                            <i class="fas fa-tag text-indigo-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Источник</p>
                            <p class="text-lg font-semibold text-gray-900">{{ \App\Models\Log\ApplicationLog::getAllPossibleSources()[$log->source] ?? $log->source }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-gray-100 rounded-lg p-3">
                            <i class="fas fa-user text-gray-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Пользователь</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $log->user_id ?: 'Система' }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-gray-100 rounded-lg p-3">
                            <i class="fas fa-network-wired text-gray-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">IP адрес</p>
                            <p class="text-lg font-semibold text-gray-900 font-mono text-sm">{{ $log->ip_address }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Сообщение -->
            <div class="mb-6">
                <x-admin.card title="Сообщение">
                    <pre class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-sm whitespace-pre-wrap break-words font-mono">{{ $log->message }}</pre>
                </x-admin.card>
            </div>

            <!-- Контекст -->
            <div>
                <x-admin.card title="Контекст">
                    @php
                        $context = $log->context;
                        
                        // Если контекст - строка, пытаемся декодировать JSON
                        if (is_string($context)) {
                            $decoded = json_decode($context, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $context = $decoded;
                            }
                        }
                        
                        // Если контекст - массив, форматируем его
                        if (is_array($context) && count($context) > 0) {
                            // Рекурсивно обрабатываем вложенные JSON строки
                            array_walk_recursive($context, function(&$value) {
                                if (is_string($value)) {
                                    $decoded = json_decode($value, true);
                                    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                                        $value = $decoded;
                                    }
                                }
                            });
                            
                            $formattedContext = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        } elseif (is_string($context) && !empty($context)) {
                            $formattedContext = $context;
                        } else {
                            $formattedContext = null;
                        }
                    @endphp
                    
                    @if($formattedContext)
                        <pre class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-sm whitespace-pre-wrap break-words font-mono overflow-x-auto max-h-96 overflow-y-auto">{{ $formattedContext }}</pre>
                    @else
                        <p class="text-gray-500 italic">Контекст отсутствует</p>
                    @endif
                </x-admin.card>
            </div>
        </x-admin.card>
    </div>
@endsection
