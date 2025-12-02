@props(['id', 'title', 'size' => 'md'])

@php
    $sizeClasses = [
        'sm' => 'sm:max-w-md',
        'md' => 'sm:max-w-lg',
        'lg' => 'sm:max-w-2xl',
        'xl' => 'sm:max-w-4xl',
    ];
@endphp

<div class="fixed z-50 inset-0 overflow-y-auto" 
     id="{{ $id }}" 
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true"
     x-data="{ open: false }"
     x-show="open"
     x-cloak
     style="display: none;"
     x-transition:enter="ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @open-modal.window="$event.detail.id === '{{ $id }}' && (open = true)"
     @close-modal.window="$event.detail.id === '{{ $id }}' && (open = false)">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
             @click="open = false"
             aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle {{ $sizeClasses[$size] ?? $sizeClasses['md'] }} sm:w-full">
            <!-- Header -->
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        {{ $title }}
                    </h3>
                    <button type="button" 
                            @click="open = false"
                            class="text-gray-400 hover:text-gray-600 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                {{ $slot }}
            </div>

            <!-- Footer -->
            @isset($footer)
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>

<!-- Legacy Bootstrap support -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Поддержка старых data-toggle="modal" и data-target
        $('[data-toggle="modal"][data-target="#{{ $id }}"]').on('click', function() {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: '{{ $id }}' } }));
        });
        
        // Поддержка data-dismiss="modal"
        $(document).on('click', '[data-dismiss="modal"]', function() {
            const modal = $(this).closest('.fixed.inset-0');
            if (modal.length) {
                window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: modal.attr('id') } }));
            }
        });
    });
</script>

