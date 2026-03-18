@extends('layouts.admin')

@section('title', 'Очередь заданий')
@section('page-title', 'Очередь заданий')

@section('content')
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-500">Подключение</div>
                <div class="mt-1 text-lg font-semibold {{ $queueConnection === 'sync' ? 'text-amber-600' : 'text-gray-900' }}">
                    {{ $queueConnection }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-500">В ожидании</div>
                <div class="mt-1 text-lg font-semibold {{ $queuePending > 0 ? 'text-blue-600' : 'text-gray-900' }}">{{ $queuePending }}</div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-500">Провалилось</div>
                <div class="mt-1 text-lg font-semibold {{ $queueFailed > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $queueFailed }}</div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-500">Воркер</div>
                @if($queueConnection === 'sync')
                    <div class="mt-1 text-lg font-semibold text-gray-400">—</div>
                @elseif($workerStatus === 'active')
                    <div class="mt-1 text-lg font-semibold text-green-600">Запущен</div>
                    @if($workerLastActivityAt)
                        <div class="mt-1 text-xs text-gray-500">Активность: {{ $workerLastActivityAt->diffForHumans() }}</div>
                    @endif
                @elseif($workerStatus === 'idle')
                    <div class="mt-1 text-lg font-semibold text-gray-600">Запущен</div>
                    @if($workerLastActivityAt)
                        <div class="mt-1 text-xs text-gray-500">Последняя активность: {{ $workerLastActivityAt->diffForHumans() }}</div>
                    @endif
                @elseif($workerStatus === 'possibly_down')
                    <div class="mt-1 text-lg font-semibold text-red-600">Возможно вылетел</div>
                    @if($workerLastActivityAt)
                        <div class="mt-1 text-xs text-gray-500">Последняя активность: {{ $workerLastActivityAt->diffForHumans() }}</div>
                    @else
                        <div class="mt-1 text-xs text-gray-500">Нет данных об активности. Задания в очереди не обрабатываются.</div>
                    @endif
                @else
                    <div class="mt-1 text-lg font-semibold text-gray-400">Нет данных</div>
                    <div class="mt-1 text-xs text-gray-500">Запустите воркер — после обработки задания статус появится.</div>
                @endif
            </div>
        </div>

        {{-- Провалившиеся задания --}}
        @if($queueFailed > 0)
            <x-admin.card title="Провалившиеся задания">
                <x-slot name="tools">
                    <form action="{{ route('admin.module.queue.retry-all') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700">
                            <i class="fas fa-redo mr-2"></i>Повторить все
                        </button>
                    </form>
                    <form action="{{ route('admin.module.queue.flush') }}" method="POST" class="inline ml-2" onsubmit="return confirm('Удалить все провалившиеся из списка без повтора?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                            Очистить список
                        </button>
                    </form>
                </x-slot>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Очередь</th>
                                <th class="px-3 sm:px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Время</th>
                                <th class="px-3 sm:px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ошибка</th>
                                <th class="px-3 sm:px-6 py-2 text-right text-xs font-medium text-gray-500 uppercase">Действие</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($failedJobs as $job)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-3 text-sm text-gray-900">{{ $job->queue }}</td>
                                    <td class="px-3 sm:px-6 py-3 text-sm text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($job->failed_at)->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 sm:px-6 py-3 text-sm text-gray-600 max-w-md truncate" title="{{ $job->exception }}">{{ Str::limit($job->exception, 80) }}</td>
                                    <td class="px-3 sm:px-6 py-3 text-right">
                                        <form action="{{ route('admin.module.queue.retry') }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="uuid" value="{{ $job->uuid }}">
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Повторить</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($queueFailed > 50)
                    <p class="mt-2 text-xs text-gray-500">Показаны последние 50. Остальные: <code>php artisan queue:failed</code></p>
                @endif
            </x-admin.card>
        @endif
    </div>
@endsection
