@props(['headers' => [], 'rows' => [], 'actions' => true])

<div class="table-responsive">
    <table class="table table-responsive-md">
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th><strong>{{ $header }}</strong></th>
                @endforeach
                @if($actions)
                    <th></th>
                @endif
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
