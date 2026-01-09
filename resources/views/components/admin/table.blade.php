@props(['headers' => [], 'responsive' => true, 'class' => ''])

<div class="{{ $responsive ? 'overflow-x-auto overflow-y-visible w-full' : 'w-full' }}" style="overflow-y: visible !important;">
    <table class="w-full divide-y divide-gray-200 {{ $class }}" style="overflow: visible;">
        @if(!empty($headers))
            <thead class="bg-gray-50">
                <tr>
                    @foreach($headers as $header)
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="bg-white divide-y divide-gray-200" style="overflow: visible;">
            {{ $slot }}
        </tbody>
    </table>
</div>

