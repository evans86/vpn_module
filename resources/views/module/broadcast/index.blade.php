@extends('layouts.admin')

@section('title', 'Рассылки')
@section('page-title', 'Рассылки')

@section('content')
    <div class="space-y-6">
        <p class="text-sm text-gray-600">
            <a href="{{ route('admin.module.queue.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                <i class="fas fa-tasks mr-1"></i>Управление очередью заданий
            </a>
            (перевыпуск ключей, рассылки, миграции — запуск воркера и контроль)
        </p>

        <x-admin.card title="Рассылки">
            <x-slot name="tools">
                <a href="{{ route('admin.module.broadcast.create') }}"
                   class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-plus mr-2"></i>Создать рассылку
                </a>
            </x-slot>
            @if($campaigns->isEmpty())
                <x-admin.empty-state
                    icon="fa-paper-plane"
                    title="Рассылок пока нет"
                    description="Создайте рассылку, укажите текст и запустите отправку."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Название</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Доставлено / Не доставлено</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($campaigns as $campaign)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $campaign->id }}</td>
                                    <td class="px-3 sm:px-6 py-4 text-sm text-gray-900">
                                        <span class="hidden sm:inline">{{ Str::limit($campaign->name, 40) }}</span>
                                        <span class="sm:hidden">{{ Str::limit($campaign->name, 20) }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($campaign->status === 'draft') bg-gray-100 text-gray-800
                                            @elseif($campaign->status === 'queued' || $campaign->status === 'running') bg-blue-100 text-blue-800
                                            @elseif($campaign->status === 'completed') bg-green-100 text-green-800
                                            @elseif($campaign->status === 'cancelled') bg-red-100 text-red-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ $campaign->getStatusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <span class="text-green-600 font-medium">{{ $campaign->delivered_count }}</span>
                                        <span class="text-gray-400 mx-1">/</span>
                                        <span class="text-red-600 font-medium">{{ $campaign->failed_count }}</span>
                                        @if($campaign->total_recipients > 0)
                                            <span class="text-gray-400 text-xs"> из {{ $campaign->total_recipients }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $campaign->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <a href="{{ route('admin.module.broadcast.show', $campaign) }}"
                                           class="text-indigo-600 hover:text-indigo-900 font-medium">
                                            Подробнее
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-admin.pagination-wrapper :paginator="$campaigns" />
            @endif
        </x-admin.card>
    </div>
@endsection
