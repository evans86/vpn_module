<div class="flex justify-between items-center mt-4">
    <div>
        <span class="text-sm text-gray-500">
            Показано с {{ $logs->firstItem() ?? 0 }} по {{ $logs->lastItem() ?? 0 }} из {{ $logs->total() }}
        </span>
    </div>
    <div>
        {{ $logs->appends(request()->query())->links('vendor.pagination.tailwind') }}
    </div>
</div>

