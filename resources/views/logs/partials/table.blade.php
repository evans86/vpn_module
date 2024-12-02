<!-- Таблица логов -->
<div class="table-responsive">
    <table class="table">
        <thead>
        <tr>
            <th>Время</th>
            <th>Уровень</th>
            <th>Источник</th>
            <th>Сообщение</th>
            <th>Пользователь</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                <td>
                        <span class="badge badge-{{ $log->getLevelColorClass() }}">
                            <i class="fas {{ $log->getLevelIcon() }} mr-1"></i>
                            {{ $log->level }}
                        </span>
                </td>
                <td>{{ $log->source }}</td>
                <td>{{ Str::limit($log->message, 100) }}</td>
                <td>{{ $log->user_id ?: 'Система' }}</td>
                <td>
                    <a href="{{ route('logs.show', $log) }}"
                       class="btn btn-sm btn-info"
                       data-toggle="tooltip"
                       title="Подробности">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<!-- Пагинация -->
<div class="pagination-container">
    {{ $logs->links() }}
</div>
