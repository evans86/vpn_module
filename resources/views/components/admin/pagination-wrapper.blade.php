@props(['paginator'])

@if($paginator->hasPages())
    <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 px-4 sm:px-0">
        <div class="text-xs sm:text-sm text-gray-700 text-center sm:text-left">
            <span class="hidden sm:inline">Показано </span>
            <span class="font-medium">{{ $paginator->firstItem() ?? 0 }}</span>
            <span class="hidden sm:inline"> - </span>
            <span class="sm:hidden">-</span>
            <span class="font-medium">{{ $paginator->lastItem() ?? 0 }}</span>
            <span class="hidden sm:inline"> из </span>
            <span class="sm:hidden">/</span>
            <span class="font-medium">{{ $paginator->total() }}</span>
            <span class="hidden sm:inline"> записей</span>
        </div>
        <div class="flex items-center gap-1 sm:gap-2 w-full sm:w-auto justify-center">
            {{ $paginator->appends(request()->query())->links('vendor.pagination.tailwind') }}
        </div>
    </div>
@endif

