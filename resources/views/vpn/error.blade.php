@extends('layouts.public')

@section('title', 'Ошибка — VPN Service')

@section('content')
<div class="flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 py-2 px-4 min-h-[calc(100vh-8rem)]">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Error Icon Section -->
            <div class="bg-gradient-to-br from-red-500 to-red-600 px-6 py-4 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-white rounded-full mb-2 shadow-lg">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-white">Ошибка</h2>
            </div>

            <!-- Error Message Section -->
            <div class="px-6 py-4">
                <div class="bg-red-50 border-l-4 border-red-500 rounded-r-lg p-3 mb-3">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-2 flex-1">
                            <p class="text-sm font-medium text-red-800 leading-relaxed">
                                {{ $message }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div>
                    <a href="{{ route('netcheck.index') }}" 
                       class="w-full inline-flex items-center justify-center px-4 py-2.5 border border-transparent rounded-lg text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-lg hover:shadow-xl">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Проверить качество сети
                    </a>
                </div>

                <!-- Help Text -->
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <p class="text-xs text-gray-500 text-center">
                        Если проблема сохраняется, обратитесь в поддержку
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
