@props(['headers' => [], 'responsive' => true, 'class' => ''])

<div class="{{ $responsive ? 'overflow-x-auto w-full -mx-4 sm:mx-0' : 'w-full' }}" style="scrollbar-width: thin;">
    <div class="inline-block min-w-full align-middle sm:px-0 px-4">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 {{ $class }}">
                @if(!empty($headers))
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach($headers as $header)
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    {{ $header }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                <tbody class="bg-white divide-y divide-gray-200">
                    {{ $slot }}
                </tbody>
            </table>
        </div>
    </div>
</div>

