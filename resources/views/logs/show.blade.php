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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
                    <pre class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-sm whitespace-pre-wrap break-words font-mono">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </x-admin.card>
            </div>
        </x-admin.card>
    </div>
@endsection
