{{-- Резерв: контроллер перенаправляет на panel-distribution. Этот шаблон не используется в нормальном потоке. --}}
@extends('layouts.admin')
@section('title', 'Перенаправление')
@section('page-title', 'Перенаправление')
@section('content')
    <div class="p-6">
        <p class="text-gray-700 mb-4">Раздел объединён со страницей «Панели и распределение».</p>
        <a href="{{ route('admin.module.panel-distribution.index') }}" class="text-indigo-600 font-medium underline">Перейти</a>
        <script>window.location.replace("{{ route('admin.module.panel-distribution.index') }}");</script>
    </div>
@endsection
