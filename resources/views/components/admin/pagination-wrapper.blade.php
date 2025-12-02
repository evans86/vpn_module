@props(['paginator'])

@if($paginator->hasPages())
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Показано {{ $paginator->firstItem() ?? 0 }} - {{ $paginator->lastItem() ?? 0 }} из {{ $paginator->total() }} записей
        </div>
        <div class="flex items-center gap-2">
            {{ $paginator->appends(request()->query())->links('vendor.pagination.tailwind') }}
        </div>
    </div>
@endif

