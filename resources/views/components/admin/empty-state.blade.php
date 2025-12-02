@props(['icon' => 'fa-inbox', 'title' => 'Данные не найдены', 'description' => 'Попробуйте изменить параметры фильтрации'])

<div class="text-center py-12">
    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100 mb-4">
        <i class="fas {{ $icon }} text-2xl text-gray-400"></i>
    </div>
    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ $title }}</h3>
    <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
    @if(isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>

