@props(['action' => '', 'method' => 'GET', 'id' => null])

<form method="{{ $method }}" action="{{ $action }}" {{ $id ? 'id='.$id : '' }} class="mb-6" x-data="{ loading: false }" @submit="loading = true">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{ $slot }}
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <button type="submit" 
                    :disabled="loading"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!loading" class="flex items-center gap-2">
                    <i class="fas fa-search"></i> Поиск
                </span>
                <span x-show="loading" class="flex items-center gap-2">
                    <i class="fas fa-spinner fa-spin"></i> Загрузка...
                </span>
            </button>
            <a href="{{ $action }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-times mr-2"></i> Сбросить
            </a>
        </div>
    </div>
</form>

