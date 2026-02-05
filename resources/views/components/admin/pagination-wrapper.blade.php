@props(['paginator'])

@if($paginator->hasPages())
    <div class="mt-6 w-full max-w-full overflow-hidden">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs sm:text-sm text-gray-700 text-center sm:text-left flex-shrink-0">
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
            <div class="flex items-center gap-1 sm:gap-2 justify-center min-w-0 flex-1 sm:flex-initial">
                <div class="overflow-x-auto w-full sm:w-auto" style="scrollbar-width: thin;">
                    <div class="flex-shrink-0 inline-block">
                        {{ $paginator->appends(request()->query())->links('vendor.pagination.tailwind') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

