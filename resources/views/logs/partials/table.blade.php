@forelse($logs as $log)
    <tr class="log-row log-level-{{ $log->level }} {{ in_array($log->level, ['error', 'critical', 'emergency']) ? 'table-danger' : ($log->level === 'warning' ? 'table-warning' : '') }}">
        <td>
            <small>{{ $log->created_at->format('d.m.Y H:i:s') }}</small>
        </td>
        <td>
            <span class="badge badge-{{ $log->getLevelColorClass() }}">
                <i class="fas {{ $log->getLevelIcon() }} mr-1"></i>
                {{ ucfirst($log->level) }}
            </span>
        </td>
        <td>
            <span class="badge badge-light">{{ $log->source }}</span>
        </td>
        <td>
            <div class="log-message" title="{{ $log->message }}">
                {{ $log->message_short }}
            </div>
        </td>
        <td>
            <a href="{{ route('admin.logs.show', $log) }}" 
               class="btn btn-sm btn-info"
               title="Подробнее">
                <i class="fas fa-eye"></i>
            </a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5" class="text-center text-muted py-4">
            <i class="fas fa-info-circle fa-3x mb-3"></i>
            <p>Логи не найдены</p>
        </td>
    </tr>
@endforelse
