@forelse($logs as $log)
    <tr class="log-row hover:bg-gray-50 {{ in_array($log->level, ['error', 'critical', 'emergency']) ? 'bg-red-50' : ($log->level === 'warning' ? 'bg-yellow-50' : '') }}">
        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
            <span class="hidden sm:inline">{{ $log->created_at->format('d.m.Y H:i:s') }}</span>
            <span class="sm:hidden">{{ $log->created_at->format('d.m.Y H:i') }}</span>
        </td>
        <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
            <span class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium
                {{ $log->getLevelColorClass() === 'danger' ? 'bg-red-100 text-red-800' : '' }}
                {{ $log->getLevelColorClass() === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                {{ $log->getLevelColorClass() === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                {{ $log->getLevelColorClass() === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}
                {{ $log->getLevelColorClass() === 'success' ? 'bg-green-100 text-green-800' : '' }}">
                <i class="fas {{ $log->getLevelIcon() }} mr-1"></i>
                <span class="hidden sm:inline">{{ ucfirst($log->level) }}</span>
                <span class="sm:hidden">{{ strtoupper(substr($log->level, 0, 1)) }}</span>
            </span>
        </td>
        <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
            <span class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                <span class="hidden sm:inline">{{ $log->source }}</span>
                <span class="sm:hidden">{{ Str::limit($log->source, 8) }}</span>
            </span>
        </td>
        <td class="px-3 sm:px-6 py-4 text-xs sm:text-sm">
            <div class="log-message max-w-md" title="{{ $log->message }}">
                <span class="hidden sm:inline">{{ $log->message_short }}</span>
                <span class="sm:hidden">{{ Str::limit($log->message_short, 30) }}</span>
            </div>
        </td>
        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-right text-xs sm:text-sm font-medium">
            <a href="{{ route('admin.logs.show', $log) }}" 
               class="inline-flex items-center px-2 sm:px-3 py-1.5 sm:py-2 border border-transparent text-xs sm:text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
               title="Подробнее">
                <i class="fas fa-eye"></i>
            </a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5" class="px-3 sm:px-6 py-12 text-center">
            <i class="fas fa-info-circle text-4xl text-gray-300 mb-3"></i>
            <p class="text-gray-500">Логи не найдены</p>
        </td>
    </tr>
@endforelse
