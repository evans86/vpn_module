@extends('module.personal.layouts.app')

@section('content')
    <div class="soon-content">
        <h1 class="text-6xl font-bold text-blue-500 animate-pulse">СКОРО</h1>
        <p class="mt-4 text-gray-600">Мы активно работаем над личным кабинетом</p>
        <div class="mt-8">
            <div class="relative pt-1">
                <div class="flex mb-2 items-center justify-between">
                    <div>
                    <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200">
                        В разработке
                    </span>
                    </div>
                    <div class="text-right">
                    <span class="text-xs font-semibold inline-block text-blue-600">
                        65%
                    </span>
                    </div>
                </div>
                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-blue-200">
                    <div style="width:65%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

<style>
    .soon-container {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 70vh;
        text-align: center;
    }
    .soon-content h1 {
        font-size: 5rem;
        font-weight: bold;
        color: #3b82f6;
        margin-bottom: 2rem;
    }
    .soon-content p {
        font-size: 1.2rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }
</style>
