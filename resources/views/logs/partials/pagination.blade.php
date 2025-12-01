<div class="d-flex justify-content-between align-items-center mt-3">
    <div>
        <span class="text-muted">
            Показано с {{ $logs->firstItem() ?? 0 }} по {{ $logs->lastItem() ?? 0 }} из {{ $logs->total() }}
        </span>
    </div>
    <div>
        {{ $logs->appends(request()->query())->links() }}
    </div>
</div>

