@props(['title' => null, 'tools' => null, 'class' => ''])

@php
    // Получаем title и tools из пропов или слотов
    // В Blade, когда используется <x-slot name="title">, создается переменная $title
    // Когда используется title="...", это передается через проп $title
    // Приоритет: слот > проп
    $titleContent = isset($title) && $title !== null ? $title : null;
    $toolsContent = isset($tools) && $tools !== null ? $tools : null;
@endphp

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full {{ $class }}">
    @if($titleContent || $toolsContent)
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-4">
            @if($titleContent)
                <h3 class="text-lg font-semibold text-gray-900">{{ $titleContent }}</h3>
            @endif
            @if($toolsContent)
                <div class="flex items-center gap-2">
                    {{ $toolsContent }}
                </div>
            @endif
        </div>
    @endif
    <div class="p-6">
        {{ $slot }}
    </div>
</div>

