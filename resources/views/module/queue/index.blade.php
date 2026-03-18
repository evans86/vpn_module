@extends('layouts.admin')

@section('title', 'Очередь заданий')
@section('page-title', 'Очередь заданий')

@section('content')
    <div class="space-y-6">
        {{-- Сводка --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-500">Подключение</div>
                <div class="mt-1 text-lg font-semibold {{ $queueConnection === 'sync' ? 'text-amber-600' : 'text-gray-900' }}">
                    {{ $queueConnection }}
                </div>
                @if($queueConnection === 'sync')
                    <p class="mt-2 text-xs text-amber-700">Воркер не используется. В .env укажите <code>QUEUE_CONNECTION=database</code> для фоновых заданий.</p>
                @endif
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-500">В ожидании</div>
                <div class="mt-1 text-lg font-semibold {{ $queuePending > 0 ? 'text-blue-600' : 'text-gray-900' }}">{{ $queuePending }}</div>
                <p class="mt-2 text-xs text-gray-500">Перевыпуск ключей, рассылки, миграции и др.</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-500">Провалилось</div>
                <div class="mt-1 text-lg font-semibold {{ $queueFailed > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $queueFailed }}</div>
                @if($queueFailed > 0)
                    <p class="mt-2 text-xs text-gray-500">Ниже можно повторить или очистить.</p>
                @endif
            </div>
        </div>

        {{-- Как запустить воркер, чтобы не отключался --}}
        <x-admin.card title="Как держать воркер запущенным (чтобы не отключался)">
            <div class="space-y-4 text-sm">
                @if($queueConnection === 'sync')
                    <p class="text-amber-700">Сначала в <code>.env</code> установите <code>QUEUE_CONNECTION=database</code> и перезапустите приложение.</p>
                @endif
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Windows (OpenServer)</h4>
                        <ul class="list-disc list-inside space-y-1 text-gray-700">
                            <li>Запуск в цикле (рекомендуется): <code class="bg-gray-100 px-1">scripts\start-queue-worker.bat</code> — окно не закрывать.</li>
                            <li>Или одна сессия: <code class="bg-gray-100 px-1">php artisan queue:work-safe database</code> в отдельном CMD.</li>
                            <li><strong>Чтобы не отключался после перезагрузки:</strong> создайте задачу в <strong>Планировщике заданий Windows</strong>: триггер «При запуске» или «При входе в систему», действие — запуск <code>php.exe</code> с аргументами <code>artisan queue:work-safe database</code>, рабочая папка — корень проекта.</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Linux / сервер</h4>
                        <ul class="list-disc list-inside space-y-1 text-gray-700">
                            <li>Фон: <code class="bg-gray-100 px-1">nohup php artisan queue:work-safe database >> storage/logs/queue-worker.log 2>&1 &</code></li>
                            <li><strong>Чтобы не отключался:</strong> используйте <strong>Supervisor</strong> — конфиг в <code>docs/supervisor-queue.conf.example</code>. Supervisor перезапустит воркер при падении и после перезагрузки сервера.</li>
                        </ul>
                    </div>
                </div>
                <p class="text-gray-500">Остановка воркера: в другом терминале <code>php artisan queue:restart</code>. Проверка: <code>php artisan queue:status</code>.</p>
            </div>
        </x-admin.card>

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
