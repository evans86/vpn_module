@props(['title', 'tools' => null])

<div class="card">
    <div class="card-header">
        <h4 class="card-title">{{ $title }}</h4>
        @if($tools)
            <div class="card-tools">
                {{ $tools }}
            </div>
        @endif
    </div>
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
