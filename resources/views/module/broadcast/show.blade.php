@extends('layouts.admin')

@section('title', $campaign->name)
@section('page-title', 'Рассылка: ' . Str::limit($campaign->name, 40))

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <a href="{{ route('admin.module.broadcast.index') }}"
               class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left mr-2"></i> К списку рассылок
            </a>
            @if($campaign->isDraft())
                <form action="{{ route('admin.module.broadcast.start', $campaign) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-paper-plane mr-2"></i> Запустить рассылку
                    </button>
                </form>
            @endif
        </div>

        <x-admin.card title="{{ $campaign->name }}">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium
                        @if($campaign->status === 'draft') bg-gray-100 text-gray-800
                        @elseif($campaign->status === 'queued' || $campaign->status === 'running') bg-blue-100 text-blue-800
                        @elseif($campaign->status === 'completed') bg-green-100 text-green-800
                        @elseif($campaign->status === 'cancelled') bg-red-100 text-red-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ $campaign->getStatusLabel() }}
                    </span>
                    <span class="text-sm text-gray-500">
                        Создана: {{ $campaign->created_at->format('d.m.Y H:i') }}
                    </span>
                    @if($campaign->started_at)
                        <span class="text-sm text-gray-500">
                            Запуск: {{ $campaign->started_at->format('d.m.Y H:i') }}
                        </span>
                    @endif
                    @if($campaign->completed_at)
                        <span class="text-sm text-gray-500">
                            Завершена: {{ $campaign->completed_at->format('d.m.Y H:i') }}
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase">Всего получателей</div>
                        <div class="text-xl font-semibold text-gray-900">{{ $campaign->total_recipients }}</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-green-700 uppercase">Доставлено</div>
                        <div class="text-xl font-semibold text-green-800">{{ $campaign->delivered_count }}</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-red-700 uppercase">Не доставлено</div>
                        <div class="text-xl font-semibold text-red-800">{{ $campaign->failed_count }}</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-blue-700 uppercase">Ожидают</div>
                        <div class="text-xl font-semibold text-blue-800">{{ $campaign->getPendingCount() }}</div>
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Текст сообщения</h4>
                    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-800 whitespace-pre-wrap border border-gray-200">{{ $campaign->message }}</div>
                </div>
            </div>
        </x-admin.card>
    </div>
@endsection
